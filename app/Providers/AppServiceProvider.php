<?php

namespace App\Providers;

use App\Services\SettingService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');

        Blade::directive(
            'statuslabel',
            fn ($expression) => "<?php echo e(\\App\\Enums\\ApplicationStatus::labelFor({$expression})); ?>"
        );

        /*
         * Identitas portal (logo, nama, footer) dibaca dari Pengaturan Sistem.
         * Hanya untuk layout, agar tidak membebani view lain.
         */
        View::composer(['layouts.app', 'layouts.auth'], function ($view): void {
            $settings = app(SettingService::class);

            $view->with([
                'branding' => $settings->all(),
                'brandLogoUrl' => $settings->imageUrl('logo_path'),
                'brandLoginLogoUrl' => $settings->imageUrl('login_logo_path'),
                'brandFaviconUrl' => $settings->imageUrl('favicon_path'),
            ]);
        });
    }
}
