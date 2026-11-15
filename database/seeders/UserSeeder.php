<?php

namespace Database\Seeders;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $username = DigiflazzConstant::username();
        $apiKey = DigiflazzConstant::developmentKey();

        User::updateOrCreate(
            ['email' => 'merchant@example.com'],
            [
                'name' => 'Digiflazz Merchant',
                'username' => $username,
                'password' => Hash::make('password123'),
                'api_key' => $apiKey,
                'balance' => 100000,
                'webhook_url' => 'http://localhost:8080/api/v1/mock-webhook',
            ]
        );
    }
}
