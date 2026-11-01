<?php

namespace App\Models\Concerns;

trait HasLocalizedText
{
    /**
     * Read the "{attribute}_{locale}" text column for the current locale, falling back to English.
     */
    protected function localizedText(string $attribute): string
    {
        $value = $this->localizedValue($attribute);

        return is_string($value) ? $value : '';
    }

    /**
     * Read the "{attribute}_{locale}" list column for the current locale, falling back to English.
     *
     * @return array<int, string>
     */
    protected function localizedList(string $attribute): array
    {
        $value = $this->localizedValue($attribute);

        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    private function localizedValue(string $attribute): mixed
    {
        $locale = app()->getLocale();

        return $this->getAttribute("{$attribute}_{$locale}") ?? $this->getAttribute("{$attribute}_en");
    }
}
