<?php

namespace App\Services;

use App\Services\EmailService;
use Database;
use SecurityHelper;
use Exception;
use PDO;

/**
 * PasswordResetService
 *
 * Dijital Rehber projesi için tamamen güvenli, OOP ve PDO Prepared Statement tabanlı
 * OTP (6 Haneli Tek Kullanımlık Şifre) Şifre Sıfırlama Servisi.
 *
 * Temel Özellikler ve Siber Güvenlik Önlemleri:
 * 1. SQL Injection Koruması: Tüm sorgular PDO prepare / execute ile çalıştırılır.
 * 2. Brute-Force & Flood Koruması (Rate Limiting): Bir IP adresi son 15 dakika içinde
 *    5'ten fazla başarısız deneme (FAILED_OTP) veya 5'ten fazla kod talebi (OTP_SENT) yaparsa
 *    15 dakika boyunca bloke edilir (IP_BLOCKED).
 * 3. Denetim Kaydı (Audit Trail): Yapılan her eylem 'security_logs' tablosuna kayıt edilir.
 * 4. Merkezi Şifre Standardı: Yeni şifre belirlenirken SecurityHelper kuralı uygulanır.
 * 5. Çift Hesap Desteği: Bireysel (users) ve İşletme (business_users) hesapları desteklenir.
 */

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../helpers/SecurityHelper.php';

class PasswordResetService {
    private $db;

    public function __construct() {
        // Singleton Database üzerinden PDO bağlantısı alınır
        $this->db = Database::getInstance()->getPDO();
    }

