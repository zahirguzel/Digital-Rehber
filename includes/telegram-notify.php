<?php
/**
 * Telegram bildirim yardımcıları — form gönderimlerinde 2 alıcıya mesaj
 */

if (!function_exists('telegramField')) {
    function telegramField($value, $fallback = 'Belirtilmedi') {
        $value = trim((string) $value);
        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('telegramGetConfig')) {
    function telegramGetConfig($pdo) {
        $defaults = [
            'telegram_enabled' => 0,
            'telegram_bot_token' => '',
            'telegram_chat_id_1' => '',
            'telegram_chat_id_2' => '',
        ];

        try {
            $row = $pdo->query('SELECT telegram_enabled, telegram_bot_token, telegram_chat_id_1, telegram_chat_id_2 FROM settings WHERE id = 1')->fetch();
            if ($row) {
                return array_merge($defaults, $row);
            }
        } catch (Exception $e) {
            error_log('Telegram ayarlari okunamadi: ' . $e->getMessage());
        }

        return $defaults;
    }
}

if (!function_exists('telegramGetChatIds')) {
    function telegramGetChatIds(array $config) {
        $ids = [];
        foreach (['telegram_chat_id_1', 'telegram_chat_id_2'] as $key) {
            $id = trim((string) ($config[$key] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

if (!function_exists('telegramSendMessage')) {
    function telegramSendMessage($botToken, $chatId, $text) {
        $botToken = trim((string) $botToken);
        $chatId = trim((string) $chatId);
        $text = trim((string) $text);
        if ($botToken === '' || $chatId === '' || $text === '') {
            return false;
        }

        if (mb_strlen($text) > 4000) {
            $text = mb_substr($text, 0, 3990) . '…';
        }

        $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';
        $payload = http_build_query([
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => '1',
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 15,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false || $httpCode < 200 || $httpCode >= 300) {
                error_log('Telegram API hatasi (curl): chat_id=' . $chatId . ' http=' . $httpCode . ' response=' . (string) $response);
                return false;
            }

            $decoded = json_decode($response, true);
            if (empty($decoded['ok'])) {
                error_log('Telegram API reddetti: chat_id=' . $chatId . ' response=' . (string) $response);
                return false;
            }
            return true;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 15,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            error_log('Telegram API hatasi (stream): chat_id=' . $chatId);
            return false;
        }

        $decoded = json_decode($response, true);
        if (empty($decoded['ok'])) {
            error_log('Telegram API reddetti: chat_id=' . $chatId . ' response=' . (string) $response);
            return false;
        }
        return true;
    }
}

if (!function_exists('telegramBroadcast')) {
    function telegramBroadcast($pdo, $text) {
        $config = telegramGetConfig($pdo);
        if ((int) ($config['telegram_enabled'] ?? 0) !== 1) {
            return ['sent' => 0, 'total' => 0];
        }

        $token = trim((string) ($config['telegram_bot_token'] ?? ''));
        $chatIds = telegramGetChatIds($config);
        if ($token === '' || empty($chatIds)) {
            return ['sent' => 0, 'total' => count($chatIds)];
        }

        $sent = 0;
        foreach ($chatIds as $chatId) {
            if (telegramSendMessage($token, $chatId, $text)) {
                $sent++;
            }
        }

        return ['sent' => $sent, 'total' => count($chatIds)];
    }
}

if (!function_exists('telegramAdminUrl')) {
    function telegramAdminUrl($path) {
        if (!function_exists('seoGetBaseUrl')) {
            require_once __DIR__ . '/seo-meta.php';
        }

        return rtrim(seoGetBaseUrl(), '/') . $path;
    }
}

if (!function_exists('telegramNotifyContactForm')) {
    function telegramNotifyContactForm($pdo, array $data) {
        $text = "📩 YENİ İLETİŞİM FORMU\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Ad Soyad: " . telegramField($data['name'] ?? '') . "\n"
            . "Telefon: " . telegramField($data['phone'] ?? '') . "\n"
            . "E-posta: " . telegramField($data['email'] ?? '') . "\n"
            . "Konu: " . telegramField($data['subject'] ?? '') . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Mesaj:\n" . telegramField($data['message'] ?? '') . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Admin: " . telegramAdminUrl('/admin/messages.php');

        return telegramBroadcast($pdo, $text);
    }
}

if (!function_exists('telegramNotifyInfluencerApplication')) {
    function telegramNotifyInfluencerApplication($pdo, array $data) {
        if (!function_exists('getInfluencerNicheLabel')) {
            require_once __DIR__ . '/influencer-helpers.php';
        }

        $text = "⭐ YENİ INFLUENCER BAŞVURUSU\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Ad Soyad: " . telegramField($data['name'] ?? '') . "\n"
            . "E-posta: " . telegramField($data['email'] ?? '') . "\n"
            . "Telefon: " . telegramField($data['phone'] ?? '') . "\n"
            . "İlçe: " . telegramField($data['district'] ?? '') . "\n"
            . "Niş: " . telegramField(getInfluencerNicheLabel($data['niche'] ?? 'diger')) . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Instagram: " . telegramField($data['instagram'] ?? '') . "\n"
            . "TikTok: " . telegramField($data['tiktok'] ?? '') . "\n"
            . "YouTube: " . telegramField($data['youtube'] ?? '') . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Bio:\n" . telegramField($data['bio'] ?? '') . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Admin: " . telegramAdminUrl('/admin/influencer-talepler.php?tab=applications');

        return telegramBroadcast($pdo, $text);
    }
}

if (!function_exists('telegramNotifyInfluencerCollaboration')) {
    function telegramNotifyInfluencerCollaboration($pdo, array $data, array $influencer) {
        if (!function_exists('influencerCollabTypes')) {
            require_once __DIR__ . '/influencer-helpers.php';
        }

        $collabTypes = influencerCollabTypes();
        $collabTypeKey = trim((string) ($data['collab_type'] ?? ''));
        $collabLabel = isset($collabTypes[$collabTypeKey])
            ? $collabTypes[$collabTypeKey]
            : ($collabTypeKey !== '' ? $collabTypeKey : 'Belirtilmedi');

        $text = "🤝 YENİ INFLUENCER İŞ BİRLİĞİ TEKLİFİ\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Influencer: " . telegramField($influencer['name'] ?? '') . "\n"
            . "Profil: " . telegramAdminUrl('/influencer/' . ($influencer['slug'] ?? '')) . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "İşletme / Marka: " . telegramField($data['business_name'] ?? '') . "\n"
            . "Yetkili: " . telegramField($data['contact_name'] ?? '') . "\n"
            . "E-posta: " . telegramField($data['email'] ?? '') . "\n"
            . "Telefon: " . telegramField($data['phone'] ?? '') . "\n"
            . "İş Birliği Türü: " . telegramField($collabLabel) . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Mesaj:\n" . telegramField($data['message'] ?? '') . "\n"
            . "━━━━━━━━━━━━━━━━━━\n"
            . "Admin: " . telegramAdminUrl('/admin/influencer-talepler.php?tab=collaborations');

        return telegramBroadcast($pdo, $text);
    }
}

if (!function_exists('telegramSendTestNotification')) {
    function telegramSendTestNotification($pdo, $force = false) {
        $config = telegramGetConfig($pdo);
        if (!$force && (int) ($config['telegram_enabled'] ?? 0) !== 1) {
            return ['sent' => 0, 'total' => 0];
        }

        $siteTitle = 'Rehber Medya';
        try {
            $siteTitle = $pdo->query('SELECT site_title FROM settings WHERE id = 1')->fetchColumn() ?: $siteTitle;
        } catch (Exception $e) {
        }

        $text = "✅ TELEGRAM TEST BİLDİRİMİ\n\n"
            . telegramField($siteTitle) . " bildirim sistemi çalışıyor.\n"
            . "Bu mesaj yapılandırılmış alıcılara gönderildi.";

        if ($force) {
            $token = trim((string) ($config['telegram_bot_token'] ?? ''));
            $chatIds = telegramGetChatIds($config);
            if ($token === '' || empty($chatIds)) {
                return ['sent' => 0, 'total' => count($chatIds)];
            }
            $sent = 0;
            foreach ($chatIds as $chatId) {
                if (telegramSendMessage($token, $chatId, $text)) {
                    $sent++;
                }
            }
            return ['sent' => $sent, 'total' => count($chatIds)];
        }

        return telegramBroadcast($pdo, $text);
    }
}
