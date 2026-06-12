<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guardrail defensivo: nada de lo que se sirve al navegador (vistas Blade,
 * JS fuente, bundle compilado de Vite y assets publicos) puede contener
 * secretos privados. Todo lo que llega a DevTools se considera publico.
 */
class FrontendSecretExposureTest extends TestCase
{
    /**
     * Patrones que jamas deben aparecer en codigo entregado al navegador.
     *
     * @var array<int, string>
     */
    private const FORBIDDEN_PATTERNS = [
        'service_role',
        'SERVICE_ROLE',
        'sb_secret',
        'SUPABASE_SERVICE_ROLE_KEY',
        'SUPABASE_STORAGE_SERVICE_KEY',
        'DB_PASSWORD',
        'DATABASE_URL',
        'APP_KEY=base64:',
        'BEGIN PRIVATE KEY',
        'BEGIN RSA PRIVATE KEY',
        'FIREBASE_CREDENTIALS',
    ];

    /**
     * @return array<int, array{string, array<int, string>}>
     */
    public static function frontendDirectories(): array
    {
        return [
            ['resources/views', ['*.blade.php']],
            ['resources/js', ['*.js']],
            ['public/build', ['*.js', '*.css', '*.json', '*.map']],
        ];
    }

    #[Test]
    public function frontend_files_do_not_contain_private_secrets(): void
    {
        foreach (self::frontendDirectories() as [$relativeDir, $namePatterns]) {
            $directory = base_path($relativeDir);
            if (! is_dir($directory)) {
                continue;
            }

            $finder = (new Finder)->files()->in($directory)->name($namePatterns);

            foreach ($finder as $file) {
                $contents = (string) file_get_contents($file->getRealPath());

                foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                    $this->assertStringNotContainsString(
                        $pattern,
                        $contents,
                        sprintf(
                            'Patron sensible "%s" encontrado en archivo servido al navegador: %s',
                            $pattern,
                            $file->getRelativePathname()
                        )
                    );
                }
            }
        }

        // Si ningun directorio existiera el test seria vacuo; al menos views debe existir.
        $this->assertDirectoryExists(base_path('resources/views'));
    }

    #[Test]
    public function vite_build_does_not_publish_sourcemaps(): void
    {
        $buildDir = base_path('public/build');
        if (! is_dir($buildDir)) {
            $this->markTestSkipped('No hay build de Vite en public/build.');
        }

        $maps = iterator_to_array((new Finder)->files()->in($buildDir)->name('*.map'));

        $this->assertSame(
            [],
            array_map(static fn ($f) => $f->getRelativePathname(), $maps),
            'Sourcemaps publicados en public/build: deben deshabilitarse en produccion.'
        );
    }

    #[Test]
    public function session_cookie_configuration_is_hardened(): void
    {
        $this->assertTrue((bool) config('session.http_only'), 'La cookie de sesion debe ser HttpOnly.');
        $this->assertContains(config('session.same_site'), ['lax', 'strict'], 'SameSite debe ser lax o strict.');
    }
}
