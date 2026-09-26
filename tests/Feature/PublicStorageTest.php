<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageTest extends TestCase
{
    public function test_uploaded_public_image_is_served_without_a_symbolic_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs(
            'products',
            UploadedFile::fake()->image('product.jpg', 100, 100),
            'product.jpg',
        );

        $this->get('/storage/products/product.jpg')
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_missing_public_image_returns_not_found(): void
    {
        Storage::fake('public');

        $this->get('/storage/products/missing.jpg')->assertNotFound();
    }
}
