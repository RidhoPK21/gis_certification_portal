<?php

namespace App\Actions\Fortify;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use RuntimeException;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Membuat akun Klien dari registrasi publik.
     */
    public function create(array $input): User
    {
        $input['name'] = trim(
            (string) ($input['name'] ?? '')
        );

        $input['email'] = Str::lower(
            trim((string) ($input['email'] ?? ''))
        );

        Validator::make(
            $input,
            [
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    'unique:users,email',
                ],

                'password' => $this->passwordRules(),
            ],
            [
                'name.required' =>
                    'Nama lengkap wajib diisi.',

                'email.required' =>
                    'Alamat email wajib diisi.',

                'email.email' =>
                    'Format alamat email tidak valid.',

                'email.unique' =>
                    'Alamat email sudah digunakan.',

                'password.required' =>
                    'Kata sandi wajib diisi.',

                'password.confirmed' =>
                    'Konfirmasi kata sandi tidak sesuai.',
            ]
        )->validate();

        return DB::transaction(
            function () use ($input): User {
                $clientRole = Role::query()
                    ->where('code', 'client')
                    ->where('is_active', true)
                    ->first();

                if (! $clientRole) {
                    throw new RuntimeException(
                        'Role client belum tersedia. Jalankan RolePermissionSeeder.'
                    );
                }

                $user = User::query()->create([
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'password' => Hash::make(
                        $input['password']
                    ),
                    'is_active' => true,

                    /*
                     * Nantinya tetap null sampai
                     * verifikasi email dilakukan.
                     */
                    'email_verified_at' => null,
                ]);

                $user->roles()->attach(
                    $clientRole->id
                );

                return $user;
            }
        );
    }
}