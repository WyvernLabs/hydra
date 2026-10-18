<?php

namespace Pterodactyl\Providers;

use Illuminate\Support\ServiceProvider;

class BladeServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->app->make('blade.compiler')
            // Hydra: localized date and time (e.g. "18. Okt. 2026 21:00" / "Oct 18, 2026 9:00 PM").
            ->directive('datetimeHuman', function ($expression) {
                return "<?php echo \Carbon\CarbonImmutable::createFromFormat(\Carbon\CarbonImmutable::DEFAULT_TO_STRING_FORMAT, $expression)->setTimezone(config('app.timezone'))->locale(app()->getLocale())->isoFormat('lll'); ?>";
            });
    }
}
