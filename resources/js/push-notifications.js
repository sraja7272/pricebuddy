const PRICEBUDDY_SW_PATH = '/sw.js';
const SUBSCRIBE_URL      = '/push/subscribe';
const UNSUBSCRIBE_URL    = '/push/subscribe';
const CONFIG_URL         = '/push/config';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

async function getRegistration() {
    if (!('serviceWorker' in navigator)) return null;
    try {
        return await navigator.serviceWorker.register(PRICEBUDDY_SW_PATH, { scope: '/' });
    } catch (e) {
        console.warn('[PushNotifications] SW registration failed:', e);
        return null;
    }
}

async function fetchVapidPublicKey() {
    if (window.__VAPID_PUBLIC_KEY__) return window.__VAPID_PUBLIC_KEY__;
    const res = await fetch(CONFIG_URL, { credentials: 'same-origin' });
    const json = await res.json();
    return json.vapidPublicKey;
}

function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

const PushNotifications = {
    isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    },

    getPermission() {
        return Notification.permission;
    },

    async isSubscribed() {
        if (!this.isSupported()) return false;
        const reg = await navigator.serviceWorker.getRegistration(PRICEBUDDY_SW_PATH);
        if (!reg) return false;
        const sub = await reg.pushManager.getSubscription();
        return !!sub;
    },

    async subscribeAndSync() {
        const reg = await getRegistration();
        if (!reg) throw new Error('Service worker registration failed.');

        await navigator.serviceWorker.ready;

        const vapidPublicKey = await fetchVapidPublicKey();

        const subscription = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
        });

        const json = subscription.toJSON();

        const res = await fetch(SUBSCRIBE_URL, {
            method: 'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                endpoint:        json.endpoint,
                keys:            json.keys,
                contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
            }),
        });

        if (!res.ok) throw new Error('Failed to save the subscription on the server.');
    },

    async enable() {
        if (!this.isSupported()) throw new Error('Push not supported on this browser.');

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') throw new Error('Permission denied.');

        await this.subscribeAndSync();
    },

    // iOS has been observed to silently drop an active push subscription
    // (while leaving Notification.permission as 'granted') after the PWA
    // backgrounds/relaunches. Call this on every page load to transparently
    // recreate the subscription when that happens, without re-prompting.
    async ensureSubscribed() {
        if (!this.isSupported()) return;
        if (Notification.permission !== 'granted') return;
        if (await this.isSubscribed()) return;

        try {
            await this.subscribeAndSync();
        } catch (e) {
            console.warn('[PushNotifications] silent re-subscribe failed:', e);
        }
    },

    async disable() {
        if (!this.isSupported()) return;
        const reg = await navigator.serviceWorker.getRegistration(PRICEBUDDY_SW_PATH);
        if (!reg) return;
        const sub = await reg.pushManager.getSubscription();
        if (!sub) return;

        const endpoint = sub.endpoint;

        await sub.unsubscribe();

        await fetch(UNSUBSCRIBE_URL, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ endpoint }),
        });
    },
};

window.PushNotifications = PushNotifications;
export default PushNotifications;

PushNotifications.ensureSubscribed();
