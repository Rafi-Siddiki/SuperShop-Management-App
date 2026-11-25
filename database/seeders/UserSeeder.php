<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    private const MALE = ['Tanvir', 'Imran', 'Sakib', 'Mahmud', 'Fahim', 'Rashed', 'Nayeem', 'Shahriar', 'Mehedi', 'Sabbir',
        'Zahid', 'Tareq', 'Asif', 'Jubayer', 'Mizanur', 'Habib', 'Kamrul', 'Nafis', 'Ashraf', 'Riyad', 'Shuvo', 'Ehsan'];
    private const FEMALE = ['Nusrat', 'Farhana', 'Tasnim', 'Sumaiya', 'Jannatul', 'Ayesha', 'Shirin', 'Nadia', 'Rumana',
        'Sharmin', 'Tahmina', 'Lamia', 'Faria', 'Anika', 'Mehjabin', 'Sabrina', 'Ishrat', 'Nabila', 'Tania', 'Afsana'];
    private const SURNAMES = ['Ahmed', 'Hossain', 'Islam', 'Rahman', 'Chowdhury', 'Khan', 'Uddin', 'Sarker', 'Haque',
        'Kabir', 'Alam', 'Talukder', 'Siddique', 'Karim', 'Hasan', 'Mahmud', 'Bhuiyan', 'Majumder', 'Akter', 'Sultana'];

    public function run(): void
    {
        $password = Hash::make('password');
        $now = now();

        $staff = [
            ['Arif Chowdhury', 'admin@example.com', User::ROLE_ADMIN, 540],
            ['Nusrat Jahan', 'nusrat@example.com', User::ROLE_ADMIN, 410],
            ['Rafiq Hossain', 'employee@example.com', User::ROLE_EMPLOYEE, 500],
            ['Sadia Akter', 'sadia@example.com', User::ROLE_EMPLOYEE, 320],
            ['Imran Kabir', 'imran@example.com', User::ROLE_EMPLOYEE, 150],
        ];

        $rows = [];
        foreach ($staff as [$name, $email, $role, $daysAgo]) {
            $rows[] = $this->row($name, $email, $role, $now->copy()->subDays($daysAgo), $password);
        }

        // The main demo customer.
        $rows[] = $this->row('Tanvir Ahmed', 'test@example.com', User::ROLE_CUSTOMER, $now->copy()->subDays(400), $password);

        // ~64 customers. A third joined over a year ago; the rest signed up steadily since.
        $used = ['test@example.com' => true];
        for ($i = 0; $i < 64; $i++) {
            $female = mt_rand(0, 1) === 1;
            $first = $female ? self::FEMALE[array_rand(self::FEMALE)] : self::MALE[array_rand(self::MALE)];
            $surnames = $female ? self::SURNAMES : array_slice(self::SURNAMES, 0, 18); // Akter/Sultana are women's names
            $last = $surnames[array_rand($surnames)];

            do {
                $email = Str::lower($first . '.' . $last . (mt_rand(0, 2) ? mt_rand(1, 99) : '')) . '@example.com';
            } while (isset($used[$email]));
            $used[$email] = true;

            $daysAgo = $i < 22 ? mt_rand(366, 430) : mt_rand(0, 365);
            $joined = $now->copy()->subDays($daysAgo)->setTime(mt_rand(8, 23), mt_rand(0, 59));
            if ($joined->isFuture()) {
                $joined = $now->copy()->subHours(mt_rand(1, 6));
            }

            $rows[] = $this->row("$first $last", $email, User::ROLE_CUSTOMER, $joined, $password);
        }

        DB::table('users')->insert($rows);
    }

    private function row(string $name, string $email, string $role, $createdAt, string $password): array
    {
        return [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => $password,
            'email_verified_at' => $createdAt,
            'remember_token' => Str::random(10),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }
}
