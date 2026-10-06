<?php
/**
 * Translation functions
 */


class Translation
{
    private static ?self $instance = null;

    /**
     * The loaded translations
     * @var array<string, string>
     */
    private array $translations = [];

    public function __construct(private string $language = 'en')
    {
        self::$instance = $this;
    }

    /**
     * Set the current language
     * @param string $language The language code
     */
    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    /**
     * Load translations from an array
     * @param array<string, string> $translations The translations to load
     */
    public function loadTranslations(array $translations): void
    {
        foreach ($translations as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $this->translations[$key] = $value;
            }
        }
    }

    /**
     * Translate a string using the current language
     * @param string $string The translation key
     * @param mixed ...$replacements Positional format values or a named replacement array
     * @return string The translated string
     */
    public static function translate(string $string, mixed ...$replacements): string
    {
        $translation = self::$instance?->translations[$string] ?? $string;

        if (count($replacements) === 1 && is_array($replacements[0])) {
            $formatArguments = $replacements[0];
        } else {
            $formatArguments = $replacements;
        }

        foreach ($formatArguments as $key => $value) {
            $translation = str_replace('{' . $key . '}', (string) $value, $translation);
        }

        if ($formatArguments !== [] && str_contains($translation, '%')) {
            try {
                $translation = vsprintf($translation, array_values($formatArguments));
            } catch (ArgumentCountError|ValueError) {
                // Keep the untranslated value when a catalog format is invalid.
            }
        }

        return $translation;
    }
}

if (!function_exists('__')) {

    /**
     * Translate a string using the current language
     * @param string $string The translation key
     * @param mixed ...$replacements Positional format values or a named replacement array
     * @return string The translated string
     */
    function __(string $string, mixed ...$replacements): string
    {
        return Translation::translate($string, ...$replacements);
    }
}