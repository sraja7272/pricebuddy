<div
    x-data="{
        show: false,
        isSafari: false,
        init() {
            const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
            if (!isIos) return;

            const isStandalone =
                window.navigator.standalone === true ||
                window.matchMedia('(display-mode: standalone)').matches;
            if (isStandalone) return;

            if (localStorage.getItem('pb_ios_install_dismissed')) return;

            this.isSafari = /safari/i.test(navigator.userAgent) &&
                            !/crios|fxios|edgios/i.test(navigator.userAgent);

            this.show = true;
        },
        dismiss() {
            this.show = false;
            localStorage.setItem('pb_ios_install_dismissed', '1');
        }
    }"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
    style="display: none;"
>
    <div
        class="w-full rounded-t-2xl bg-white p-6 shadow-2xl dark:bg-gray-900 sm:max-w-md sm:rounded-xl"
        @click.stop
    >
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0 rounded-full bg-blue-100 p-3 dark:bg-blue-900">
                <x-heroicon-o-arrow-down-on-square class="h-6 w-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div class="flex-1">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Install PriceBuddy for notifications
                </h2>

                <div x-show="isSafari">
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        To receive push notifications on iOS, add PriceBuddy to your Home Screen:
                    </p>
                    <ol class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <li>1. Tap the <strong>Share</strong> button (↑) in the Safari toolbar</li>
                        <li>2. Scroll down and tap <strong>Add to Home Screen</strong></li>
                        <li>3. Tap <strong>Add</strong> — then open from your Home Screen</li>
                    </ol>
                </div>

                <div x-show="!isSafari">
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Push notifications on iOS require PriceBuddy to be installed via Safari:
                    </p>
                    <ol class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        <li>1. <strong>Copy this page's URL</strong></li>
                        <li>2. Open <strong>Safari</strong> and paste the URL</li>
                        <li>3. Tap the <strong>Share</strong> button (↑) in Safari's toolbar</li>
                        <li>4. Tap <strong>Add to Home Screen</strong>, then <strong>Add</strong></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
            <button
                type="button"
                @click="dismiss()"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                Got it
            </button>
        </div>
    </div>
</div>
