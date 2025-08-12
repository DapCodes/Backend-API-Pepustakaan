<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('rahasia123'),
        ]);

        User::create([
            'name' => 'Daffa',
            'email' => 'daffa@example.com',
            'password' => Hash::make('rahasia123'),
        ]);

        User::create([
            'name' => 'Rio',
            'email' => 'rio@example.com',
            'password' => Hash::make('rahasia123'),
        ]);
    }
}
