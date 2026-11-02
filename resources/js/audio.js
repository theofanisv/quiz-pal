/**
 * The game's sound effects, synthesised with the Web Audio API so there is nothing to
 * download. The AudioContext is created lazily and can only start running inside a user
 * gesture, which is what `unlock()` is for; a cue on a context that is not running is
 * silently dropped.
 */
export function createSynth() {
    let context = null;
    let master = null;

    const ensureContext = () => {
        if (context === null) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;

            if (!AudioContextClass) {
                return null;
            }

            context = new AudioContextClass();
            master = context.createGain();
            master.gain.value = 0.25;
            master.connect(context.destination);
        }

        return context;
    };

    /**
     * One enveloped oscillator note. `at` delays the note (seconds), `glideTo` ramps the
     * pitch over the note, `lowpass` sends it through a lowpass filter at that frequency.
     */
    const note = ({ type = 'sine', frequency, glideTo = null, duration, at = 0, gain = 1, lowpass = null }) => {
        const ctx = ensureContext();

        if (ctx === null || ctx.state !== 'running') {
            return;
        }

        const start = ctx.currentTime + at;
        const oscillator = ctx.createOscillator();
        const envelope = ctx.createGain();

        oscillator.type = type;
        oscillator.frequency.setValueAtTime(frequency, start);

        if (glideTo !== null) {
            oscillator.frequency.exponentialRampToValueAtTime(glideTo, start + duration);
        }

        envelope.gain.setValueAtTime(0.0001, start);
        envelope.gain.exponentialRampToValueAtTime(gain, start + 0.01);
        envelope.gain.exponentialRampToValueAtTime(0.0001, start + duration);

        let output = oscillator;

        if (lowpass !== null) {
            const filter = ctx.createBiquadFilter();
            filter.type = 'lowpass';
            filter.frequency.value = lowpass;
            oscillator.connect(filter);
            output = filter;
        }

        output.connect(envelope);
        envelope.connect(master);
        oscillator.start(start);
        oscillator.stop(start + duration + 0.02);
    };

    return {
        /** Resumes the context; returns whether it is running. Call inside a user gesture. */
        async unlock() {
            const ctx = ensureContext();

            if (ctx !== null && ctx.state === 'suspended') {
                await ctx.resume().catch(() => {});
            }

            return ctx?.state === 'running';
        },

        click() {
            note({ type: 'sine', frequency: 1200, duration: 0.04, gain: 0.6 });
        },

        select() {
            note({ type: 'triangle', frequency: 660, glideTo: 880, duration: 0.09 });
        },

        correct() {
            [523, 659, 784].forEach((frequency, index) => note({ type: 'triangle', frequency, duration: 0.11, at: index * 0.11 }));
        },

        wrong() {
            note({ type: 'sawtooth', frequency: 220, glideTo: 110, duration: 0.35, lowpass: 600 });
        },

        tick(final = false) {
            note({ type: 'sine', frequency: final ? 1500 : 1000, duration: final ? 0.12 : 0.05, gain: final ? 0.8 : 0.5 });
        },
    };
}

/**
 * The background music loop as an HTMLAudioElement owned by JavaScript, so Livewire never
 * morphs it away. Nothing is fetched until the first `play()`.
 */
export function createMusic(src) {
    let audio = null;

    const ensureAudio = () => {
        if (audio === null) {
            audio = new Audio(src);
            audio.loop = true;
            audio.volume = 0.35;
            audio.preload = 'none';
        }

        return audio;
    };

    return {
        get playing() {
            return audio !== null && !audio.paused;
        },

        async play() {
            const element = ensureAudio();

            if (element.paused) {
                await element.play().catch(() => {});
            }
        },

        pause() {
            audio?.pause();
        },
    };
}
