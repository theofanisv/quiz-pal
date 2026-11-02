<?php

namespace App\Enums;

enum Locale: string
{
    case English = 'en';
    case Greek = 'el';

    /**
     * The language the app speaks until the visitor picks another one.
     * Read from its own key because App::setLocale() rewrites app.locale during a request.
     */
    public static function default(): self
    {
        return self::from(config('app.default_locale'));
    }

    public static function current(): self
    {
        return self::tryFrom(app()->getLocale()) ?? self::default();
    }

    /**
     * Every language except the default one; each of these has its own home page URL.
     *
     * @return list<self>
     */
    public static function alternates(): array
    {
        return array_values(array_filter(self::cases(), fn (self $locale): bool => $locale !== self::default()));
    }

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Greek => 'Ελληνικά',
        };
    }

    /**
     * The home page in this language: the root URL for the default language, a prefixed URL otherwise.
     */
    public function homeUrl(): string
    {
        return $this === self::default() ? route('home') : route('home.localized', $this);
    }

    public function openGraphLocale(): string
    {
        return match ($this) {
            self::English => 'en_US',
            self::Greek => 'el_GR',
        };
    }
}
