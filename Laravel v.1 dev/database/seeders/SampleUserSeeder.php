<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class SampleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Memasukkan data ke dalam tabel users dengan pengecekan
            $existingUser = User::whereIn('email', collect($this->data())->pluck('email'))->get()->keyBy('email');

            foreach ($this->data() as $user) {
                if (isset($existingUser[$user['email']])) {
                    // Jika user sudah ada, perbarui
                    $existingUser[$user['email']]->update($user);
                } else {
                    // Jika user belum ada, buat baru
                    $newUser = User::create($user);
                    $newUser->update([
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            \Log::error('Error seeding users: ' . $e->getMessage(), ['exception' => $e]);
            echo 'Error seeding users: ' . $e->getMessage() . "\n";
        }
    }

    private function data()
    {
        return [
            [
                'name' => 'TestUser',
                'email' => 'testuser31@gmail.com',
                'password' => Hash::make('@TestUser_123') // Hashing password
            ],
            [
                'name' => 'AdminMarket',
                'email' => 'admin1@market.com',
                'password' => Hash::make('@dMIN_market') // Hashing password
            ]
        ];
    }
}