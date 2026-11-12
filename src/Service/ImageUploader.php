<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploader
{
    private const DIRECTORIES = ['uploads', 'images/avatars', 'images/testimonials'];
    private const EXTENSIONS = ['jpg', 'png', 'webp'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDirectory,
    ) {
    }

    public function upload(UploadedFile $file, string $directory): string
    {
        $this->assertDirectory($directory);

        $image = getimagesize($file->getPathname());
        $extension = match ($image[2] ?? null) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
        if ($extension === null) {
            throw new \RuntimeException('Upload a valid JPEG, PNG, or WebP image.');
        }

        $targetDirectory = $this->publicDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException('The image upload directory is unavailable.');
        }

        $fileName = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($targetDirectory, $fileName);

        return $directory.'/'.$fileName;
    }

    public function remove(?string $relativePath, string $directory): void
    {
        $this->assertDirectory($directory);
        if ($relativePath === null || !str_starts_with($relativePath, $directory.'/')) {
            return;
        }

        $targetDirectory = realpath($this->publicDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory));
        $filePath = realpath($this->publicDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath));
        if ($targetDirectory === false || $filePath === false || !str_starts_with($filePath, $targetDirectory.DIRECTORY_SEPARATOR)) {
            return;
        }
        if (is_file($filePath) && !unlink($filePath)) {
            throw new \RuntimeException('The previous image could not be removed.');
        }
    }

    private function assertDirectory(string $directory): void
    {
        if (!in_array($directory, self::DIRECTORIES, true)) {
            throw new \InvalidArgumentException('Unsupported image upload destination.');
        }
    }
}
