<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Products;

class ProductsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        try {
            // Menghapus semua data dari tabel hanya jika sudah ada record tabel agar bisa langsung tambah data di array
            if (DB::table('products')->count() > 0) {
                DB::table('products')->truncate();
            }

            // Memasukkan data ke dalam tabel products dengan pengecekan
            foreach ($this->data() as $product) {
                Products::updateOrCreate(
                    ['name' => $product['name']],
                    $product
                );
            }

        } catch (\Throwable $e) {
            \Log::error('Error seeding products: ' . $e->getMessage(), ['exception' => $e]);
            echo 'Error seeding products: ' . $e->getMessage() . "\n";
        }

    }

    /**
     * Data to be seeded
     */
    private function data()
    {
        return [
            [
                'name' => 'Apple',
                'price' => 1000,
                'stock' => 100
            ],
            [
                'name' => 'Banana',
                'price' => 2000,
                'stock' => 100
            ],
            [
                'name' => 'Cherry',
                'price' => 3000,
                'stock' => 100
            ],
            [
                'name' => 'Mango',
                'price' => 4000,
                'stock' => 100
            ],
            [
                'name' => 'Elderberry',
                'price' => 5000,
                'stock' => 100
            ]
        ];
    }
}