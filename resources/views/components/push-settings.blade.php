<div
    x-data="{
        supported: false,
        subscribed: false,
        permission: 'default',
        loading: false,
        error: '',
        async init() {
            if (!window.PushNotifications || !window.PushNotifications.isSupported()) {
                this.supported = false;
                return;
            }
            this.supported = true;
            this.permission = window.PushNotifications.getPermission();
            this.subscribed = await window.PushNotifications.isSubscribed();
        },
        async toggle() {
            this.loading = true;
            this.error = '';
            try {
                if (this.subscribed) {
                    await window.PushNotifications.disable();
                    this.subscribed = false;
                    this.permission = window.PushNotifications.getPermission();
                } else {
                    await window.PushNotifications.enable();
                    this.subscribed = true;
                    this.permission = 'granted';
                }
            } catch (e) {
                // Whatever the failure (permission denied, subscribe error,
                // server rejection), the device must not appear enabled.
                this.subscribed = false;
                this.permission = window.PushNotifications.getPermission();
                this.error = this.permission === 'denied'
                    ? ''
                    : 'Could not enable push notifications. Please try again.';
            } finally {
                this.loading = false;
            }
        }
    }"
    class="mt-2"
>
    <div x-show="!supported" class="text-sm text-gray-500 dark:text-gray-400">
        Push notifications are not supported on this browser or device.
        On iOS, the app must be installed from Safari via "Add to Home Screen" (iOS 16.4+).
    </div>

    <div x-show="supported" class="flex items-center gap-4">
        <div x-show="permission === 'denied'" class="text-sm text-warning-600 dark:text-warning-400">
            Notifications are blocked in your browser settings. To re-enable, go to your browser's
            site settings and allow notifications for this site.
        </div>

        <div x-show="permission !== 'denied'" class="flex items-center gap-3">
            <button
                type="button"
                @click="toggle()"
                :disabled="loading"
                :class="subscribed
                    ? 'bg-primary-600 hover:bg-primary-700'
                    : 'bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600'"
                class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none disabled:opacity-50"
                role="switch"
                :aria-checked="subscribed"
            >
                <span
                    :class="subscribed ? 'translate-x-5' : 'translate-x-0'"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                ></span>
            </button>
            <span class="text-sm text-gray-700 dark:text-gray-300">
                <span x-show="loading">Updating…</span>
                <span x-show="!loading && subscribed">Push notifications enabled on this device</span>
                <span x-show="!loading && !subscribed">Enable push notifications on this device</span>
            </span>
        </div>

        <p x-show="error" x-text="error" class="mt-2 text-sm text-danger-600 dark:text-danger-400"></p>
    </div>
</div>
