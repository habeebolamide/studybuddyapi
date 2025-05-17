<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = array(
            array(
                'name' => 'Test',
                'uname' => 'Testing',
                'email' => "test@test.com",
                'password' => Hash::make('12345678'),
                'phone' => "9025917185",
                'email_verified_at' => Carbon::now(),
            )
        );


        foreach ($admin as $value) {
            $user = User::Create($value);
        }
    }
}
