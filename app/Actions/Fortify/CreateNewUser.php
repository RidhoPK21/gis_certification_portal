<?php

namespace App\Actions\Fortify;

use App\Models\Role;
use App\Models\User;
use App\Services\TurnstileService;
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
        /*
         * Turnstile diperiksa sebelum validasi lain agar bot tidak dapat
         * memakai form registrasi untuk membuat akun massal.
         */
        app(TurnstileService::class)->ensureValid(request());

        $input['name'] = trim(
            (string) ($input['name'] ?? '')
        );

        $input['email'] = Str::lower(
            trim((string) ($input['email'] ?? ''))
        );

        $input['company_name'] = trim(
            (string) ($input['company_name'] ?? '')
        );

        Validator::make(
            $input,
            [
                'name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'company_name' => [
                    'required',
                    'string',
                    'max:200',
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

                'company_name.required' =>
                    'Nama perusahaan/instansi wajib diisi.',

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
                    'company_name' => $input['company_name'],
                    'password' => Hash::make(
                        $input['password']
                    ),
                    'is_active' => true,
                ]);

                /*
                 * email_verified_at sengaja dibiarkan pada nilai
                 * default null sampai kode OTP yang dikirim ke
                 * email pendaftar berhasil diverifikasi.
                 */

                $user->roles()->attach(
                    $clientRole->id
                );

                return $user;
            }
        );
    }
}