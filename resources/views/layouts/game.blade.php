<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-zinc-900">
        <div class="mx-auto flex min-h-svh w-full max-w-2xl flex-col gap-6 px-4 py-5 sm:px-6">
            <header class="flex items-center justify-between gap-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white">
                    <x-app-logo-icon class="size-7 fill-current" />
                    <span>{{ config('app.name') }}</span>
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
