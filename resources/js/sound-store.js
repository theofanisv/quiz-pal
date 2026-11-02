import { createMusic, createSynth } from './audio';

const MUSIC_KEY = 'quizpal.sound.music';
const SFX_KEY = 'quizpal.sound.sfx';
const MUSIC_SCREENS = ['play', 'results'];

/**
 * Browsers disagree on which touch event counts as the user gesture that may start audio
 * (pointerdown only for a mouse, pointerup or touchend for a finger, click everywhere), so
 * the unlock is retried on all of them until the context runs.
 */
const UNLOCK_EVENTS = ['pointerdown', 'pointerup', 'touchend', 'click', 'keydown'];

let detachUnlockListeners = null;

const readSetting = (key, fallback) => {
    try {
        return localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
};

const writeSetting = (key, value) => {
    try {
        localStorage.setItem(key, value);
    } catch {
        // Private mode or storage disabled: the setting lasts for this page only.
    }
};

const insideUserGesture = () => navigator.userActivation?.isActive ?? false;

/**
 * The Alpine `sound` store: the music and sound-effect toggles (remembered per device),
 * whether a user gesture has unlocked audio yet, and which screen is showing, which is
 * what decides whether the music plays. Cues are keyed to rendered DOM state, so every
 * client hears the same thing without extra broadcasting.
 */
export const SOUND_STORE = {
    music: false,
    sfx: true,
    unlocked: false,
    screen: 'idle',
    synth: null,
    track: null,

    init() {
        this.music = readSetting(MUSIC_KEY, '0') === '1';
        this.sfx = readSetting(SFX_KEY, '1') === '1';
        this.synth = createSynth();
        this.track = createMusic('/sounds/music.mp3');

        const attempt = () => {
            this.unlock();
        };

        UNLOCK_EVENTS.forEach((event) => window.addEventListener(event, attempt, { capture: true, passive: true }));
        detachUnlockListeners = () => UNLOCK_EVENTS.forEach((event) => window.removeEventListener(event, attempt, { capture: true }));
    },

    /**
     * Tries to start the audio context (only a user gesture can). Once it runs, audio is
     * unlocked for the rest of the page and the music starts if it should be playing.
     */
    async unlock() {
        if (this.unlocked) {
            return true;
        }

        if (!(await this.synth.unlock())) {
            return false;
        }

        this.unlocked = true;
        detachUnlockListeners?.();
        detachUnlockListeners = null;
        this.syncMusic();

        return true;
    },

    async toggleMusic() {
        this.music = !this.music;
        writeSetting(MUSIC_KEY, this.music ? '1' : '0');

        await this.unlock();
        this.syncMusic();
    },

    async toggleSfx() {
        this.sfx = !this.sfx;
        writeSetting(SFX_KEY, this.sfx ? '1' : '0');

        if (this.sfx) {
            await this.unlock();
            this.play('click');
        }
    },

    setScreen(name) {
        this.screen = name;
        this.syncMusic();
    },

    /**
     * Plays one of: click, select, correct, wrong, tick, tickFinal. Before the unlock a cue
     * plays only when it comes from a user gesture (a tap or a key), which is also what
     * unlocks the context; any other cue is dropped rather than queued for later.
     */
    play(cue) {
        if (!this.sfx) {
            return;
        }

        if (!this.unlocked) {
            if (!insideUserGesture()) {
                return;
            }

            this.unlock();
        }

        switch (cue) {
            case 'click':
                this.synth.click();
                break;
            case 'select':
                this.synth.select();
                break;
            case 'correct':
                this.synth.correct();
                break;
            case 'wrong':
                this.synth.wrong();
                break;
            case 'tick':
                this.synth.tick(false);
                break;
            case 'tickFinal':
                this.synth.tick(true);
                break;
        }
    },

    syncMusic() {
        if (this.music && this.unlocked && MUSIC_SCREENS.includes(this.screen)) {
            this.track.play();
        } else {
            this.track.pause();
        }
    },
};
