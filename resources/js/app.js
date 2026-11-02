/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import { launchConfetti } from './confetti';

const NEON_COLORS = ['#22e1ff', '#ff3cac', '#b4ff39', '#ffb830', '#9d6bff'];

document.addEventListener('alpine:init', () => {
    /**
     * Round clock. Every value comes from server timestamps, so all devices show the
     * question at the same instant and count down together; the server still decides
     * when a round is really over.
     */
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

    /**
     * The answer pad. Highlights the tapped tile immediately and locks the pad while the
     * server records the answer; if the server did not accept it (the tile comes back
     * idle), the pad unlocks again. `open` is false once the player has answered or the
     * round is revealed.
     */
    window.Alpine.data('answerPad', ({ open }) => ({
        open,
        picked: null,

        async pick(choice) {
            if (!this.open || this.picked !== null) {
                return;
            }

            this.picked = choice;
            window.Alpine.store('sound')?.play?.('select');

            await this.$wire.answer(choice);

            const tile = this.$root.querySelector(`[data-choice="${choice}"]`);

            if (tile?.dataset.state === 'idle') {
                this.picked = null;
            }
        },
    }));

    /**
     * The final results screen: a confetti burst, bigger for the winners.
     */
    window.Alpine.data('resultsScreen', ({ winner }) => ({
        init() {
            window.Alpine.store('sound')?.setScreen?.('results');

            launchConfetti(this.$refs.confetti, NEON_COLORS, winner ? 220 : 70);
        },
    }));
});
