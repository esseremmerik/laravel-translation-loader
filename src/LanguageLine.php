<?php

namespace Spatie\TranslationLoader;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class LanguageLine extends Model
{
    /** @var array */
    public $translatable = ['text'];

    /** @var array */
    public $guarded = ['id'];

    /** @var array */
    protected $casts = ['text' => 'array'];

    public static function boot()
    {
        parent::boot();

        $flushGroupCache = function (self $languageLine) {
            $languageLine->flushGroupCache();
        };

        static::saved($flushGroupCache);
        static::deleted($flushGroupCache);
    }

    public static function getTranslationsForGroup(string $namespace, string $locale, string $group): array
    {
        return Cache::rememberForever(static::getCacheKey($namespace, $group, $locale), function () use ($group, $locale, $namespace) {
            return static::query()
                    ->where('namespace', $namespace)
                    ->where('group', $group)
                    ->get()
                    ->reduce(function ($lines, self $languageLine) use ($group, $locale) {
                        $translation = $languageLine->getTranslation($locale);

                        if ($translation !== null && $group === '*') {
                            // Make a flat array when returning json translations
                            $lines[$languageLine->key] = $translation;
                        } elseif ($translation !== null && $group !== '*') {
                            // Make a nesetd array when returning normal translations
                            Arr::set($lines, $languageLine->key, $translation);
                        }

                        return $lines;
                    }) ?? [];
        });
    }

    public static function getCacheKey(string $namespace, string $group, string $locale): string
    {
        return "spatie.laravel-translation-loader.{$namespace}.{$group}.{$locale}";
    }

    /**
     * @param string $locale
     *
     * @return string
     */
    public function getTranslation(string $locale): ?string
    {
        if (! isset($this->text[$locale])) {
            $fallback = config('app.fallback_locale');

            return $this->text[$fallback] ?? null;
        }

        return $this->text[$locale];
    }

    /**
     * @param string $locale
     * @param string $value
     *
     * @return $this
     */
    public function setTranslation(string $locale, string $value)
    {
        $this->text = array_merge($this->text ?? [], [$locale => $value]);

        return $this;
    }

    public function flushGroupCache()
    {
        foreach ($this->getTranslatedLocales() as $locale) {
            Cache::forget(static::getCacheKey($this->namespace, $this->group, $locale));
        }
    }

    protected function getTranslatedLocales(): array
    {
        return array_keys($this->text);
    }
}
