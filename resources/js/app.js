/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/**
 * Round clock. Every value comes from server timestamps, so all devices show the
 * question at the same instant and count down together; the server still decides
 * when a round is really over.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('roundClock', ({ startsAt, endsAt, serverNow, totalMs }) => ({
        started: false,
        startsIn: 0,
        remaining: 0,
        fired: false,
        interval: null,

        init() {
            const offset = Date.now() - serverNow;

            const tick = () => {
                const now = Date.now() - offset;

                this.startsIn = Math.max(0, Math.ceil((startsAt - now) / 1000));
                this.started = now >= startsAt;
                this.remaining = Math.max(0, endsAt - now);

                if (this.started && this.remaining <= 0 && !this.fired) {
                    this.fired = true;
                    clearInterval(this.interval);
                    this.$wire.timeUp();
                }
            };

            tick();
            this.interval = setInterval(tick, 100);
        },

        destroy() {
            clearInterval(this.interval);
        },

        get seconds() {
            return Math.ceil(this.remaining / 1000);
        },

        get percent() {
            return totalMs > 0 ? Math.round((this.remaining / totalMs) * 100) : 0;
        },
    }));
});
