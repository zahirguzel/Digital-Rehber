<?php
/**
 * Pure-PHP TOTP (RFC 6238) implementation for Google Authenticator.
 * Zero external dependencies — uses only PHP native functions.
 */

/**
 * Generate a random 16-character Base32 secret key.
 */
function generateSecret() {
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Decode a Base32 string to raw binary.
 */
function base32Decode($secret) {
    $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret      = strtoupper($secret);
    $buffer      = 0;
    $bufferSize  = 0;
    $output      = '';

    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        if ($char === '=') break;
        $val = strpos($base32chars, $char);
        if ($val === false) continue;
        $buffer     = ($buffer << 5) | $val;
        $bufferSize += 5;
        if ($bufferSize >= 8) {
            $bufferSize -= 8;
            $output .= chr(($buffer >> $bufferSize) & 0xFF);
        }
    }
    return $output;
}

/**
 * Generate a 6-digit TOTP code for a given secret and Unix timestamp counter.
 */
function generateCode($secret, $timeSlice) {
    $key     = base32Decode($secret);
    $time    = pack('N*', 0) . pack('N*', $timeSlice);
    $hmac    = hash_hmac('sha1', $time, $key, true);
    $offset  = ord($hmac[strlen($hmac) - 1]) & 0x0F;
    $code    = (
        ((ord($hmac[$offset])     & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) <<  8) |
         (ord($hmac[$offset + 3]) & 0xFF)
    ) % 1000000;
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify a 6-digit code with ±1 time-slot tolerance (±30s window).
 *
 * @param string $secret  Base32 secret stored for the admin
 * @param string $code    6-digit code entered by the user
 * @return bool
 */
function verifyCode($secret, $code) {
    $code      = preg_replace('/\D/', '', $code); // strip spaces/dashes
    $timeSlice = (int)floor(time() / 30);

    for ($i = -1; $i <= 1; $i++) {
        if (generateCode($secret, $timeSlice + $i) === $code) {
            return true;
        }
    }
    return false;
}

/**
 * Build the otpauth:// URI for Google Authenticator.
 */
function getOtpauthUrl($username, $secret, $issuer = 'HatayWeb') {
    return 'otpauth://totp/'
        . rawurlencode($issuer . ':' . $username)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}

/**
 * Return a QR code image URL via api.qrserver.com (no API key needed).
 */
function getQrUrl($username, $secret, $issuer = 'HatayWeb') {
    $otpauth = getOtpauthUrl($username, $secret, $issuer);
    return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($otpauth);
}
