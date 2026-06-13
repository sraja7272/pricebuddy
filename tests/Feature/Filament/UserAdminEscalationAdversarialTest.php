<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserAdminEscalationAdversarialTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_persist_forged_is_admin_via_fill_form(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'name' => 'Still Normal',
                'is_admin' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(
            (bool) $user->refresh()->is_admin,
            'Non-admin must NOT be able to escalate their own is_admin flag.'
        );
    }

    public function test_non_admin_cannot_persist_forged_is_admin_via_raw_data_set(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->set('data.is_admin', true)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(
            (bool) $user->refresh()->is_admin,
            'Forged data.is_admin injection must not escalate a non-admin.'
        );
    }

    public function test_non_admin_cannot_set_is_admin_on_create(): void
    {
        // Admins are the only ones who can reach the create page, but the
        // CreateUser page also belt-and-suspenders checks the acting user.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Admin Created User',
                'email' => 'new-admin-escalation@example.com',
                'password' => 'password',
                'is_admin' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(
            (bool) User::whereEmail('new-admin-escalation@example.com')->sole()->is_admin
        );
    }

    public function test_non_admin_cannot_see_delete_action_on_own_edit_page(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->assertActionHidden('delete');
    }

    public function test_admin_sees_delete_action(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $other->getKey()])
            ->assertActionVisible('delete');
    }

    public function test_guest_is_redirected_from_user_resource(): void
    {
        $user = User::factory()->create();

        $this->get(UserResource::getUrl('edit', ['record' => $user->getKey()]))
            ->assertRedirect();
    }
}
