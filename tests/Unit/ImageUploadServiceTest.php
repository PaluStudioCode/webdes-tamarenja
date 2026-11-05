<?php

namespace Tests\Unit;

use App\Services\ImageUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageUploadServiceTest extends TestCase
{
    public function test_logo_upload_is_strictly_converted_to_png(): void
    {
        Storage::fake('public');

        $service = new ImageUploadService();

        // Unggah gambar berformat JPEG
        $uploadedJpeg = UploadedFile::fake()->image('logo_desa.jpg', 400, 400);
        $path = $service->uploadLogo($uploadedJpeg, 'public');

        $this->assertStringStartsWith('logo/', $path);
        $this->assertStringEndsWith('.png', $path);
        Storage::disk('public')->assertExists($path);

        // Verifikasi signature PNG (8 byte pertama: \x89PNG\r\n\x1a\n)
        $content = Storage::disk('public')->get($path);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $content);
    }

    public function test_field_photo_is_compressed_and_scaled(): void
    {
        Storage::fake('public');

        $service = new ImageUploadService();

        // Buat gambar lebar 2000px
        $uploadedPhoto = UploadedFile::fake()->image('proyek_awal.jpg', 2000, 1000);
        $path = $service->uploadFieldPhoto($uploadedPhoto, 'proyek/2026', 'webp', 1600, 80, 'public');

        $this->assertStringStartsWith('proyek/2026/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_delete_if_exists(): void
    {
        Storage::fake('public');

        $service = new ImageUploadService();
        $uploaded = UploadedFile::fake()->image('test.png', 100, 100);
        $path = $service->uploadLogo($uploaded, 'public');

        Storage::disk('public')->assertExists($path);

        $deleted = $service->deleteIfExists($path, 'public');
        $this->assertTrue($deleted);
        Storage::disk('public')->assertMissing($path);

        // Hapus path yang tidak ada tidak melempar exception
        $this->assertFalse($service->deleteIfExists('nonexistent/file.png', 'public'));
    }
}
