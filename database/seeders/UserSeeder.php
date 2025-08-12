<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
