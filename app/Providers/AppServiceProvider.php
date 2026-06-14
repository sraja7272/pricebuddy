<?php

namespace App\Providers;

use App\Enums\NotificationMethods;
use App\Enums\Role;
use App\Models\Product;
use App\Models\ProductSource;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Policies\ProductSourcePolicy;
use App\Policies\UserPolicy;
use App\Services\Helpers\NotificationsHelper;
use App\Services\Helpers\QueueHelper;
use App\Services\Helpers\SettingsHelper;
use DutchCodingCompany\FilamentSocialite\Events\Login as SocialiteLogin;
use DutchCodingCompany\FilamentSocialite\Events\Registered as SocialiteRegistered;
use Filament\Facades\Filament;
use Filament\Navigation\MenuItem;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (filled(getenv('TRUSTED_PROXIES')) || getenv('FORCE_HTTPS') || str_starts_with(config('app.url', ''), 'https://')) {
            URL::forceHttps();
        }

        $this->registerPolicies();
        $this->registerFilamentSettings();
        $this->setConfigFromAppSettings();
        $this->registerAbout();
        $this->authorizePackages();
        $this->registerOidcAuth();
        $this->registerOidcAdminGroupSync();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(ProductSource::class, ProductSourcePolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    protected function registerFilamentSettings(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render(
                '@vite([\'resources/scss/app.scss\', \'resources/js/app.js\'])'.
                '@include(\'body.js-settings\')'.
                '<link rel="manifest" href="/manifest.json">'.
                '<script src="/js/clipboard.js"></script>'
            ),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SIDEBAR_FOOTER,
            fn (): string => view('components.sidebar-footer', [
                'content' => config('app.version', 'development'),
            ]),
        );

        Filament::registerUserMenuItems([
            MenuItem::make()
                ->label(__('Account settings'))
                ->url(fn () => '/admin/users/'.auth()->id().'/edit')
                ->icon('heroicon-s-cog'),
        ]);
    }

    protected function setConfigFromAppSettings(): void
    {
        // Email.
        $mail = NotificationMethods::Mail->value;
        if (NotificationsHelper::isEnabled($mail)) {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => NotificationsHelper::getSetting($mail, 'smtp_host'),
                'mail.mailers.smtp.port' => NotificationsHelper::getSetting($mail, 'smtp_port'),
                'mail.mailers.smtp.username' => NotificationsHelper::getSetting($mail, 'smtp_user'),
                'mail.mailers.smtp.password' => NotificationsHelper::getSetting($mail, 'smtp_password'),
                'mail.mailers.smtp.encryption' => NotificationsHelper::getSetting($mail, 'encryption') ?: null,
                'mail.from.address' => NotificationsHelper::getSetting($mail, 'from_address', 'hello@example.com'),
            ]);
        }

        // Pushover.
        $pushover = NotificationMethods::Pushover->value;
        if (NotificationsHelper::isEnabled($pushover)) {
            config([
                'services.pushover.token' => NotificationsHelper::getSetting($pushover, 'token'),
            ]);
        }

        // Logging.
        config([
            'logging.channels.db.days' => SettingsHelper::getSetting('log_retention_days', 7),
        ]);
    }

    protected function registerAbout(): void
    {
        AboutCommand::add('PriceBuddy', fn () => [
            'Version' => config('app.version', 'development'),
            'Queue Worker' => QueueHelper::isRunning() ? 'Running' : 'Stopped',
        ]);
    }

    protected function authorizePackages(): void
    {
        Gate::define('viewApiDocs', function (?User $user) {
            return ! is_null($user);
        });
    }

    private function registerOidcAuth(): void
    {
        if (empty(config('services.oidc.client_id'))) {
            return;
        }

        $missing = array_values(array_filter([
            empty(config('services.oidc.base_url')) ? 'OIDC_BASE_URL' : null,
            empty(config('services.oidc.client_secret')) ? 'OIDC_CLIENT_SECRET' : null,
        ]));

        if ($missing) {
            Log::warning(
                'OIDC is enabled but required configuration is missing: '.implode(', ', $missing).'. OIDC login will not function.'
            );

            return;
        }

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('oidc', \SocialiteProviders\OIDC\Provider::class);
        });
    }

    private function registerOidcAdminGroupSync(): void
    {
        if (empty(config('services.oidc.client_id'))) {
            return;
        }

        $syncAdmin = function ($event) {
            $adminGroup = config('services.oidc.admin_group');
            $groupsClaim = config('services.oidc.groups_claim', 'groups');
            $user = $event->socialiteUser->getUser();

            $username = data_get($event->oauthUser->user, 'preferred_username', $event->oauthUser->getId());
            $groups = (array) data_get($event->oauthUser->user, $groupsClaim, []);

            Log::info('OIDC: login', [
                'username' => $username,
                'groups' => $groups,
            ]);

            if (! filled($adminGroup)) {
                return;
            }

            // Never demote the bootstrap admin to prevent lock-out
            // @phpstan-ignore-next-line
            $isAdmin = in_array($adminGroup, $groups, true) || $user->email === env('APP_USER_EMAIL');
            $user->forceFill(['role' => $isAdmin ? Role::Admin : Role::User])->save();

            Log::info('OIDC: admin sync', [
                'username' => $username,
                'admin_group' => $adminGroup,
                'granted' => $isAdmin,
            ]);
        };

        Event::listen(SocialiteLogin::class, $syncAdmin);
        Event::listen(SocialiteRegistered::class, $syncAdmin);
    }
}
