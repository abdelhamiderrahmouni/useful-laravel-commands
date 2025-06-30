<?php

namespace UsefulLaravelCommands\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Termwind\render;

class TranslateCommand extends Command
{
    protected $signature = 'translate';

    protected $description = 'A command to help in translating your laravel app';

    private string $sourceLanguage = 'en';

    private array $languagesWithStats = [];

    public function handle(): void
    {
        $this->sourceLanguage = $this->getSourceLanguage();

        while (true) {
            $this->calculateStats();

            render(view('useful-laravel-commands::translate', [
                'languages' => $this->languagesWithStats,
                'source' => $this->sourceLanguage,
            ]));

            $action = select(
                'What do you want to do?',
                [
                    'add' => 'Add a new translation',
                    'syncAll' => 'Sync all translations',
                    'addLang' => 'Add a new language',
                    'sync' => 'Sync a specific language file',
                    'delete' => 'Delete a language file',
                    'source' => 'Change source language',
                    'exit' => 'Exit',
                ]
            );

            match ($action) {
                'add' => $this->addTranslation(),
                'syncAll' => $this->syncAllTranslations(),
                'addLang' => $this->addLanguage(),
                'sync' => $this->syncFile(),
                'delete' => $this->deleteFile(),
                'source' => $this->sourceLanguage = $this->getSourceLanguage(),
                'exit' => exit(0),
            };
        }
    }

    private function getTranslationFiles(): array
    {
        $allTranslationFiles = [];

        $appLangDirs = [
            resource_path('lang'),
            base_path('lang'),
        ];

        foreach ($appLangDirs as $baseDir) {
            if (! File::isDirectory($baseDir)) {
                continue;
            }

            // Process top-level JSON files (e.g., resources/lang/en.json)
            $jsonFiles = File::files($baseDir, '*.json');
            foreach ($jsonFiles as $file) {
                $langCode = $file->getFilenameWithoutExtension();
                $identifier = $langCode . ' (json)';
                $allTranslationFiles[$identifier]['json'] = $file->getPathname();
            }

            // Process top-level PHP files (e.g., resources/lang/en.php)
            $phpFiles = File::files($baseDir, '*.php');
            foreach ($phpFiles as $file) {
                $langCode = $file->getFilenameWithoutExtension();
                $identifier = $langCode . ' (php)';
                $allTranslationFiles[$identifier]['php']['_single'] = $file->getPathname();
            }

            // Process PHP files in language subdirectories (e.g., lang/en/messages.php)
            $langSubDirs = File::directories($baseDir);
            foreach ($langSubDirs as $subDir) {
                $langCode = basename($subDir);
                // Ensure this is not the 'vendor' directory
                if ($langCode === 'vendor') {
                    continue;
                }
                $phpFilesInSubDir = File::files($subDir, '*.php');
                if ($phpFilesInSubDir->isNotEmpty()) {
                    $identifier = $langCode . '/'; // Identifier for folder-based PHP translations
                    foreach ($phpFilesInSubDir as $file) {
                        $allTranslationFiles[$identifier]['php'][$file->getFilenameWithoutExtension()] = $file->getPathname();
                    }
                }
            }

            // Process vendor directories
            $vendorDir = $baseDir . DIRECTORY_SEPARATOR . 'vendor';
            if (File::isDirectory($vendorDir)) {
                $packageDirs = File::directories($vendorDir);
                foreach ($packageDirs as $packageDir) {
                    $packageName = basename($packageDir);
                    $packageLangDir = $packageDir . DIRECTORY_SEPARATOR . 'lang';

                    if (File::isDirectory($packageLangDir)) {
                        // Scan JSON files in vendor package lang directory
                        $vendorJsonFiles = File::files($packageLangDir, '*.json');
                        foreach ($vendorJsonFiles as $file) {
                            $lang = $file->getFilenameWithoutExtension();
                            $identifier = 'vendor/' . $packageName . '/' . $lang . ' (json)';
                            $allTranslationFiles[$identifier]['json'] = $file->getPathname();
                        }

                        // Scan PHP files in vendor package lang subdirectories first
                        $vendorLangSubDirs = File::directories($packageLangDir);
                        foreach ($vendorLangSubDirs as $vendorSubDir) {
                            $lang = basename($vendorSubDir);
                            $vendorPhpFilesInSubDir = File::files($vendorSubDir, '*.php');
                            if ($vendorPhpFilesInSubDir->isNotEmpty()) {
                                $identifier = 'vendor/' . $packageName . '/' . $lang . '/'; // Identifier for folder-based PHP translations
                                foreach ($vendorPhpFilesInSubDir as $file) {
                                    $allTranslationFiles[$identifier]['php'][$file->getFilenameWithoutExtension()] = $file->getPathname();
                                }
                            }
                        }

                        // Scan top-level PHP files in vendor package lang directory
                        // Only add if a folder-based identifier for this language doesn't already exist
                        $vendorPhpFiles = File::files($packageLangDir, '*.php');
                        foreach ($vendorPhpFiles as $file) {
                            $lang = $file->getFilenameWithoutExtension();
                            $folderIdentifier = 'vendor/' . $packageName . '/' . $lang . '/';
                            if (!isset($allTranslationFiles[$folderIdentifier])) {
                                $identifier = 'vendor/' . $packageName . '/' . $lang . ' (php)';
                                $allTranslationFiles[$identifier]['php']['_single'] = $file->getPathname();
                            }
                        }
                    }
                }
            }
        }

        return $allTranslationFiles;
    }

