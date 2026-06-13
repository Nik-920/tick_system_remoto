<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * ExternalSdkIsolationTest — Verifica que los SDKs externos
 * NO se filtran fuera de los adapters permitidos.
 *
 * Recorre app/ y falla si encuentra referencias a:
 *   - Kreait\Firebase (SDK Firebase) fuera de FirebasePushNotificationAdapter
 *   - HuggingFaceService fuera de HuggingFaceEmbeddingAdapter y HuggingFaceService
 *
 * Criterio protegido: #4, #8 (encapsulación del adaptee externo)
 *
 * Excepciones documentadas:
 *   - app/Services/Firebase/FcmNotificationService.php: alias legado que extiende
 *     FirebasePushNotificationAdapter. No importa Kreait directamente, solo hereda.
 *     Si en el futuro se elimina este alias, también se puede remover de la lista.
 */
class ExternalSdkIsolationTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = realpath(__DIR__.'/../../../') ?: '';
    }

    // ──────────────────────────────────────────────────────────────
    // Firebase SDK (Kreait)
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function kreait_firebase_sdk_is_not_used_outside_firebase_adapter(): void
    {
        $pattern = 'Kreait\\Firebase';

        // Solo el adapter puede usar Kreait directamente.
        // FcmNotificationService extiende el adapter (herencia), no importa Kreait.
        $allowedFiles = [
            'app/Services/Firebase/FirebasePushNotificationAdapter.php',
        ];

        $violations = $this->findPatternViolations($pattern, $allowedFiles, 'app');

        $this->assertEmpty(
            $violations,
            "El SDK Kreait\\Firebase aparece fuera del adapter permitido (viola encapsulación):\n"
            .implode("\n", $violations)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // HuggingFace Adaptee
    // ──────────────────────────────────────────────────────────────

    #[Test]
    public function hugging_face_service_is_not_used_outside_its_adapter_and_service(): void
    {
        $pattern = 'HuggingFaceService';

        // HuggingFaceService puede ser referenciado en:
        //   - su propio archivo (definición)
        //   - el adapter (que lo encapsula)
        //   - tests unitarios propios de HuggingFaceService
        //   - AppServiceProvider si se registra explícitamente (no es el caso aquí)
        $allowedFiles = [
            'app/Services/Ai/HuggingFaceService.php',
            'app/Services/Ai/HuggingFaceEmbeddingAdapter.php',
        ];

        $violations = $this->findPatternViolations($pattern, $allowedFiles, 'app');

        $this->assertEmpty(
            $violations,
            "HuggingFaceService aparece fuera de los archivos permitidos (viola encapsulación):\n"
            .implode("\n", $violations)
        );
    }

    #[Test]
    public function listeners_do_not_import_kreait_sdk(): void
    {
        $listenersPath = $this->projectRoot.'/app/Listeners';
        if (! is_dir($listenersPath)) {
            $this->markTestSkipped('Directorio app/Listeners no existe.');
        }

        $violations = $this->findPatternViolations('Kreait\\Firebase', [], 'app/Listeners');

        $this->assertEmpty(
            $violations,
            "Un listener importa Kreait\\Firebase directamente (viola encapsulación del adapter):\n"
            .implode("\n", $violations)
        );
    }

    #[Test]
    public function jobs_do_not_import_kreait_sdk(): void
    {
        $jobsPath = $this->projectRoot.'/app/Jobs';
        if (! is_dir($jobsPath)) {
            $this->markTestSkipped('Directorio app/Jobs no existe.');
        }

        $violations = $this->findPatternViolations('Kreait\\Firebase', [], 'app/Jobs');

        $this->assertEmpty(
            $violations,
            "Un job importa Kreait\\Firebase directamente (viola encapsulación del adapter):\n"
            .implode("\n", $violations)
        );
    }

    #[Test]
    public function controllers_do_not_import_kreait_sdk(): void
    {
        $controllersPath = $this->projectRoot.'/app/Http/Controllers';
        if (! is_dir($controllersPath)) {
            $this->markTestSkipped('Directorio app/Http/Controllers no existe.');
        }

        $violations = $this->findPatternViolations('Kreait\\Firebase', [], 'app/Http/Controllers');

        $this->assertEmpty(
            $violations,
            "Un controller importa Kreait\\Firebase directamente (viola encapsulación del adapter):\n"
            .implode("\n", $violations)
        );
    }

    // ──────────────────────────────────────────────────────────────
    // Helper
    // ──────────────────────────────────────────────────────────────

    /**
     * Recorre archivos PHP bajo $searchSubPath y devuelve los que contienen
     * $pattern, excluyendo los archivos en $allowedRelativePaths.
     *
     * @param  string[]  $allowedRelativePaths  Rutas relativas al proyecto (ej: 'app/Foo.php')
     * @return string[] Rutas relativas de archivos con violaciones
     */
    private function findPatternViolations(
        string $pattern,
        array $allowedRelativePaths,
        string $searchSubPath
    ): array {
        $searchPath = $this->projectRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $searchSubPath);

        if (! is_dir($searchPath)) {
            return [];
        }

        $violations = [];

        $directory = new RecursiveDirectoryIterator($searchPath);
        $iterator = new RecursiveIteratorIterator($directory);

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! ($file instanceof SplFileInfo)) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            // Ruta relativa normalizada con forward slashes
            $relativePath = str_replace('\\', '/', ltrim(
                str_replace(str_replace('\\', '/', $this->projectRoot), '', str_replace('\\', '/', $realPath)),
                '/'
            ));

            // ¿Está en los archivos permitidos?
            foreach ($allowedRelativePaths as $allowed) {
                if (str_ends_with($relativePath, $allowed)) {
                    continue 2;
                }
            }

            $content = file_get_contents($realPath);
            if ($content === false) {
                continue;
            }

            if (str_contains($content, $pattern)) {
                $violations[] = $relativePath;
            }
        }

        return $violations;
    }
}
