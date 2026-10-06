<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Group;
use Illuminate\Database\Eloquent\Model;

/**
 * Renders one copy of a field per locale, side by side, bound to `{name}.{locale}`.
 *
 * Native replacement for mvenghaus/filament-plugin-translatable-inline (Filament 3 only).
 * Works with spatie/laravel-translatable models (which cast translatable attributes to
 * arrays) and with models that store a JSON column behind a string accessor (Center).
 */
final class TranslatableFields
{
    public const DEFAULT_LOCALES = ['en', 'ar'];

    /**
     * @param  list<string>  $locales
     * @param  list<string>|null  $requiredLocales  null = all locales when the field is required
     */
    public static function make(Field $field, array $locales = self::DEFAULT_LOCALES, ?array $requiredLocales = null): Group
    {
        $name = $field->getName();
        $baseLabel = $field->getLabel();
        $requiredLocales ??= $field->isRequired() ? $locales : [];

        $fields = array_map(
            fn (string $locale): Field => $field->getClone()
                ->name($locale)
                ->statePath($locale)
                ->label("{$baseLabel} ({$locale})")
                ->required(in_array($locale, $requiredLocales, true)),
            $locales,
        );

        return Group::make($fields)
            ->statePath($name)
            ->columns(count($locales))
            ->columnSpanFull()
            ->afterStateHydrated(function (Group $component, mixed $state, ?Model $record) use ($name): void {
                if (is_array($state) || ! $record) {
                    return;
                }

                // An accessor returned the current-locale string: fall back to the raw JSON.
                $raw = $record->getRawOriginal($name);
                $component->state(is_string($raw) ? (json_decode($raw, true) ?: []) : []);
            });
    }

    /**
     * Only the first (main) locale is required.
     *
     * @param  list<string>  $locales
     */
    public static function mainLocaleRequired(Field $field, array $locales = self::DEFAULT_LOCALES): Group
    {
        return self::make($field, $locales, [$locales[0]]);
    }
}