    private function getLanguages(): array
    {
        return array_keys($this->getTranslationFiles());
    }

    private function getSourceLanguage(): string
    {
        return select(
            'Select source language',
            $this->getLanguages(),
            $this->sourceLanguage
        );
    }

    private function calculateStats(): void
    {
        $files = $this->getTranslationFiles();
        $sourceTranslations = $this->getTranslationsForLanguage($this->sourceLanguage);

        $this->languagesWithStats = [];
        foreach ($files as $identifier => $fileData) {
            if ($identifier === $this->sourceLanguage) {
                $this->languagesWithStats[$identifier] = [
                    'missing' => 0,
                    'total' => count($sourceTranslations),
                ];
                continue;
            }

            $translations = $this->getTranslationsForLanguage($identifier);
            $missingKeys = array_diff_key($sourceTranslations, $translations);

            $this->languagesWithStats[$identifier] = [
                'missing' => count($missingKeys),
                'total' => count($sourceTranslations),
            ];
        }
    }

    private function getTranslationsForLanguage(string $identifier): array
    {
        $allFiles = $this->getTranslationFiles();

        if (! isset($allFiles[$identifier])) {
            return [];
        }

        $translations = [];
        $filesForIdentifier = $allFiles[$identifier];

        // Handle JSON files
        if (isset($filesForIdentifier['json'])) {
            $jsonPath = $filesForIdentifier['json'];
            $translations = array_merge($translations, json_decode(File::get($jsonPath), true) ?: []);
        }

        // Handle PHP files
        if (isset($filesForIdentifier['php'])) {
            foreach ($filesForIdentifier['php'] as $phpPath) {
                $translations = array_merge($translations, require $phpPath);
            }
        }

        return $translations;
    }

    private function addTranslation(): void
    {
        $key = text('Enter the translation key', required: true);
        $value = text("Enter the translation value for ({$this->sourceLanguage})", required: true);

        $addToAll = confirm('Add to all languages?', true);
        $translate = false;
        if ($addToAll) {
            $translate = confirm('Auto-translate the values?', true);
        }

        $files = $this->getTranslationFiles();

        foreach ($files as $identifier => $fileData) {
            if ($identifier === $this->sourceLanguage) {
                $translations = $this->getTranslationsForLanguage($identifier);
                $translations[$key] = $value;
                $this->saveTranslations($identifier, $translations);
            } elseif ($addToAll) {
                $translations = $this->getTranslationsForLanguage($identifier);
                $translatedValue = $this->translateValue($value, $identifier, $translate);
                $translations[$key] = $translatedValue;
                $this->saveTranslations($identifier, $translations);
            }
        }

        $this->info('Translation added successfully.');
    }

    private function syncAllTranslations(): void
    {
        $translate = confirm('Auto-translate the missing values?', true);
        $sourceTranslations = $this->getTranslationsForLanguage($this->sourceLanguage);

        foreach ($this->languagesWithStats as $identifier => $stats) {
            if ($identifier === $this->sourceLanguage || $stats['missing'] === 0) {
                continue;
            }

            $this->syncLanguage($identifier, $sourceTranslations, $translate);
        }

        $this->info('All translations synced successfully.');
    }

