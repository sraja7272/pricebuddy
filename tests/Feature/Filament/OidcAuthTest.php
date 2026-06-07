<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AppSettingsPage;
use App\Filament\Pages\Login;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OidcAuthTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        User::query()->delete();

        $this->adminUser = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
        ]);

        $this->regularUser = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@test.com',
            'password' => Hash::make('password'),
        ]);
    }

    // -------------------------------------------------------------------------
    // Admin authorization
    // -------------------------------------------------------------------------

    public function test_admin_can_access_user_resource_index(): void
    {
        $this->actingAs($this->adminUser);

        $this->get(UserResource::getUrl('index'))->assertOk();
    }

    public function test_non_admin_cannot_access_user_resource_index(): void
    {
        $this->actingAs($this->regularUser);

        $this->get(UserResource::getUrl('index'))->assertForbidden();
    }

    public function test_non_admin_cannot_create_users(): void
    {
        $this->actingAs($this->regularUser);

        $this->get(UserResource::getUrl('create'))->assertForbidden();
    }

    public function test_non_admin_can_edit_own_profile(): void
    {
        $this->actingAs($this->regularUser);

        $this->get(UserResource::getUrl('edit', ['record' => $this->regularUser->getKey()]))->assertOk();
    }

    public function test_non_admin_cannot_edit_other_users(): void
    {
        $this->actingAs($this->regularUser);

        $this->get(UserResource::getUrl('edit', ['record' => $this->adminUser->getKey()]))->assertForbidden();
    }

    public function test_admin_can_access_app_settings_page(): void
    {
        $this->actingAs($this->adminUser);

        $this->get(AppSettingsPage::getUrl())->assertOk();
    }

    public function test_non_admin_cannot_access_app_settings_page(): void
    {
        $this->actingAs($this->regularUser);

        $this->assertFalse(AppSettingsPage::canAccess());
    }

    // -------------------------------------------------------------------------
    // is_admin cannot be set by a non-admin via the form
    // -------------------------------------------------------------------------

    public function test_non_admin_cannot_elevate_own_is_admin_via_edit_form(): void
    {
        $this->actingAs($this->regularUser);

        $params = ['record' => $this->regularUser->getKey()];

        Livewire::test(EditUser::class, $params)
            ->fillForm([
                'name' => $this->regularUser->name,
                'email' => $this->regularUser->email,
                'is_admin' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->regularUser->refresh();

        $this->assertFalse($this->regularUser->is_admin, 'Non-admin should not be able to set is_admin via the form');
    }

    // -------------------------------------------------------------------------
    // DISABLE_LOCAL_LOGIN
    // -------------------------------------------------------------------------

    public function test_authenticate_is_denied_when_local_login_disabled(): void
    {
        $_ENV['DISABLE_LOCAL_LOGIN'] = 'true';

        try {
            Livewire::test(Login::class)
                ->fillForm([
                    'email' => $this->adminUser->email,
                    'password' => 'password',
                ])
                ->call('authenticate')
                ->assertHasFormErrors();
        } finally {
            unset($_ENV['DISABLE_LOCAL_LOGIN']);
        }
    }

    public function test_local_login_works_when_not_disabled(): void
    {
        Livewire::test(Login::class)
            ->fillForm([
                'email' => $this->adminUser->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect(route('filament.admin.pages.home-dashboard'));
    }
}
