<?php

namespace UsefulLaravelCommands\Console\Commands;

use Illuminate\Console\Command;

class CleanDevFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up development files such as logs, debugbar, and temporary files in laravel applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $currentPath = getcwd();

        $this->info('Cleaning up development files...');

        $pathsToClean = [
            'logs' => $currentPath . '/storage/logs/*.log',
            'debugbar' => $currentPath . '/storage/debugbar/*',
            'livewire-tmp' => $currentPath . '/storage/app/livewire-tmp/*',
            'pail' => $currentPath . '/storage/pail/*',
        ];

        $totalDeleted = 0;

        foreach ($pathsToClean as $type => $path) {
            $count = $this->cleanFiles($path);
            if (! $count) continue;

            $this->info("✅  Deleted {$count} {$type} files");
            $totalDeleted += $count;
        }

        if ($totalDeleted) {
            $this->info("👍 Total files cleaned: {$totalDeleted}");
            $this->info('🥳 Development files cleaned successfully.');
        } else {
            $this->info("🥳 Your project is clean.");
        }
    }

    /**
     * Clean files matching the given pattern, preserving .gitignore
     *
     * @param string $pattern
     * @return int Number of files deleted
     */
    private function cleanFiles(string $pattern): int
    {
        $count = 0;

        foreach (glob($pattern, GLOB_NOSORT) as $file) {
            if (is_file($file) && basename($file) !== '.gitignore') {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
