import { createMusic, createSynth } from './audio';

const MUSIC_KEY = 'quizpal.sound.music';
const SFX_KEY = 'quizpal.sound.sfx';
const MUSIC_SCREENS = ['play', 'results'];

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

        const unlock = () => this.unlock();

        window.addEventListener('pointerdown', unlock, { capture: true, once: true });
        window.addEventListener('keydown', unlock, { capture: true, once: true });
    },

    /** Marks audio as unlocked by a user gesture and starts the music if it should play. */
    async unlock() {
        if (this.unlocked) {
            return;
        }

        await this.synth.unlock();
        this.unlocked = true;
        this.syncMusic();
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

    /** Plays one of: click, select, correct, wrong, tick, tickFinal. */
    play(cue) {
        if (!this.sfx || !this.unlocked) {
            return;
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
