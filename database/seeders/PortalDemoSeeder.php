<?php

namespace Database\Seeders;

use App\Models\FamilyBilling;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalDemoSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'teacher.demo@pibg.test'],
            [
                'name' => 'Cikgu Demo',
                'role' => 'teacher',
                'phone' => '01110000001',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'pta.demo@pibg.test'],
            [
                'name' => 'AJK PIBG Demo',
                'role' => 'pta',
                'phone' => '01110000002',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ],
        );

        User::updateOrCreate(
            ['email' => 'parent.demo@pibg.test'],
            [
                'name' => 'Puan Demo',
                'role' => 'parent',
                'phone' => '0123456789',
                // Parent account is TAC-only. Password remains random and unknown.
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ],
        );

        Student::updateOrCreate(
            ['student_no' => '4A-0001'],
            [
                'family_code' => 'FAM-0001',
                'full_name' => 'Adam Hakim',
                'class_name' => 'Tahun 4 Amanah',
                'parent_name' => 'Puan Demo',
                'parent_phone' => '0123456789',
                'parent_email' => 'parent.demo@pibg.test',
                'total_fee' => 100.00,
                'paid_amount' => 50.00,
                'status' => 'active',
            ],
        );

        Student::updateOrCreate(
            ['student_no' => '6B-0007'],
            [
                'family_code' => 'FAM-0001',
                'full_name' => 'Aisyah Sofea',
                'class_name' => 'Tahun 6 Bestari',
                'parent_name' => 'Puan Demo',
                'parent_phone' => '0123456789',
                'parent_email' => 'parent.demo@pibg.test',
                'total_fee' => 0.00,
                'paid_amount' => 0.00,
                'status' => 'active',
            ],
        );

        FamilyBilling::query()->updateOrCreate(
            [
                'family_code' => 'FAM-0001',
                'billing_year' => now()->year,
            ],
            [
                'fee_amount' => 100.00,
                'paid_amount' => 50.00,
                'status' => 'partial',
            ],
        );
    }
}