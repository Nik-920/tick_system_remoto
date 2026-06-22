<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Functional;

use App\Support\Functional\UploadRules;
use App\Support\Functional\UploadValidationError;
use App\Support\Functional\UploadValidationPipeline;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for UploadValidationPipeline — pure functional validation logic.
 *
 * These tests exercise array_map, array_filter, array_reduce paths directly
 * without touching the database, HTTP stack or form request layer.
 */
class UploadValidationPipelineTest extends TestCase
{
    private UploadValidationPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = new UploadValidationPipeline;
    }

    // ── UploadRules::fromConfig ───────────────────────────────────────────────

    #[Test]
    public function from_config_create_returns_correct_limits(): void
    {
        $rules = UploadRules::fromConfig('create');

        $this->assertSame(5, $rules->maxFiles);
        $this->assertSame(10240, $rules->maxFileSizeKb);
        $this->assertSame(50, $rules->maxTotalSizeMb);
        $this->assertSame(10240 * 1024, $rules->maxFileSizeBytes());
        $this->assertSame(50 * 1024 * 1024, $rules->maxTotalSizeBytes());
        $this->assertSame('10 MB', $rules->maxFileSizeLabel);
        $this->assertSame('50 MB', $rules->maxTotalSizeLabel);
    }

    #[Test]
    public function from_config_reporter_edit_returns_correct_limits(): void
    {
        $rules = UploadRules::fromConfig('reporter_edit');

        $this->assertSame(5, $rules->maxFiles);
        $this->assertSame(5120, $rules->maxFileSizeKb);
        $this->assertSame(25, $rules->maxTotalSizeMb);
        $this->assertSame(5120 * 1024, $rules->maxFileSizeBytes());
        $this->assertSame(25 * 1024 * 1024, $rules->maxTotalSizeBytes());
    }

    #[Test]
    public function from_config_maintenance_returns_correct_limits(): void
    {
        $rules = UploadRules::fromConfig('maintenance');

        $this->assertSame(5, $rules->maxFiles);
        $this->assertSame(5120, $rules->maxFileSizeKb);
        $this->assertSame(25, $rules->maxTotalSizeMb);
    }

    #[Test]
    public function from_config_throws_for_unknown_profile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown upload profile: nonexistent');

        UploadRules::fromConfig('nonexistent');
    }

    #[Test]
    public function normalized_extensions_are_lowercase(): void
    {
        $rules = new UploadRules(
            maxFiles: 5,
            maxFileSizeKb: 1024,
            maxTotalSizeMb: 10,
            allowedExtensions: ['JPG', 'PNG', 'WEBP'],
            maxFileSizeLabel: '1 MB',
            maxTotalSizeLabel: '10 MB',
        );

        $this->assertSame(['jpg', 'png', 'webp'], $rules->normalizedExtensions());
    }

    // ── Empty array ───────────────────────────────────────────────────────────

    #[Test]
    public function empty_file_list_returns_no_errors(): void
    {
        $rules = UploadRules::fromConfig('create');
        $errors = $this->pipeline->validate([], $rules);

        $this->assertSame([], $errors);
    }

    // ── Valid file — happy path ───────────────────────────────────────────────

    #[Test]
    public function valid_small_jpg_produces_no_errors(): void
    {
        $rules = UploadRules::fromConfig('create');
        $files = [UploadedFile::fake()->image('foto.jpg', 100, 100)->size(500)];

        $errors = $this->pipeline->validate($files, $rules);

        $this->assertSame([], $errors);
    }

    #[Test]
    public function five_small_jpgs_produce_no_errors_for_create_profile(): void
    {
        $rules = UploadRules::fromConfig('create');
        $files = array_map(
            fn (int $i): UploadedFile => UploadedFile::fake()->image("foto{$i}.jpg")->size(100),
            range(1, 5),
        );

        $errors = $this->pipeline->validate($files, $rules);

        $this->assertSame([], $errors);
    }

    // ── max_files ─────────────────────────────────────────────────────────────

    #[Test]
    public function six_files_trigger_max_files_error(): void
    {
        $rules = UploadRules::fromConfig('create');
        $files = array_fill(0, 6, UploadedFile::fake()->image('foto.jpg')->size(10));

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertContains('max_files', $codes);

        $maxFilesError = current(array_filter($errors, fn ($e) => $e->code === 'max_files'));
        $this->assertNotFalse($maxFilesError);
        $this->assertStringContainsString('5', $maxFilesError->message);
    }

    // ── max_file_size (per-file) ──────────────────────────────────────────────

    #[Test]
    public function file_over_10mb_triggers_max_file_size_error_for_create(): void
    {
        $rules = UploadRules::fromConfig('create');
        $files = [UploadedFile::fake()->create('grande.pdf', 11 * 1024, 'application/pdf')];

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertContains('max_file_size', $codes);

        $sizeError = current(array_filter($errors, fn ($e) => $e->code === 'max_file_size'));
        $this->assertNotFalse($sizeError);
        $this->assertSame('grande.pdf', $sizeError->filename);
        $this->assertStringContainsString('10 MB', $sizeError->message);
    }

    #[Test]
    public function file_exactly_at_5mb_limit_has_no_size_error_for_reporter_profile(): void
    {
        $rules = UploadRules::fromConfig('reporter_edit');
        $files = [UploadedFile::fake()->create('doc.pdf', 5 * 1024, 'application/pdf')];

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertNotContains('max_file_size', $codes);
    }

    // ── max_total_size (array_reduce) ─────────────────────────────────────────

    #[Test]
    public function five_files_exceeding_total_50mb_trigger_max_total_size_error(): void
    {
        $rules = UploadRules::fromConfig('create');
        // 5 files × ~11MB each = ~55MB total (pipeline sees each individually then reduces)
        $files = array_fill(0, 5, UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg'));

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertContains('max_total_size', $codes);

        $totalError = current(array_filter($errors, fn ($e) => $e->code === 'max_total_size'));
        $this->assertNotFalse($totalError);
        $this->assertStringContainsString('50 MB', $totalError->message);
    }

    #[Test]
    public function three_files_within_25mb_total_have_no_total_size_error_for_reporter(): void
    {
        $rules = UploadRules::fromConfig('reporter_edit');
        $files = array_fill(0, 3, UploadedFile::fake()->create('doc.pdf', 4 * 1024, 'application/pdf'));

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertNotContains('max_total_size', $codes);
    }

    // ── invalid_extension ────────────────────────────────────────────────────

    #[Test]
    public function exe_file_triggers_invalid_extension_error(): void
    {
        $rules = UploadRules::fromConfig('create');
        $files = [UploadedFile::fake()->create('malware.exe', 50, 'application/octet-stream')];

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertContains('invalid_extension', $codes);

        $extError = current(array_filter($errors, fn ($e) => $e->code === 'invalid_extension'));
        $this->assertNotFalse($extError);
        $this->assertSame('malware.exe', $extError->filename);
        $this->assertStringContainsString('malware.exe', $extError->message);
    }

    #[Test]
    public function uppercase_jpg_extension_is_treated_as_valid(): void
    {
        // UploadedFile normalises to original extension from client name.
        // The pipeline normalises to lowercase before comparing.
        $rules = UploadRules::fromConfig('create');
        $files = [UploadedFile::fake()->image('FOTO.JPG', 100, 100)];

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertNotContains('invalid_extension', $codes);
    }

    // ── Error value objects ───────────────────────────────────────────────────

    #[Test]
    public function errors_are_instances_of_upload_validation_error(): void
    {
        $rules = UploadRules::fromConfig('create');
        $files = [UploadedFile::fake()->create('bad.exe', 50, 'application/octet-stream')];

        $errors = $this->pipeline->validate($files, $rules);

        $this->assertNotEmpty($errors);
        foreach ($errors as $error) {
            $this->assertInstanceOf(UploadValidationError::class, $error);
            $this->assertNotEmpty($error->code);
            $this->assertNotEmpty($error->message);
        }
    }

    #[Test]
    public function error_value_objects_are_readonly_immutable(): void
    {
        $error = new UploadValidationError(
            code: 'max_files',
            message: 'Puedes adjuntar hasta 5 archivos.',
            filename: null,
        );

        $this->assertSame('max_files', $error->code);
        $this->assertSame('Puedes adjuntar hasta 5 archivos.', $error->message);
        $this->assertNull($error->filename);

        // PHP 8.2 readonly: attempting to write throws Error.
        $this->expectException(\Error::class);
        // @phpstan-ignore-next-line
        $error->code = 'other';
    }

    // ── Pipeline purity — input not mutated ───────────────────────────────────

    #[Test]
    public function pipeline_does_not_mutate_the_input_array(): void
    {
        $rules = UploadRules::fromConfig('create');
        $original = [UploadedFile::fake()->image('foto.jpg')->size(100)];
        $snapshot = $original;

        $this->pipeline->validate($original, $rules);

        $this->assertSame($snapshot, $original, 'Pipeline must not modify the input array');
    }

    // ── Multiple errors in one run ────────────────────────────────────────────

    #[Test]
    public function pipeline_returns_all_errors_from_all_validators_in_one_pass(): void
    {
        $rules = UploadRules::fromConfig('create');
        // 6 files, one of which is oversized and one has a bad extension.
        $files = [
            ...array_fill(0, 4, UploadedFile::fake()->image('ok.jpg')->size(100)),
            UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf'),
            UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream'),
        ];

        $errors = $this->pipeline->validate($files, $rules);

        $codes = array_column($errors, 'code');
        $this->assertContains('max_files', $codes);
        $this->assertContains('max_file_size', $codes);
        $this->assertContains('invalid_extension', $codes);
    }
}
