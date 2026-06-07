<?php

namespace App\Providers;

use App\Enums\NotificationMethods;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
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

        Log::info('OIDC: driver registered', [
            'base_url'    => config('services.oidc.base_url'),
            'scopes'      => config('services.oidc.scopes'),
            'admin_group' => config('services.oidc.admin_group') ?: '(not set)',
        ]);

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

            // The ID token JWT often omits non-standard claims like groups — providers
            // frequently put them only on the userinfo endpoint. Fetch userinfo explicitly
            // so we always see the full profile regardless of what the IdP puts in the JWT.
            $userInfo = $this->fetchOidcUserInfo($event->oauthUser->token);
            $rawData = $userInfo ?? $event->oauthUser->user;

            Log::debug('OIDC: admin sync triggered', [
                'user_email'             => $user->email,
                'configured_admin_group' => $adminGroup ?: '(not set)',
                'groups_claim'           => $groupsClaim,
                'raw_oidc_payload'       => $rawData,
                'source'                 => $userInfo ? 'userinfo_endpoint' : 'id_token',
            ]);

            if (! filled($adminGroup)) {
                Log::debug('OIDC: OIDC_ADMIN_GROUP not configured, skipping group sync');

                return;
            }

            $groups = (array) data_get($rawData, $groupsClaim, []);

            Log::debug('OIDC: group membership check', [
                'user_email'         => $user->email,
                'found_groups'       => $groups,
                'looking_for'        => $adminGroup,
                'group_match'        => in_array($adminGroup, $groups, true),
                'is_bootstrap_admin' => $user->email === env('APP_USER_EMAIL'),
            ]);

            // Never demote the bootstrap admin to prevent lock-out
            $isAdmin = in_array($adminGroup, $groups, true) || $user->email === env('APP_USER_EMAIL');
            $user->forceFill(['is_admin' => $isAdmin])->save();

            Log::info('OIDC: admin status set', [
                'user_email' => $user->email,
                'is_admin'   => $isAdmin,
            ]);
        };

        Event::listen(SocialiteLogin::class, $syncAdmin);
        Event::listen(SocialiteRegistered::class, $syncAdmin);
    }

    private function fetchOidcUserInfo(string $accessToken): ?array
    {
        try {
            $baseUrl = rtrim(config('services.oidc.base_url'), '/');

            $userinfoUrl = Cache::remember(
                'oidc_userinfo_url_'.md5($baseUrl),
                3600,
                function () use ($baseUrl) {
                    $discovery = Http::get($baseUrl.'/.well-known/openid-configuration')->json();

                    return $discovery['userinfo_endpoint'] ?? null;
                }
            );

            if (! $userinfoUrl) {
                Log::warning('OIDC: userinfo_endpoint not found in discovery document');

                return null;
            }

            return Http::withToken($accessToken)->get($userinfoUrl)->json();
        } catch (\Exception $e) {
            Log::warning('OIDC: failed to fetch userinfo endpoint', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
