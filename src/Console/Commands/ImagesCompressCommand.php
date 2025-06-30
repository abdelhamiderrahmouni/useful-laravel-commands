<?php

namespace UsefulLaravelCommands\Console\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Console\Command;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\Finder\Finder;

class ImagesCompressCommand extends Command
{
    protected $signature = 'images:compress
                            {path? : The path to the assets folder. Default is in config file (public/assets).}
                            {--keep : Keep the original images after optimization.}
                            {--prefix=old_ : prefix of the original images folder.}
                            {--details : Display the output of the optimization process.}';

    protected $description = 'Compress all static images in the provided folder recursively!';

    protected array $errors = [];

    protected string $assetsDir = '';

    protected bool $keepOriginal = false;

    public function handle(): int
    {
        $this->assetsDir = $this->argument('path') ?? config('useful-commands.assets_dir', 'public');

        $this->keepOriginal = $this->option('keep');

        // check if the provided path is a directory
        if (! is_dir($this->assetsDir)) {
            $this->error("The provided path {$this->assetsDir} is not a directory.");

            return self::FAILURE;
        }

        if ($this->keepOriginal) {
            $originalDir = $this->option('prefix') . $this->assetsDir;

            // Check if the directory exists, if not, attempt to create it
            //File::ensureDirectoryExists($originalDir);
            File::copyDirectory($this->assetsDir, $originalDir);

            $this->info("📁 Original images will be kept in {$originalDir}.");
        }

        $finder = new Finder();

        $this->info('🔍 Loading images from '.$this->assetsDir.' directory.');

        // Find all files within the assets directory
        $finder->files()->in($this->assetsDir);

        $this->info('🚀 Optimizing images...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($finder));
        if (! $this->option('details')) {
            $bar->start();
        }

        foreach ($finder as $file) {
            $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            $supportedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

            if (in_array($extension, $supportedExtensions)) {
                try {
                    $optimizerChain = OptimizerChainFactory::create();
                    $optimizerChain->optimize($file->getPathname());

                    if ($this->option('details')) {
                        $this->info("Optimized: {$file->getPathname()}");
                    } else {
                        $bar->advance();
                    }

                } catch (\Exception $e) {
                    $this->errors[$file->getFilename()] = $e->getMessage();
                }
            }
        }

        if (! $this->option('details')) {
            $bar->finish();
        }

        $this->newLine(2);
        $this->info('🥳 All images in the '.$this->assetsDir.' folder have been optimized.');

        $this->reportErrors();

        return self::SUCCESS;
    }

    protected function reportErrors()
    {
        if (count($this->errors) > 0) {
            $this->error('Some errors occurred during the optimization:');
            foreach ($this->errors as $filename => $error) {
                $this->error("{$filename}: {$error}");
            }
        }
    }
}
