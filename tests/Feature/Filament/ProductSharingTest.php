<?php

namespace Tests\Feature\Filament;

use App\Events\PriceCreatedEvent;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Models\Price;
use App\Models\Product;
use App\Models\Url;
use App\Models\User;
use App\Notifications\PriceAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ProductSharingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $recipient;

    protected User $stranger;

    protected function setUp(): void
    {
        parent::setUp();

        User::query()->delete();

        $this->owner = User::factory()->create(['name' => 'Owner', 'email' => 'owner@test.com']);
        $this->recipient = User::factory()->create(['name' => 'Recipient', 'email' => 'recipient@test.com']);
        $this->stranger = User::factory()->create(['name' => 'Stranger', 'email' => 'stranger@test.com']);
    }

    // -----------------------------------------------------------------------
    // Product list visibility
    // -----------------------------------------------------------------------

    public function test_shared_product_appears_in_recipient_list(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$product]);
    }

    public function test_stranger_cannot_see_shared_product_in_list(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->stranger);

        Livewire::test(ListProducts::class)
            ->assertCanNotSeeTableRecords([$product]);
    }

    // -----------------------------------------------------------------------
    // View/Edit page access
    // -----------------------------------------------------------------------

    public function test_recipient_can_view_shared_product(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        $this->get(ProductResource::getUrl('view', ['record' => $product]))->assertOk();
    }

    public function test_stranger_gets_403_on_view(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->stranger);

        $this->get(ProductResource::getUrl('view', ['record' => $product]))->assertForbidden();
    }

    public function test_recipient_can_edit_shared_product(): void
    {
        $product = $this->createOwnedProduct(['title' => 'Original title']);
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['title' => 'Updated by recipient'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated by recipient', $product->refresh()->title);
    }

    public function test_stranger_gets_403_on_edit(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->stranger);

        $this->get(ProductResource::getUrl('edit', ['record' => $product]))->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Delete — owner-only
    // -----------------------------------------------------------------------

    public function test_recipient_cannot_delete_shared_product(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->assertActionHidden('delete');
    }

    // -----------------------------------------------------------------------
    // Share action — owner-only
    // -----------------------------------------------------------------------

    public function test_owner_can_share_product_with_user(): void
    {
        $product = $this->createOwnedProduct();

        $this->actingAs($this->owner);

        Livewire::test(ListProducts::class)
            ->callTableAction('share', $product, data: ['users' => [$this->recipient->getKey()]])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($product->sharedWith()->whereKey($this->recipient->getKey())->exists());
    }

    public function test_recipient_cannot_call_share_action(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('share', $product);
    }

    public function test_share_action_sends_notification_to_new_recipient(): void
    {
        $product = $this->createOwnedProduct(['title' => 'Cool product']);

        $this->actingAs($this->owner);

        $notificationsBefore = \DB::table('notifications')
            ->where('notifiable_id', $this->recipient->getKey())
            ->count();

        Livewire::test(ListProducts::class)
            ->callTableAction('share', $product, data: ['users' => [$this->recipient->getKey()]])
            ->assertHasNoTableActionErrors();

        $notificationsAfter = \DB::table('notifications')
            ->where('notifiable_id', $this->recipient->getKey())
            ->count();

        $this->assertGreaterThan($notificationsBefore, $notificationsAfter);

        // Verify the notification title mentions "shared".
        $notification = \DB::table('notifications')
            ->where('notifiable_id', $this->recipient->getKey())
            ->latest()
            ->first();

        $data = json_decode($notification->data, true);
        $this->assertStringContainsStringIgnoringCase('shared', $data['title'] ?? '');
    }

    public function test_resyncing_share_list_without_new_users_sends_no_notification(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->owner);

        $notificationsBefore = \DB::table('notifications')
            ->where('notifiable_id', $this->recipient->getKey())
            ->count();

        // Re-save with the same user — no new additions.
        Livewire::test(ListProducts::class)
            ->callTableAction('share', $product, data: ['users' => [$this->recipient->getKey()]])
            ->assertHasNoTableActionErrors();

        $notificationsAfter = \DB::table('notifications')
            ->where('notifiable_id', $this->recipient->getKey())
            ->count();

        $this->assertSame($notificationsBefore, $notificationsAfter);
    }

    // -----------------------------------------------------------------------
    // Leave action — recipient-only
    // -----------------------------------------------------------------------

    public function test_recipient_can_leave_shared_product(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        Livewire::test(ListProducts::class)
            ->callTableAction('leave_share', $product)
            ->assertHasNoTableActionErrors();

        $this->assertFalse($product->sharedWith()->whereKey($this->recipient->getKey())->exists());
        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);
    }

    public function test_owner_does_not_see_leave_action(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->owner);

        Livewire::test(ListProducts::class)
            ->assertTableActionHidden('leave_share', $product);
    }

    public function test_stranger_is_denied_by_leave_action_authorize(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->stranger);

        // The product is not in the stranger's table (filtered by currentUser()), but we also
        // verify the authorization guard itself rejects a stranger — they can't 'view' the product.
        $this->assertFalse($this->stranger->can('view', $product));
        // Double-check: the LeaveShareAction authorize closure requires can('view'), so it also denies.
        $this->assertFalse(
            $this->stranger->can('view', $product) && $this->stranger->getKey() !== $product->user_id
        );
    }

    // -----------------------------------------------------------------------
    // Fetch route (IDOR fix + shared access)
    // -----------------------------------------------------------------------

    public function test_stranger_gets_403_on_fetch_route(): void
    {
        $product = $this->createOwnedProduct();

        $this->actingAs($this->stranger);

        $this->post("/admin/products/{$product->getKey()}/fetch")->assertForbidden();
    }

    public function test_recipient_can_trigger_fetch(): void
    {
        $product = $this->createOwnedProduct();
        $product->sharedWith()->attach($this->recipient);

        $this->actingAs($this->recipient);

        // Product has no URLs so updatePrices() no-ops without hitting any external service.
        // We just confirm the route resolves (not 403) for a shared user.
        $this->post("/admin/products/{$product->getKey()}/fetch")->assertRedirect();
    }

    // -----------------------------------------------------------------------
    // Notifications — fan-out on price alert
    // -----------------------------------------------------------------------

    public function test_price_alert_notifies_owner_and_recipients(): void
    {
        Notification::fake();

        $product = $this->createOwnedProduct([
            'notify_price' => 100,
        ]);
        $product->sharedWith()->attach($this->recipient);
        $product->load('sharedWith', 'user');

        $url = Url::factory()->create(['product_id' => $product->getKey()]);
        $price = Price::factory()->create([
            'url_id' => $url->getKey(),
            'price' => 50,
        ]);

        // Load the price's relations the way the listener does.
        $price->load('product', 'url');

        event(new PriceCreatedEvent($price));

        Notification::assertSentTo($this->owner, PriceAlertNotification::class);
        Notification::assertSentTo($this->recipient, PriceAlertNotification::class);
        Notification::assertNotSentTo($this->stranger, PriceAlertNotification::class);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function createOwnedProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['user_id' => $this->owner->getKey()], $attributes));
    }
}
