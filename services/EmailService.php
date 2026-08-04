<?php
namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use Environment;

class EmailService {
    private $isDev;
    private $logFile;

    public function __construct() {
        if (!class_exists('Environment')) {
            require_once __DIR__ . '/../config/environment.php';
            Environment::load();
        }
        $this->isDev = Environment::get('APP_ENV', 'development') !== 'production';
        $this->logFile = __DIR__ . '/../logs/email.log';
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
            // SMTP Ayarları
            $mail->isSMTP();
            $mail->Host       = Environment::get('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = Environment::get('MAIL_USER', '');
            $mail->Password   = Environment::get('MAIL_PASS', '');
            
            // Port'a göre şifreleme türü belirle
            $port = (int)Environment::get('MAIL_PORT', '587');
            $mail->Port = $port;
            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            // Gönderici ve Alıcı Ayarları
            $mail->setFrom(Environment::get('MAIL_FROM', 'noreply@example.com'), Environment::get('MAIL_FROM_NAME', 'Sistem'));
            $mail->addAddress($toEmail, $toName);
            if ($replyTo) {
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
        $adminEmail = Environment::get('ADMIN_EMAIL', '');
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
