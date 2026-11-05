<?php

namespace Tests\Feature;

use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_beranda_publik_renders_successfully(): void
    {
        $this->seed(EssentialDesaSeeder::class);

        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Beranda')
                ->where('profil_desa.nama_desa', 'Tamarenja')
            );
    }
}
