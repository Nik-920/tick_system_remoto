<?php

declare(strict_types=1);

/**
 * Recursively lints PHP files with `php -l` and fails fast at the end if any file is invalid.
 */
final class PhpSyntaxChecker
{
    private const EXCLUDED_DIRS = [
        'vendor',
        'node_modules',
        '.git',
        'storage/framework/cache',
        'storage/framework/views',
        'bootstrap/cache',
        'public/build',
    ];

    /** @var list<string> */
    private array $errors = [];

    public function run(string $rootPath): int
    {
        $files = $this->collectPhpFiles($rootPath);

        if ($files === []) {
            fwrite(STDOUT, "No PHP files found to lint.\n");
            return 0;
        }

        foreach ($files as $file) {
            $this->lintFile($file);
        }

        if ($this->errors !== []) {
            fwrite(STDERR, "\nPHP syntax check failed:\n");
            foreach ($this->errors as $error) {
                fwrite(STDERR, " - {$error}\n");
            }
            return 1;
        }

        fwrite(STDOUT, sprintf("PHP syntax check passed for %d files.\n", count($files)));
        return 0;
    }

    /**
     * @return list<string>
     */
    private function collectPhpFiles(string $rootPath): array
    {
        $result = [];
        $directory = new RecursiveDirectoryIterator(
            $rootPath,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
        );

        $iterator = new RecursiveIteratorIterator($directory);

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $fileInfo->getPathname());

            if (!$this->isPhpFile($path)) {
                continue;
            }

            if ($this->isExcluded($path)) {
                continue;
            }

            $result[] = $path;
        }

        sort($result);
        return $result;
    }

    private function isPhpFile(string $path): bool
    {
        return str_ends_with($path, '.php');
    }

    private function isExcluded(string $path): bool
    {
        foreach (self::EXCLUDED_DIRS as $excludedDir) {
            if (str_contains($path, '/' . trim($excludedDir, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    private function lintFile(string $file): void
    {
        $command = sprintf('php -l %s', escapeshellarg($file));
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            $this->errors[] = sprintf('%s :: %s', $file, implode(' | ', $output));
            return;
        }

        fwrite(STDOUT, sprintf("[OK] %s\n", $file));
    }
}

$checker = new PhpSyntaxChecker();
exit($checker->run(dirname(__DIR__, 2)));
