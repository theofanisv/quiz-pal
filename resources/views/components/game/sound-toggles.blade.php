{{-- Music and sound-effect toggles, remembered per device by the Alpine `sound` store. --}}
<div x-data {{ $attributes->class(['flex items-center gap-1 rounded-full border border-night-700 bg-night-800 p-1']) }}>
    <button
        type="button"
        x-on:click="$store.sound.toggleMusic()"
        x-bind:aria-pressed="$store.sound.music"
        x-bind:class="! $store.sound.music && 'opacity-40'"
        aria-label="{{ __('Music') }}"
        title="{{ __('Music') }}"
        class="flex size-8 items-center justify-center rounded-full text-ink transition hover:bg-night-700"
        data-test="music-toggle"
    >
        <flux:icon.musical-note class="size-4" variant="mini" />
    </button>

    <button
        type="button"
        x-on:click="$store.sound.toggleSfx()"
        x-bind:aria-pressed="$store.sound.sfx"
        x-bind:class="! $store.sound.sfx && 'opacity-40'"
        aria-label="{{ __('Sound effects') }}"
        title="{{ __('Sound effects') }}"
        class="flex size-8 items-center justify-center rounded-full text-ink transition hover:bg-night-700"
        data-test="sfx-toggle"
    >
        <flux:icon.speaker-wave class="size-4" variant="mini" x-show="$store.sound.sfx" />
        <flux:icon.speaker-x-mark class="size-4" variant="mini" x-show="! $store.sound.sfx" x-cloak />
    </button>
</div>
