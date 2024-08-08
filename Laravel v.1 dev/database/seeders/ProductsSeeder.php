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
        DB::beginTransaction();

        try {
            // Memasukkan data ke dalam tabel products dengan pengecekan
            $existingProducts = Products::whereIn('name', collect($this->data())->pluck('name'))->get()->keyBy('name');

            foreach ($this->data() as $product) {
                if (isset($existingProducts[$product['name']])) {
                    // Jika produk sudah ada, perbarui
                    $existingProducts[$product['name']]->update($product);
                } else {
                    // Jika produk belum ada, buat baru
                    $products = Products::create($product);
                    $products->update([
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

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