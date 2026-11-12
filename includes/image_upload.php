<?php

function saveSiteImageUpload(array $file, string $type): ?string
{
    $directories = [
        'avatars' => ['folder' => 'images/avatars', 'prefix' => 'avatar_'],
        'testimonials' => ['folder' => 'images/testimonials', 'prefix' => 'testimonial_'],
    ];
    if (!isset($directories[$type])) {
        throw new InvalidArgumentException('Unsupported image upload destination.');
    }

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
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime]) || getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Upload a valid JPEG, PNG, or WebP image.');
    }

    $relativeDirectory = $directories[$type]['folder'];
    $uploadDirectory = __DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('The image upload directory is unavailable.');
    }

    $fileName = $directories[$type]['prefix'] . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('The image could not be saved.');
    }

    return $relativeDirectory . '/' . $fileName;
}

function removeSiteAvatarUpload(?string $relativePath): bool
{
    return removeSiteImageFromDirectory($relativePath, 'avatars');
}

function removeSiteTestimonialUpload(?string $relativePath): bool
{
    return removeSiteImageFromDirectory($relativePath, 'testimonials');
}

function removeSiteImageFromDirectory(?string $relativePath, string $type): bool
{
    $directories = ['avatars' => 'images/avatars', 'testimonials' => 'images/testimonials'];
    if (!isset($directories[$type]) || $relativePath === null || !str_starts_with($relativePath, $directories[$type] . '/')) {
        return false;
    }

    $targetDirectory = realpath(__DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $directories[$type]));
    $filePath = realpath(__DIR__ . '/../' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
    if (!$targetDirectory || !$filePath || dirname($filePath) !== $targetDirectory || !is_file($filePath)) {
        return false;
    }

    return unlink($filePath);
}
