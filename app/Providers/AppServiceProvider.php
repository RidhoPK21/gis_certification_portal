<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
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
    }
}
