<?php

namespace App\Tests\Service;

use App\Service\ImageUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageUploaderTest extends TestCase
{
    private string $publicDirectory;
    private ImageUploader $uploader;
    private ?string $temporaryFile = null;

    protected function setUp(): void
    {
        $this->publicDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'motoworks-public-'.bin2hex(random_bytes(8));
        mkdir($this->publicDirectory);
        $this->uploader = new ImageUploader($this->publicDirectory);
    }

    protected function tearDown(): void
    {
        if ($this->temporaryFile !== null && is_file($this->temporaryFile)) {
            unlink($this->temporaryFile);
        }
        $uploadsDirectory = $this->publicDirectory.DIRECTORY_SEPARATOR.'uploads';
        if (is_dir($uploadsDirectory)) {
            rmdir($uploadsDirectory);
        }
        rmdir($this->publicDirectory);
    }

    public function testUploadUsesManagedRelativePathAndCanRemoveTheFile(): void
    {
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'moto-image-');
        file_put_contents($this->temporaryFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l2cAAAAASUVORK5CYII='));
        $uploadedFile = new UploadedFile($this->temporaryFile, 'avatar.png', 'image/png', null, true);

        $path = $this->uploader->upload($uploadedFile, 'uploads');
        $absolutePath = $this->publicDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);

        self::assertMatchesRegularExpression('#^uploads/[a-f0-9]{32}\.png$#', $path);
        self::assertFileExists($absolutePath);

        $this->uploader->remove($path, 'uploads');

        self::assertFileDoesNotExist($absolutePath);
    }

    public function testUnsupportedUploadDirectoryIsRejected(): void
    {
        $this->temporaryFile = tempnam(sys_get_temp_dir(), 'moto-image-');
        file_put_contents($this->temporaryFile, 'not an image');
        $uploadedFile = new UploadedFile($this->temporaryFile, 'image.png', 'image/png', null, true);

        $this->expectException(\InvalidArgumentException::class);
        $this->uploader->upload($uploadedFile, '../outside');
    }
}
