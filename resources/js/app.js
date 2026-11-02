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

/**
 * The smallest client-minus-server clock difference seen so far. Every render sends the
 * server's time; the time the reply spends travelling and being patched in only ever adds
 * to a sample, so the smallest sample is the closest to the real difference. Keeping it
 * across renders stops the countdown from jumping up after each re-render.
 */
let serverClockOffset = null;

/**
 * The round (by end time) whose time-up was already reported: a re-render re-initialises
 * the clock, and this keeps it from reporting again.
 */
let reportedTimeUp = null;

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
     * `closed` is true once the server has revealed the round.
     */
    window.Alpine.data('roundClock', ({ startsAt, endsAt, serverNow, totalMs, closed }) => ({
        started: false,
        startsIn: 0,
        remaining: 0,
        interval: null,

        init() {
            sound().setScreen('play');

            const sample = Date.now() - serverNow;
            serverClockOffset = serverClockOffset === null ? sample : Math.min(serverClockOffset, sample);

            const tick = () => {
                const now = Date.now() - serverClockOffset;

                this.startsIn = Math.max(0, Math.ceil((startsAt - now) / 1000));
                this.started = now >= startsAt;
                this.remaining = Math.max(0, endsAt - now);

                if (this.started) {
                    this.cueTick();
                }

                if (this.started && this.remaining <= 0) {
                    this.reportTimeUp();
                }
            };

            tick();
            this.interval = setInterval(tick, 100);
        },

        destroy() {
            clearInterval(this.interval);
        },

        /**
         * Tells the server, once per round, that the time ran out. The server only renders when
         * the round is revealed; if it says the round is still open (this clock ran a little
         * ahead, or the call failed) the report is allowed again a second later.
         */
        async reportTimeUp() {
            if (closed || reportedTimeUp === endsAt) {
                return;
            }

            reportedTimeUp = endsAt;

            let isClosed = false;

            try {
                isClosed = (await this.$wire.timeUp()) === true;
            } catch {
                // Already reported by Livewire.
            }

            if (!isClosed) {
                setTimeout(() => {
                    if (reportedTimeUp === endsAt) {
                        reportedTimeUp = null;
                    }
                }, 1000);
            }
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
     * The answer pad. Marks the tapped tile (with its tick) immediately and locks the pad.
     * The server replies with the choice it recorded, without re-rendering while the round
     * is open: the pad keeps that pick, or unlocks when the server rejected the answer. A
     * later render re-initialises the pad from the server. `open` is false once the player
     * has answered or the round is revealed.
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

            // A reply that never comes must not keep the pad locked: it unlocks after a while so
            // the player can try again. A late reply still sets the pick the server recorded.
            let replied = false;
            const watchdog = setTimeout(() => {
                if (!replied) {
                    this.picked = null;
                }
            }, 5000);

            let recorded = null;

            try {
                recorded = await this.$wire.answer(choice);
            } catch {
                // Already reported by Livewire.
            } finally {
                replied = true;
                clearTimeout(watchdog);
            }

            this.picked = Number.isInteger(recorded) ? recorded : null;
        },
    }));

    /**
     * The "who has answered" strip while a round is open. The server renders who had answered
     * at that moment; PlayerAnswered broadcasts (sent to everyone, the answering player too)
     * mark the rest without asking the server to re-render.
     */
    window.Alpine.data('answerStrip', ({ channel, roundId, answered }) => ({
        answered,
        presence: null,
        listener: null,

        init() {
            this.listener = (event) => {
                if (event.round_id === roundId && !this.answered.includes(event.player_id)) {
                    this.answered.push(event.player_id);
                }
            };

            // Livewire has already joined the room's presence channel; join() hands back the same one.
            this.presence = window.Echo?.join(channel) ?? null;
            this.presence?.listen('PlayerAnswered', this.listener);
        },

        destroy() {
            this.presence?.stopListening('PlayerAnswered', this.listener);
        },

        has(playerId) {
            return this.answered.includes(playerId);
        },
    }));

    /**
     * The admin's "next question" button. It stays disabled with a loading spinner until a
     * moment after the reveal (the server sends how long is left), so every player sees the
     * answers before the game moves on. Flux shows its own spinner while the request runs.
     */
    window.Alpine.data('advanceButton', ({ waitMs }) => ({
        waiting: waitMs > 0,
        timer: null,

        init() {
            if (this.waiting) {
                this.timer = setTimeout(() => {
                    this.waiting = false;
                }, waitMs);
            }
        },

        destroy() {
            clearTimeout(this.timer);
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
