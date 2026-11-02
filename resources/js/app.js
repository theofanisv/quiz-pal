/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import { launchConfetti } from './confetti';
import { SOUND_STORE } from './sound-store';

const NEON_COLORS = ['#22e1ff', '#ff3cac', '#b4ff39', '#ffb830', '#9d6bff'];

/**
 * The last countdown second that ticked, keyed by the round's end time: a re-render
 * re-initialises the clock, and this keeps it from repeating a tick.
 */
let lastTick = null;

document.addEventListener('alpine:init', () => {
    window.Alpine.store('sound', SOUND_STORE);

    const sound = () => window.Alpine.store('sound');

    /**
     * Tells the sound store which screen is showing. The join and lobby roots use it; the
     * play and results screens report from their own components.
     */
    window.Alpine.data('gameScreen', (name) => ({
        init() {
            sound().setScreen(name);
        },
    }));

    /**
     * Round clock. Every value comes from server timestamps, so all devices show the
     * question at the same instant and count down together; the server still decides
     * when a round is really over. Ticks in the last five seconds while the round is open.
     */
    window.Alpine.data('roundClock', ({ startsAt, endsAt, serverNow, totalMs }) => ({
        started: false,
        startsIn: 0,
        remaining: 0,
        fired: false,
        interval: null,

        init() {
            sound().setScreen('play');

            const offset = Date.now() - serverNow;

            const tick = () => {
                const now = Date.now() - offset;

                this.startsIn = Math.max(0, Math.ceil((startsAt - now) / 1000));
                this.started = now >= startsAt;
                this.remaining = Math.max(0, endsAt - now);

                if (this.started) {
                    this.cueTick();
                }

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

        /**
         * One tick a second from 5 down to 2 and a higher final tick at 1, only while the
         * round is still open (the reveal renders a sound cue, which is the signal to stop).
         */
        cueTick() {
            const seconds = this.seconds;
            const key = `${endsAt}:${seconds}`;

            if (key === lastTick) {
                return;
            }

            lastTick = key;

            if (this.$root.querySelector('[data-sound-cue]') !== null) {
                return;
            }

            if (seconds >= 2 && seconds <= 5) {
                sound().play('tick');
            } else if (seconds === 1) {
                sound().play('tickFinal');
            }
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
     * server records the answer; the next render re-initialises the pad from the server,
     * and if the server did not accept the answer (the tile comes back idle) the pad
     * unlocks again. `open` is false once the player has answered or the round is revealed.
     */
    window.Alpine.data('answerPad', ({ open }) => ({
        open,
        picked: null,

        async pick(choice) {
            if (!this.open || this.picked !== null) {
                return;
            }

            this.picked = choice;
            sound().play('select');

            // A request that fails (Livewire reports it) or never comes back must not keep the
            // pad locked: the tile is released as soon as the server has not recorded the answer.
            const release = () => {
                if (this.$root.querySelector(`[data-choice="${choice}"]`)?.dataset.state === 'idle') {
                    this.picked = null;
                }
            };
            const watchdog = setTimeout(release, 5000);

            try {
                await this.$wire.answer(choice);
            } catch {
                // Already reported by Livewire.
            } finally {
                clearTimeout(watchdog);
                release();
            }
        },
    }));

    /**
     * The final results screen: a confetti burst, bigger for the winners.
     */
    window.Alpine.data('resultsScreen', ({ winner }) => ({
        init() {
            sound().setScreen('results');

            launchConfetti(this.$refs.confetti, NEON_COLORS, winner ? 220 : 70);
        },
    }));
});
