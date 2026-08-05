<?php
/**
 * Global Image Processing Helper
 * Resizes, compresses, and converts images to WebP format.
 */

function processAndSaveImage($fileArray, $targetDir, $prefix = 'img_', $maxSizeMb = 1, $maxWidth = 1200, $maxHeight = 1200) {
    if (!isset($fileArray['error']) || is_array($fileArray['error']) || $fileArray['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Dosya yükleme hatası.'];
    }

    $maxBytes = $maxSizeMb * 1024 * 1024;
    if ($fileArray['size'] > $maxBytes) {
        return ['success' => false, 'error' => "Dosya boyutu çok büyük (Max: {$maxSizeMb} MB)."];
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $imageInfo = @getimagesize($fileArray['tmp_name']);
    if ($imageInfo === false || !in_array($imageInfo['mime'], $allowedMimeTypes)) {
        return ['success' => false, 'error' => 'Geçersiz veya desteklenmeyen resim formatı (Sadece JPG, PNG, WebP).'];
    }

    $origWidth = $imageInfo[0];
    $origHeight = $imageInfo[1];
    $mime = $imageInfo['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = @imagecreatefromjpeg($fileArray['tmp_name']);
            break;
        case 'image/png':
            $sourceImage = @imagecreatefrompng($fileArray['tmp_name']);
            break;
        case 'image/webp':
            $sourceImage = @imagecreatefromwebp($fileArray['tmp_name']);
            break;
        default:
            return ['success' => false, 'error' => 'Desteklenmeyen resim formatı.'];
    }

    if (!$sourceImage) {
        return ['success' => false, 'error' => 'Resim işlenirken hata oluştu.'];
    }

    // Calculate dimensions maintaining aspect ratio
    $newWidth = $origWidth;
    $newHeight = $origHeight;

    if ($origWidth > $maxWidth || $origHeight > $maxHeight) {
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth = (int) round($origWidth * $ratio);
        $newHeight = (int) round($origHeight * $ratio);
    }

    $targetImage = imagecreatetruecolor($newWidth, $newHeight);

    // Maintain transparency for PNG/WebP
    imagealphablending($targetImage, false);
    imagesavealpha($targetImage, true);
    $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
    imagefilledrectangle($targetImage, 0, 0, $newWidth, $newHeight, $transparent);

    imagecopyresampled($targetImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

    // Create target directory if it doesn't exist
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    // Generate unique filename with WebP extension
    $newFilename = uniqid($prefix . time() . '_') . '.webp';
    $targetPath = rtrim($targetDir, '/') . '/' . $newFilename;

    // Save as WebP with 80% quality
    $saveSuccess = imagewebp($targetImage, $targetPath, 80);

    imagedestroy($sourceImage);
    imagedestroy($targetImage);

    if ($saveSuccess) {
        return ['success' => true, 'filename' => $newFilename];
    } else {
        return ['success' => false, 'error' => 'Resim diske kaydedilemedi.'];
    }
}
