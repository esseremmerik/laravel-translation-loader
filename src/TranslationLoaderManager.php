<?php

namespace Spatie\TranslationLoader;

use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\FileLoader;
use Spatie\TranslationLoader\TranslationLoaders\TranslationLoader;

class TranslationLoaderManager extends FileLoader
{
    /**
     * Load the messages for the given locale.
     *
     * @param string $locale
     * @param string $group
     * @param string $namespace
     *
     * @return array
     */
    public function load($locale, $group, $namespace = '*'): array
    {
        $fileTranslations = parent::load($locale, $group, $namespace);

        try {

            if (! is_null($namespace) && $namespace !== '*' &&
                (\app::runningInConsole() && !Schema::hasColumn(config('laravel-translation-loader.model'), 'namespace'))) {
                return $fileTranslations;
            }
        } catch (\Exception $e) {
            return $fileTranslations;
        }
        
        $loaderTranslations = $this->getTranslationsForTranslationLoaders($locale, $group, $namespace);

        return array_replace_recursive($fileTranslations, $loaderTranslations);
    }

    protected function getTranslationsForTranslationLoaders(
        string $locale,
        string $group,
        string $namespace = '*'
    ): array {
        return collect(config('translation-loader.translation_loaders'))
            ->map(function (string $className) {
                return app($className);
            })
            ->mapWithKeys(function (TranslationLoader $translationLoader) use ($locale, $group, $namespace) {
                return $translationLoader->loadTranslations($locale, $group, $namespace);
            })
            ->toArray();
    }
}
