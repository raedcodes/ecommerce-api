<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for local use only; every password is "password".
 */
class UserSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.com';

    public const CUSTOMER_EMAIL = 'customer@example.com';

    public const SECOND_CUSTOMER_EMAIL = 'customer2@example.com';

    public function run(): void
    {
        $this->account(self::ADMIN_EMAIL, 'Demo Admin', isAdmin: true);
        $this->account(self::CUSTOMER_EMAIL, 'Demo Customer');
        $this->account(self::SECOND_CUSTOMER_EMAIL, 'Second Customer');
    }

    /**
     * is_admin is deliberately not mass-assignable, so it is force-filled here.
     */
    private function account(string $email, string $name, bool $isAdmin = false): void
    {
        User::firstOrNew(['email' => $email])
            ->forceFill([
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'is_admin' => $isAdmin,
            ])
            ->save();
    }
}
