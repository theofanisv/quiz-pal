/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/**
 * Question countdown. Driven by server timestamps so every device agrees on
 * when the round ends; the server still decides when it is really over.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('countdown', ({ endsAt, serverNow, totalMs }) => ({
        remaining: 0,
        fired: false,
        interval: null,

        init() {
            const offset = Date.now() - serverNow;

            const tick = () => {
                this.remaining = Math.max(0, endsAt - (Date.now() - offset));

                if (this.remaining <= 0 && !this.fired) {
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
