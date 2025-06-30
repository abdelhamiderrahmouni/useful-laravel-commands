<?php

declare(strict_types=1);

namespace UsefulLaravelCommands\Console\Commands;

use Illuminate\Console\Command;

class LangFilesToJson extends Command
{
    protected $signature = 'lang:json {paths?*}';
    protected $description = 'Convert Laravel language files from PHP to flat JSON (dot notation)';

    /**
     * Recursively flatten the language array into dot notation.
     */
    final public function convert(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_array($value)) {
                $result += $this->convert($value, $fullKey);
            } else {
                // Optional: Replace :placeholder with {placeholder}
                if (str_contains($value, ':')) {
                    $value = preg_replace('/:(\w+)/', '{$1}', $value);
                }
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    /**
     * @throws \JsonException
     */
    final public function handle(): void
    {
        $currentDir = getcwd();

        $sourceDirs = [
            $currentDir . '/lang/',
            $currentDir . '/resources/lang/',
            /* TODO: Deal with vendor packages that will be namespaced and deeply nested,
                     ex: filament-actions::associate.single.label
                     which will be in (/resources/lang | /lang)/vendor/filament-actions/en/associate.php
             */
        ];

        // Add custom language paths from command arguments, if any
        if ($customPaths = $this->argument('paths') ?? []) {
            foreach ((array)$customPaths as $customPath) {
                $sourceDirs[] = rtrim($customPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            }
        }

        $allLanguages = [];
        foreach ($sourceDirs as $sourceDir) {
            if (!is_dir($sourceDir)) {
                continue;
            }
            foreach (array_diff(scandir($sourceDir, SCANDIR_SORT_NONE), ['.', '..']) as $language) {
                $languageDir = $sourceDir . $language . '/';
                if (!is_dir($languageDir)) {
                    continue;
                }
                $allLanguages[$language][] = $languageDir;
            }
        }

        foreach ($allLanguages as $language => $dirs) {
            $translations = [];

            foreach ($dirs as $languageDir) {

                foreach (array_diff(scandir($languageDir, SCANDIR_SORT_NONE), ['.', '..']) as $file) {
                    $filePath = $languageDir . $file;
                    if (!str_ends_with($file, '.php')) {
                        continue;
                    }

                    $array = require $filePath;

                    $baseKey = str_replace('.php', '', $file);
                    $flattened = $this->convert($array, $baseKey);
                    $translations += $flattened;
                }
            }
            $targetPath = $currentDir . '/lang/' . $language . '.json';
            file_put_contents($targetPath, json_encode($translations, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $this->info('Language files compiled to flat JSON successfully!');
    }
}