    /**
     * Adım A: Şifre sıfırlama talebi (OTP oluşturma ve kaydetme)
     *
     * @param string $email E-posta veya işletme kullanıcı adı
     * @return array ['success' => bool, 'message' => string, 'dev_otp' => string|null, 'user_type' => string|null]
     */
    public function requestOtp($email, $requestedType = 'all') {
        $email = trim($email);
        $ip    = SecurityHelper::getClientIP();

        // 1. E-posta formatı ve boşluk kontrolü
        if (empty($email)) {
            return ['success' => false, 'message' => 'Lütfen e-posta adresinizi girin.'];
        }

        // 2. IP Rate Limit (Flood / Brute-force denetimi)
        // Neden yapılır?: Kötü niyetli kişilerin sürekli kod isteyerek mail sunucusunu veya SMS servisini
        // tıkamasını (SMS/Mail Bombing) engellemek için.
        if ($this->isIpBlocked($ip)) {
            $this->logSecurity($ip, $email, 'IP_BLOCKED', '15 dakikada çok fazla kod talebi veya başarısız deneme nedeniyle bloke edildi.');
            return ['success' => false, 'message' => 'Çok fazla deneme yaptınız. Güvenliğiniz için IP adresiniz 15 dakika engellenmiştir.'];
        }

        // 3. Kullanıcının sistemde kayıtlı olup olmadığının kontrolü (Bireysel veya İşletme)
        $account = $this->findAccountByEmail($email, $requestedType);
        if (!$account) {
            // Neden sahte/genel mesaj veya uyarı logluyoruz?:
            // Güvenlik açısından "böyle bir kullanıcı yok" mesajı verilebilir veya güvenlik takibi için loglanabilir.
            $this->logSecurity($ip, $email, 'FAILED_OTP_REQUEST', 'Sistemde bulunmayan e-posta ile şifre sıfırlama talebi.');
            return ['success' => false, 'message' => 'Bu e-posta adresi ile kayıtlı bir hesap bulunamadı.'];
        }

        // 4. 6 Haneli Kriptografik Güvenli OTP Kodu Üretilmesi
        // Neden random_int kullanırız?: rand() veya mt_rand() tahmin edilebilir. random_int kriptografik olarak güvenlidir.
        $otpCode   = (string) random_int(100000, 999999);
        $userType  = $account['type']; // 'user' veya 'business'
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes')); // 5 dakika geçerlilik süresi

        try {
            // 5. Eski kullanılmamış kodları pasife al (Opsiyonel temizlik)
            $stmtClean = $this->db->prepare("UPDATE password_reset_codes SET is_used = 1 WHERE email = ? AND is_used = 0");
            $stmtClean->execute([$email]);

            // 6. Yeni OTP kodunu veritabanına kaydet (PDO Prepared Statement - MySQL saatine senkronize)
            $stmtInsert = $this->db->prepare("
                INSERT INTO password_reset_codes (email, user_type, otp_code, expires_at, is_used, created_at)
                VALUES (?, ?, ?, NOW() + INTERVAL 5 MINUTE, 0, NOW())
            ");
            $stmtInsert->execute([$email, $userType, $otpCode]);

            // 7. İşlemi denetim loglarına ekle
            $this->logSecurity($ip, $email, 'OTP_SENT', "6 haneli OTP kodu üretildi ({$userType}).");

            // 8. E-posta Gönderme
            $emailService = new EmailService();
            $emailService->sendPasswordResetEmail($email, $otpCode);
            
            $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1', '::1']);

            return [
                'success'   => true,
                'message'   => 'Şifre sıfırlama kodu e-posta adresinize gönderildi. (Geçerlilik: 5 Dakika)',
                'dev_otp'   => $isLocalhost ? $otpCode : null, // Geliştirme ortamında ekrana basmaya devam etsin
                'user_type' => $userType,
                'email'     => $email
            ];

        } catch (\Throwable $e) {
            $this->logSecurity($ip, $email, 'ERROR', 'OTP kaydı/gönderimi sırasında hata: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Bir sistem hatası oluştu veya e-posta gönderilemedi, lütfen tekrar deneyin.'];
        }
    }

    /**
     * Adım B: OTP kodunun doğrulanması
     *
     * @param string $email
     * @param string $otpCode
     * @return array ['success' => bool, 'message' => string, 'user_type' => string|null]
     */
    public function verifyOtp($email, $otpCode) {
        $email   = trim($email);
        $otpCode = trim($otpCode);
        $ip      = SecurityHelper::getClientIP();

        // 1. IP Rate limit kontrolü
        if ($this->isIpBlocked($ip)) {
            $this->logSecurity($ip, $email, 'IP_BLOCKED', 'Brute-force şüphesiyle engel devrede.');
            return ['success' => false, 'message' => 'Çok fazla başarısız deneme yaptınız. Lütfen 15 dakika bekleyin.'];
        }

        // 2. Boş değer kontrolü
        if (empty($email) || empty($otpCode) || strlen($otpCode) !== 6) {
            return ['success' => false, 'message' => 'Lütfen 6 haneli doğrulama kodunu eksiksiz girin.'];
        }

        try {
            // 3. Kodun geçerliliğini kontrol et (Kullanılmamış ve süresi dolmamış olmalı)
            // Neden expires_at >= NOW()?: 5 dakikalık zaman aşımı süresini kesin olarak zorunlu kılmak için.
            $stmt = $this->db->prepare("
                SELECT * FROM password_reset_codes
                WHERE email = ? AND otp_code = ? AND is_used = 0 AND expires_at >= NOW()
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$email, $otpCode]);
            $codeRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$codeRow) {
                // Kod geçersiz veya süresi dolmuş
                // Neden FAILED_OTP loglanıyor?: Hatalı deneme sayısını artırmak ve brute-force saldırılarını engellemek için.
                $this->logSecurity($ip, $email, 'FAILED_OTP', "Hatalı veya süresi dolmuş OTP kodu denendi: {$otpCode}");
                return ['success' => false, 'message' => 'Girdiğiniz doğrulama kodu hatalı veya 5 dakikalık süresi dolmuş.'];
            }

            // Doğrulama başarılı
            return [
                'success'   => true,
                'message'   => 'Doğrulama başarılı.',
                'user_type' => $codeRow['user_type'],
                'email'     => $email
            ];

        } catch (Exception $e) {
            $this->logSecurity($ip, $email, 'ERROR', 'OTP doğrulama veritabanı hatası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Doğrulama sırasında hata oluştu.'];
        }
    }

    /**
     * Adım C: Yeni şifrenin standartlara uygun olarak güncellenmesi
     *
     * @param string $email
     * @param string $otpCode
     * @param string $newPassword
     * @param string $newPasswordConfirm
     * @return array ['success' => bool, 'message' => string]
     */
    public function resetPassword($email, $otpCode, $newPassword, $newPasswordConfirm) {
        $email   = trim($email);
        $otpCode = trim($otpCode);
        $ip      = SecurityHelper::getClientIP();

        // 1. IP engelli mi kontrol et
        if ($this->isIpBlocked($ip)) {
            return ['success' => false, 'message' => 'Çok fazla başarısız deneme nedeniyle IP adresiniz geçici olarak engellenmiştir.'];
        }

        // 2. Şifrelerin eşleşme kontrolü
        if ($newPassword !== $newPasswordConfirm) {
            return ['success' => false, 'message' => 'Girdiğiniz yeni şifreler eşleşmiyor.'];
        }

        // 3. Merkezi şifre kuralı kontrolü (Min 8 karakter, büyük harf, küçük harf, rakam)
        if (!SecurityHelper::validatePasswordStrength($newPassword)) {
            return ['success' => false, 'message' => SecurityHelper::getPasswordStrengthMessage()];
        }

        // 4. Kodun doğruluğunu son kez doğrula
        $verification = $this->verifyOtp($email, $otpCode);
        if (!$verification['success']) {
            return $verification;
        }

        $userType = $verification['user_type'];

        try {
            // 5. Şifreyi Bcrypt ile hashle (password_hash)
            // Neden Bcrypt / PASSWORD_DEFAULT?: Güvenli, tuzlamalı (salted) ve endüstri standardı olduğu için.
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            // 6. Kullanıcının tablosunu PDO Prepared Statement ile güncelle
            if ($userType === 'business') {
                $stmtUpd = $this->db->prepare("
                    UPDATE business_users
                    SET password = ?, force_password_change = 0 
                    WHERE username = ? OR business_id IN (SELECT id FROM businesses WHERE email = ?)
                ");
                $stmtUpd->execute([$hashedPassword, $email, $email]);
            } else {
                $stmtUpd = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
                $stmtUpd->execute([$hashedPassword, $email]);
            }

            // 7. Kullanılan OTP kodunu is_used = 1 yap (Tekrar kullanılamasın)
            $stmtUse = $this->db->prepare("UPDATE password_reset_codes SET is_used = 1 WHERE email = ? AND otp_code = ?");
            $stmtUse->execute([$email, $otpCode]);

            // 8. Başarı kaydını logla
            $this->logSecurity($ip, $email, 'PASSWORD_RESET_SUCCESS', "Kullanıcı şifresi başarıyla yenilendi ({$userType}).");

            return ['success' => true, 'message' => 'Şifreniz başarıyla yenilendi! Yeni şifrenizle giriş yapabilirsiniz.'];

        } catch (Exception $e) {
            $this->logSecurity($ip, $email, 'ERROR', 'Şifre güncellenirken veritabanı hatası: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Şifre sıfırlanırken bir hata oluştu.'];
        }
    }

    /**
     * E-posta adresinin bireysel (users) veya işletme (business_users) tablosunda olup olmadığını arar
     *
     * @param string $email
     * @return array|null ['id' => int, 'email' => string, 'name' => string, 'type' => 'user'|'business']
     */
    private function findAccountByEmail($email, $requestedType = 'all') {
        // Önce normal üyeler (users) tablosuna bak (Eğer tablo veritabanında yoksa hatayı yut)
        if ($requestedType === 'all' || $requestedType === 'user') {
            try {
                $stmtUser = $this->db->prepare("SELECT id, email, name FROM users WHERE email = ? LIMIT 1");
                $stmtUser->execute([$email]);
                $uRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if ($uRow) {
                    return [
                        'id'    => $uRow['id'],
                        'email' => $uRow['email'],
                        'name'  => $uRow['name'],
                        'type'  => 'user'
                    ];
                }
            } catch (Exception $e) {
                // users tablosu yoksa PDOException atılır, bunu yoksayıp işletmelerde aramaya devam et.
            }
        }

        // Bulunamazsa işletme hesapları (business_users) tablosuna bak (username veya b.email ile eşleşir)
        if ($requestedType === 'all' || $requestedType === 'business') {
            $stmtBiz = $this->db->prepare("
                SELECT bu.id, bu.username, b.email as biz_email, b.name as name
                FROM business_users bu
                LEFT JOIN businesses b ON bu.business_id = b.id
                WHERE bu.username = ? OR (b.email IS NOT NULL AND b.email != '' AND b.email = ?)
                LIMIT 1
            ");
            $stmtBiz->execute([$email, $email]);
            $bRow = $stmtBiz->fetch(PDO::FETCH_ASSOC);

            if ($bRow) {
                return [
                    'id'       => $bRow['id'],
                    'email'    => !empty($bRow['biz_email']) ? $bRow['biz_email'] : $bRow['username'],
                    'username' => $bRow['username'],
                    'name'     => $bRow['name'] ?: $bRow['username'],
                    'type'     => 'business'
                ];
            }
        }

        return null;
    }

    /**
     * IP Adresi engellenmiş mi? (Son 15 dakikada 5'ten fazla başarısız deneme veya istek var mı?)
     *
     * @param string $ip
     * @return bool
     */
    private function isIpBlocked($ip) {
        // Son 15 dakikada FAILED_OTP sayısı >= 5 ise VEYA OTP_SENT >= 5 ise bloke et
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM security_logs
            WHERE ip_address = ?
              AND action IN ('FAILED_OTP', 'OTP_SENT')
              AND created_at >= (NOW() - INTERVAL 15 MINUTE)
        ");
        $stmt->execute([$ip]);
        $count = (int) $stmt->fetchColumn();

        return ($count >= 5);
    }

    /**
     * Siber Güvenlik ve Denetim Logunu security_logs tablosuna yazar
     *
     * @param string $ip
     * @param string|null $email
     * @param string $action
     * @param string|null $details
     */
    private function logSecurity($ip, $email, $action, $details = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (ip_address, email, action, details, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$ip, $email, $action, $details]);
        } catch (Exception $e) {
            // Loglama hatası ana işlemi durdurmaz
        }
    }
}
