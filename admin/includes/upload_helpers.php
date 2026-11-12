<?php

function saveAdminImageUpload(array $file): ?string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || !isset($file['tmp_name'], $file['size'])) {
        throw new RuntimeException('The image could not be uploaded.');
    }
    if ((int) $file['size'] < 1 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Images must be no larger than 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mime) || !isset($extensions[$mime]) || getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Upload a valid JPEG, PNG, or WebP image.');
    }

    $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The image upload directory is unavailable.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The image could not be saved.');
    }

    return 'uploads/' . $fileName;
}

function removeAdminImageUpload(?string $relativePath): bool
{
    if ($relativePath === null || $relativePath === '') {
        return true;
    }

    $uploadDirectory = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads');
    $filePath = realpath(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
    if (!$uploadDirectory || !$filePath || !str_starts_with($filePath, $uploadDirectory . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
        return false;
    }

    return unlink($filePath);
}
