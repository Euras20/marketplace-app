<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\AdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FoundationSeeder extends Seeder
{
public function run(): void
{
    Store::updateOrCreate(
        ['code' => 'agri'],
        [
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Agricultural Marketplace',
            'primary_domain' => 'agri.example.com',
            'status' => 'active',
        ]
    );

    Store::updateOrCreate(
        ['code' => 'adult'],
        [
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Adult Products Marketplace',
            'primary_domain' => 'adult.example.com',
            'status' => 'active',
        ]
    );

    AdminUser::updateOrCreate(
        ['email' => 'admin@example.com'],
        [
            'public_id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Global Administrator',
            'password' => Hash::make('change-me-on-first-login'),
        ]
    );
}
}
