<?php

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * NoConcreteAdapterLeakTest — Verifica que los adapters concretos
 * NO se importan directamente en clientes de dominio.
 *
 * Recorre app/ y falla si encuentra imports de adapters concretos
 * fuera de los lugares permitidos.
 *
 * Criterio protegido: #5, #8, #12 (DIP — Dependency Inversion Principle)
 *
 * Lugares PERMITIDOS para importar adapters concretos:
 *   - app/Providers/AppServiceProvider.php (binding IoC)
 *   - app/Services/Ai/HuggingFaceEmbeddingAdapter.php (definición)
 *   - app/Services/Firebase/FirebasePushNotificationAdapter.php (definición)
 *   - app/Services/Firebase/FcmNotificationService.php (alias legado)
 */
class NoConcreteAdapterLeakTest extends TestCase
{
    private string $appPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appPath = realpath(__DIR__.'/../../../app') ?: '';
    }

    #[Test]
    public function no_domain_client_imports_hugging_face_embedding_adapter_directly(): void
    {
        $forbidden = 'App\\Services\\Ai\\HuggingFaceEmbeddingAdapter';

        $allowedFiles = [
            'app/Providers/AppServiceProvider.php',
            'app/Services/Ai/HuggingFaceEmbeddingAdapter.php',
        ];

        $violations = $this->findImportViolations($forbidden, $allowedFiles);

        $this->assertEmpty(
            $violations,
            "Los siguientes archivos importan HuggingFaceEmbeddingAdapter directamente (viola DIP):\n"
            .implode("\n", $violations)
        );
    }

    #[Test]
    public function no_domain_client_imports_firebase_push_notification_adapter_directly(): void
    {
        $forbidden = 'App\\Services\\Firebase\\FirebasePushNotificationAdapter';

        $allowedFiles = [
            'app/Providers/AppServiceProvider.php',
            'app/Services/Firebase/FirebasePushNotificationAdapter.php',
            'app/Services/Firebase/FcmNotificationService.php', // alias legado
        ];

        $violations = $this->findImportViolations($forbidden, $allowedFiles);

        $this->assertEmpty(
            $violations,
            "Los siguientes archivos importan FirebasePushNotificationAdapter directamente (viola DIP):\n"
            .implode("\n", $violations)
        );
    }

    /**
     * @param  string[]  $allowedFiles  Rutas relativas al proyecto (desde raíz)
     * @return string[]
     */
    private function findImportViolations(string $forbiddenClass, array $allowedFiles): array
    {
        $violations = [];

        /** @var SplFileInfo $file */
        foreach ($this->phpFilesInApp() as $file) {
            $realPath = $file->getRealPath();
            if ($realPath === false) {
                continue;
            }

            // Normaliza separadores de ruta para comparación cross-platform
            $relativePath = str_replace('\\', '/', ltrim(
                str_replace(str_replace('\\', '/', realpath(__DIR__.'/../../../') ?: ''), '', str_replace('\\', '/', $realPath)),
                '/'
            ));

            // ¿Es un archivo permitido?
            foreach ($allowedFiles as $allowed) {
                if (str_ends_with($relativePath, $allowed)) {
                    continue 2;
                }
            }

            $content = file_get_contents($realPath);
            if ($content === false) {
                continue;
            }

            // Busca `use App\Services\Ai\HuggingFaceEmbeddingAdapter;`
            // o `App\Services\Ai\HuggingFaceEmbeddingAdapter::class`
            if (str_contains($content, $forbiddenClass)) {
                $violations[] = $relativePath;
            }
        }

        return $violations;
    }

    /**
     * @return \Generator<int, SplFileInfo>
     */
    private function phpFilesInApp(): \Generator
    {
        $directory = new RecursiveDirectoryIterator($this->appPath);
        $iterator = new RecursiveIteratorIterator($directory);

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
