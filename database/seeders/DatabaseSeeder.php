<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(EssentialDesaSeeder::class);

        if (app()->environment(['local', 'staging']) && class_exists(DummyDesaSeeder::class)) {
            $this->call(DummyDesaSeeder::class);
        }
    }
}
