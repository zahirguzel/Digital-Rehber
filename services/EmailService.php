<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Environment;

class EmailService {
    private $isDev;
    private $logFile;
    private $cfg; // mail config (DB öncelikli, .env fallback)

    public function __construct() {
        if (!class_exists('Environment')) {
            require_once __DIR__ . '/../config/environment.php';
            Environment::load();
        }
        $this->isDev = Environment::get('APP_ENV', 'development') !== 'production';
        $this->logFile = __DIR__ . '/../logs/email.log';
        $this->cfg = $this->loadMailConfig();
    }

    /**
     * Mail ayarlarını DB'den okur, boşsa .env'e düşer.
     */
    private function loadMailConfig(): array {
        $dbHost  = Environment::get('MAIL_HOST', 'smtp.gmail.com');
        $dbPort  = (int)Environment::get('MAIL_PORT', '587');
        $dbUser  = Environment::get('MAIL_USER', '');
        $dbPass  = Environment::get('MAIL_PASS', '');
        $dbFrom  = Environment::get('MAIL_FROM', '');
        $dbFromName = Environment::get('MAIL_FROM_NAME', 'Sistem');
        $dbAdminEmail = Environment::get('ADMIN_EMAIL', '');

        // DB'den oku (settings tablosu id=1)
        try {
            if (class_exists('Database')) {
                $pdo = \Database::getInstance()->getPDO();
                $row = $pdo->query("SELECT mail_host, mail_port, mail_user, mail_pass, mail_from, mail_from_name, mail_admin_email FROM settings WHERE id = 1")->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    if (!empty($row['mail_host']))       $dbHost       = $row['mail_host'];
                    if (!empty($row['mail_port']))       $dbPort       = (int)$row['mail_port'];
                    if (!empty($row['mail_user']))       $dbUser       = $row['mail_user'];
                    if (!empty($row['mail_pass']))       $dbPass       = $row['mail_pass'];
                    if (!empty($row['mail_from']))       $dbFrom       = $row['mail_from'];
                    if (!empty($row['mail_from_name'])) $dbFromName   = $row['mail_from_name'];
                    if (!empty($row['mail_admin_email'])) $dbAdminEmail = $row['mail_admin_email'];
                }
            }
        } catch (\Exception $e) {
            // DB okunamazsa .env değerleri kullanılmaya devam eder
        }

        return [
            'host'        => $dbHost,
            'port'        => $dbPort,
            'user'        => $dbUser,
            'pass'        => $dbPass,
            'from'        => $dbFrom ?: $dbUser,
            'from_name'   => $dbFromName,
            'admin_email' => $dbAdminEmail,
        ];
    }

    /**
     * Ortak E-posta Gönderme Fonksiyonu
     */
    private function sendEmail($toEmail, $toName, $subject, $body, $replyTo = null) {
        if ($this->isDev) {
            // Geliştirme (Dev) modunda mail gönderme, log'a yaz.
            $logContent = "[" . date('Y-m-d H:i:s') . "] MOCK EMAIL SENT\n";
            $logContent .= "TO: $toEmail ($toName)\n";
            if ($replyTo) $logContent .= "REPLY-TO: $replyTo\n";
            $logContent .= "SUBJECT: $subject\n";
            $logContent .= "BODY:\n$body\n";
            $logContent .= "---------------------------------------------------\n";
            @file_put_contents($this->logFile, $logContent, FILE_APPEND);
            return true;
        }

        // Canlı (Production) modunda PHPMailer ile gönder
        $mail = new PHPMailer(true);
        try {
            // SMTP Ayarları (DB öncelikli)
            $mail->isSMTP();
            $mail->Host     = $this->cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->cfg['user'];
            $mail->Password = $this->cfg['pass'];
            $mail->Port     = $this->cfg['port'];
            $mail->SMTPSecure = ($this->cfg['port'] === 465)
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            // Gönderici ve Alıcı
            $mail->setFrom($this->cfg['from'], $this->cfg['from_name']);
            $mail->addAddress($toEmail, $toName);
            if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo);
            }

            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log("Email Gönderim Hatası: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Şifre Sıfırlama (OTP) Kodu Gönder
     */
    public function sendPasswordResetEmail($toEmail, $otpCode) {
        $subject = 'Şifre Sıfırlama Kodunuz';
        $body = "
            <h2>Şifre Sıfırlama İsteği</h2>
            <p>Hesabınızın şifresini sıfırlamak için doğrulama kodunuz aşağıdadır:</p>
            <h1 style='color:#0EA5E9; letter-spacing: 5px;'>{$otpCode}</h1>
            <p>Bu kod 5 dakika boyunca geçerlidir. Şifre sıfırlama talebini siz yapmadıysanız bu e-postayı dikkate almayın.</p>
        ";
        return $this->sendEmail($toEmail, 'Kullanıcı', $subject, $body);
    }

    /**
     * Influencer İş Birliği Talebi Gönder
     */
    public function sendCollaborationEmail($toEmail, $influencerName, $data) {
        $subject = 'Yeni Bir İş Birliği Talebiniz Var!';
        $body = "
            <h2>Merhaba {$influencerName},</h2>
            <p>Profiliniz üzerinden size yeni bir iş birliği talebi gönderildi. Detaylar aşağıdadır:</p>
            <ul>
                <li><strong>İşletme / Marka:</strong> {$data['business_name']}</li>
                <li><strong>Yetkili:</strong> {$data['contact_name']}</li>
                <li><strong>E-posta:</strong> {$data['email']}</li>
                <li><strong>Telefon:</strong> {$data['phone']}</li>
                <li><strong>İş Birliği Türü:</strong> {$data['collab_type']}</li>
            </ul>
            <p><strong>Mesaj:</strong><br/>{$data['message']}</p>
            <p><em>Not: Bu talebi gönderen marka ile iletişime geçmek için bu maile yanıt verebilirsiniz.</em></p>
        ";
        return $this->sendEmail($toEmail, $influencerName, $subject, $body, $data['email']);
    }

    /**
     * Admin İletişim / Başvuru Bilgilendirme Gönder
     */
    public function sendAdminNotification($subject, $content, $replyTo = null) {
        $adminEmail = $this->cfg['admin_email'] ?: Environment::get('ADMIN_EMAIL', '');
        if (empty($adminEmail)) {
            error_log("EmailService: ADMIN_EMAIL tanimli degil.");
            return false;
        }
        
        $body = "
            <h2>Sistem Bildirimi</h2>
            <p>Web sitenizden yeni bir form/talep ulaştı:</p>
            <div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin-top:15px;'>
                {$content}
            </div>
        ";
        return $this->sendEmail($adminEmail, 'Yönetici', $subject, $body, $replyTo);
    }
}
