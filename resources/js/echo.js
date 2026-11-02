import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// The Reverb settings come from the page (see partials/head.blade.php), not
// from the build, so the same public/build works in every environment.
const reverb = JSON.parse(document.querySelector('meta[name="reverb"]')?.content ?? '{}');

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverb.key,
    wsHost: reverb.host,
    wsPort: reverb.port ?? 80,
    wssPort: reverb.port ?? 443,
    forceTLS: (reverb.scheme ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
