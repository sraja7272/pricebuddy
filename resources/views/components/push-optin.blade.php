<div
    x-data="{
        show: false,
        loading: false,
        init() {
            if (!window.PushNotifications || !window.PushNotifications.isSupported()) return;
            if (Notification.permission !== 'default') return;
            if (localStorage.getItem('pb_push_dismissed')) return;
            this.show = true;
        },
        dismiss() {
            this.show = false;
            localStorage.setItem('pb_push_dismissed', '1');
        },
        async enable() {
            this.loading = true;
            try {
                await window.PushNotifications.enable();
                localStorage.setItem('pb_push_dismissed', '1');
                this.show = false;
            } catch (e) {
                this.dismiss();
            } finally {
                this.loading = false;
            }
        }
    }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
    style="display: none;"
>
    <div
        class="mx-4 w-full max-w-md rounded-xl bg-white p-6 shadow-2xl dark:bg-gray-900"
        @click.stop
    >
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 rounded-full bg-primary-100 p-3 dark:bg-primary-900">
                <x-heroicon-o-bell class="h-6 w-6 text-primary-600 dark:text-primary-400" />
            </div>
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Stay updated on price drops
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Get instant push notifications when a product you're tracking drops in price or comes back in stock — right on this device.
                </p>
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <button
                type="button"
                @click="dismiss()"
                class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Not now
            </button>
            <button
                type="button"
                @click="enable()"
                :disabled="loading"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700 disabled:opacity-60"
            >
                <span x-show="!loading">Enable notifications</span>
                <span x-show="loading">Enabling…</span>
            </button>
        </div>
    </div>
</div>