    private function addLanguage(): void
    {
        $language = text('Enter the language code (e.g., fr)', required: true);

        $sourceFilesData = $this->getTranslationFiles()[$this->sourceLanguage] ?? [];

        $newFileCreated = false;

        $sourceIdentifier = $this->sourceLanguage;

        $baseDir = $this->getLanguageBaseDirectory($sourceIdentifier, $sourceFilesData);

        if ($baseDir === null) {
            $this->error("Could not determine base directory for new language files from source: {$this->sourceLanguage}");

            return;
        }

        // Create JSON file if source was JSON
        if (str_ends_with($sourceIdentifier, ' (json)')) {
            $file = $baseDir.'/'.$language.'.json';
            if (! File::exists($file)) {
                File::put($file, json_encode((object) [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $this->info("Language file created: {$file}");
                $newFileCreated = true;
            } else {
                $this->warn("Language file already exists: {$file}");
            }
        }

        // Create PHP files if source was PHP (top-level or folder-based)
        if (str_ends_with($sourceIdentifier, ' (php)') || str_ends_with($sourceIdentifier, '/')) {
            // If source was top-level PHP (e.g., en.php), create new top-level PHP file (fr.php)
            if (str_ends_with($sourceIdentifier, ' (php)')) {
                $newFilePath = $baseDir.'/'.$language.'.php';
                if (! File::exists($newFilePath)) {
                    File::put($newFilePath, "<?php\n\nreturn [];\n");
                    $this->info("Language file created: {$newFilePath}");
                    $newFileCreated = true;
                } else {
                    $this->warn("Language file already exists: {$newFilePath}");
                }
            }

            // If source was folder-based PHP (e.g., en/messages.php), create new folder and files
            if (str_ends_with($sourceIdentifier, '/')) {
                foreach ($sourceFilesData['php'] as $key => $sourceFilePath) {
                    $filename = pathinfo($sourceFilePath, PATHINFO_FILENAME);
                    $newLangDir = $baseDir.'/'.$language;
                    File::makeDirectory($newLangDir, 0755, true, true);
                    $newFilePath = $newLangDir.'/'.$filename.'.php';

                    if (! File::exists($newFilePath)) {
                        File::put($newFilePath, "<?php\n\nreturn [];\n");
                        $this->info("Language file created: {$newFilePath}");
                        $newFileCreated = true;
                    } else {
                        $this->warn("Language file already exists: {$newFilePath}");
                    }
                }
            }
        }

        if (! $newFileCreated) {
            $this->error("Could not create any language files for {$language}. Check source language files.");
        }
    }

    private function syncFile(): void
    {
        $langToSync = select(
            'Which language do you want to sync?',
            $this->getLanguages()
        );

        $translate = confirm('Auto-translate the missing values?', true);
        $sourceTranslations = $this->getTranslationsForLanguage($this->sourceLanguage);

        $this->syncLanguage($langToSync, $sourceTranslations, $translate);

        $this->info("Language {$langToSync} synced successfully.");
    }

    private function syncLanguage(string $lang, array $sourceTranslations, bool $translate): void
    {
        $translations = $this->getTranslationsForLanguage($lang);

        $syncedTranslations = $this->recursiveSyncAndTranslate($sourceTranslations, $translations, $lang, $translate);

        $this->saveTranslations($lang, $syncedTranslations);
    }

    private function recursiveSyncAndTranslate(array $source, array $target, string $targetLang, bool $translate): array
    {
        foreach ($source as $key => $sourceValue) {
            if (is_array($sourceValue)) {
                // If source value is an array, ensure target key exists and is an array
                if (!isset($target[$key]) || !is_array($target[$key])) {
                    $target[$key] = [];
                }
                // Recursively sync nested arrays
                $target[$key] = $this->recursiveSyncAndTranslate($sourceValue, $target[$key], $targetLang, $translate);
            } else {
                // If source value is a string
                // Only translate if missing or if explicit re-translation is requested
                if (!isset($target[$key]) || $translate) {
                    $target[$key] = $this->translate($this->sourceLanguage, $targetLang, $sourceValue);
                }
            }
        }

        return $target;
    }

    private function translateValue(mixed $value, string $targetLang, bool $translate): mixed
    {
        if (is_array($value)) {
            $translatedArray = [];
            foreach ($value as $nestedKey => $nestedValue) {
                $translatedArray[$nestedKey] = $this->translateValue($nestedValue, $targetLang, $translate);
            }

            return $translatedArray;
        }

        return $translate ? $this->translate($this->sourceLanguage, $targetLang, $value) : $value;
    }

    private function deleteFile(): void
    {
        $langToDelete = select(
            'Which language do you want to delete?',
            $this->getLanguages()
        );

        if (confirm("Are you sure you want to delete all files for `{$langToDelete}`?")) {
            $files = $this->getTranslationFiles();
            if (isset($files[$langToDelete])) {
                $languageFiles = $files[$langToDelete];

                if (isset($languageFiles['json'])) {
                    File::delete($languageFiles['json']);
                }

                if (isset($languageFiles['php'])) {
                    foreach ($languageFiles['php'] as $phpFile) {
                        File::delete($phpFile);
                    }
                }
                $this->info("Language {$langToDelete} deleted successfully.");
            }
        }
    }

    private function saveTranslations(string $lang, array $translations): void
    {
        ksort($translations);

        $targetPath = $this->getTargetFilePathForLanguage($lang);

        if ($targetPath === null) {
            $this->error("Could not find a suitable file to save translations for language: {$lang}");

            return;
        }

        if (str_ends_with($targetPath, '.json')) {
            File::put($targetPath, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } elseif (str_ends_with($targetPath, '.php')) {
            $content = var_export($translations, true);
            $content = str_replace('array (', '[', $content);
            $content = str_replace(')', ']', $content);
            $content = "<?php\n\nreturn " . $content . ";\n";
            File::put($targetPath, $content);
        }
    }

    private function translate(string $source, string $target, string $text): string
    {
        if (empty($text)) {
            return '';
        }

        $response = Http::get("https://translate.googleapis.com/translate_a/single?client=gtx&sl={$source}&tl={$target}&dt=t&q=".urlencode($text));

        if ($response->failed()) {
            $this->error("Failed to translate '{$text}' from {$source} to {$target}.");

            return $text;
        }

        return Arr::get($response->json(), '0.0.0', $text);
    }

    private function getTargetFilePathForLanguage(string $identifier): ?string
    {
        $allFiles = $this->getTranslationFiles();
        if (!isset($allFiles[$identifier])) {
            return null;
        }

        $filesForIdentifier = $allFiles[$identifier];

        // Prioritize JSON file
        if (isset($filesForIdentifier['json'])) {
            return $filesForIdentifier['json'];
        }

        // Then prioritize 'messages.php' if it exists
        if (isset($filesForIdentifier['php']['messages'])) {
            return $filesForIdentifier['php']['messages'];
        }

        // Then top-level PHP file
        if (isset($filesForIdentifier['php']['_single'])) {
            return $filesForIdentifier['php']['_single'];
        }

        // Fallback to the first PHP file found
        if (isset($filesForIdentifier['php']) && !empty($filesForIdentifier['php'])) {
            return reset($filesForIdentifier['php']);
        }

        return null;
    }

    private function getLanguageBaseDirectory(string $sourceIdentifier, array $sourceFilesData): ?string
    {
        $isVendor = str_starts_with($sourceIdentifier, 'vendor/');

        if ($isVendor) {
            preg_match('/^vendor\/(.*?)\/.*$/', $sourceIdentifier, $matches);
            $packageName = $matches[1] ?? null;

            if ($packageName) {
                $baseDir = base_path('lang') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . 'lang';
                if (File::isDirectory($baseDir)) {
                    return $baseDir;
                }
                $baseDir = resource_path('lang') . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . $packageName . DIRECTORY_SEPARATOR . 'lang';
                if (File::isDirectory($baseDir)) {
                    return $baseDir;
                }
            }
        } else {
            if (str_ends_with($sourceIdentifier, ' (json)') && isset($sourceFilesData['json'])) {
                return dirname($sourceFilesData['json']);
            } elseif (str_ends_with($sourceIdentifier, ' (php)') && isset($sourceFilesData['php']['_single'])) {
                return dirname($sourceFilesData['php']['_single']);
            } elseif (str_ends_with($sourceIdentifier, '/') && isset($sourceFilesData['php'])) {
                // For folder-based PHP, get the directory of one of its files
                $firstPhpFile = reset($sourceFilesData['php']);
                return dirname($firstPhpFile); // This is already the lang/en/ directory
            }
        }

        return null;
    }
}

    

    
