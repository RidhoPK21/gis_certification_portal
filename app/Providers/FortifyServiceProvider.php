<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use App\Http\Responses\RegisterResponse as SystemGISRegisterResponse;
use Laravel\Fortify\Contracts\RegisterResponse as FortifyRegisterResponse;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
{
    $this->app->singleton(
        FortifyRegisterResponse::class,
        SystemGISRegisterResponse::class
    );
}

    public function boot(): void
    {
        /*
         * Action bawaan Fortify.
         */
        Fortify::createUsersUsing(
            CreateNewUser::class
        );

        Fortify::updateUserProfileInformationUsing(
            UpdateUserProfileInformation::class
        );

        Fortify::updateUserPasswordsUsing(
            UpdateUserPassword::class
        );

        Fortify::resetUserPasswordsUsing(
            ResetUserPassword::class
        );

        /*
         * Tampilan autentikasi.
         */
        Fortify::loginView(function () {
            return view('auth.login');
        });

        Fortify::registerView(function () {
            return view('auth.register');
        });

        /*
         * Login hanya untuk akun aktif.
         */
        Fortify::authenticateUsing(
            function (Request $request): ?User {
                $email = Str::lower(
                    trim(
                        (string) $request->input('email')
                    )
                );

                $user = User::query()
                    ->whereRaw(
                        'LOWER(email) = ?',
                        [$email]
                    )
                    ->first();

                if (
                    ! $user ||
                    ! $user->is_active ||
                    ! Hash::check(
                        (string) $request->input('password'),
                        $user->password
                    )
                ) {
                    return null;
                }

                $user->forceFill([
                    'last_login_at' => now(),
                ])->save();

                return $user;
            }
        );

        /*
         * Maksimal lima percobaan login per menit
         * untuk kombinasi email dan alamat IP.
         */
        RateLimiter::for(
            'login',
            function (Request $request): Limit {
                $email = Str::lower(
                    trim(
                        (string) $request->input('email')
                    )
                );

                return Limit::perMinute(5)->by(
                    $email . '|' . $request->ip()
                );
            }
        );
    }
}