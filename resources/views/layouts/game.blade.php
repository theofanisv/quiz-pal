<!DOCTYPE html>
{{-- Game pages are always dark: the head is included without @fluxAppearance so the OS setting never flips them. --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['appearance' => false])
        <meta name="color-scheme" content="dark">
    </head>
    <body class="min-h-screen bg-night-950 text-ink antialiased">
        <div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
            <div class="absolute -top-32 -left-24 size-96 rounded-full bg-neon-violet/20 blur-3xl motion-safe:animate-float"></div>
            <div class="absolute -right-24 -bottom-40 size-[28rem] rounded-full bg-neon-cyan/15 blur-3xl motion-safe:animate-float [animation-delay:-3.5s]"></div>
        </div>

        <div class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-6 px-4 py-5 sm:px-6">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center text-ink">
                    <x-game.logo />
                </a>

                <x-locale-switcher />
            </header>

            <main class="flex flex-1 flex-col gap-6">
                {{ $slot }}
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast position="top center" />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
