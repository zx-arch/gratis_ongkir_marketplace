<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Menjalankan seeder lain
            $this->call([ProductsSeeder::class]);
            DB::commit();

        } catch (Throwable $e) {
            DB::rollBack();
            echo "Seeder failed: " . $e->getMessage();
        }
    }
}