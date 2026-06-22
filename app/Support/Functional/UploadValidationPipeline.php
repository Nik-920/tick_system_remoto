<?php

declare(strict_types=1);

namespace App\Support\Functional;

use Illuminate\Http\UploadedFile;

/**
 * Functional pipeline that validates a list of uploaded files against UploadRules.
 *
 * Core functional concepts applied (PHP 8.2):
 *
 *  - Callables / closures as first-class values stored in an array of validators.
 *  - Arrow functions (fn) for concise, expression-bodied transformations.
 *  - array_map   — applies each validator callable to the file list.
 *  - array_filter — removes null placeholders from per-file error arrays.
 *  - array_reduce — accumulates total byte count without mutation.
 *  - Immutable readonly value objects (UploadRules, UploadValidationError).
 *
 * @see https://www.php.net/manual/en/language.types.callable.php
 * @see https://www.php.net/manual/en/functions.arrow.php
 * @see https://www.php.net/manual/en/function.array-map.php
 * @see https://www.php.net/manual/en/function.array-filter.php
 * @see https://www.php.net/manual/en/function.array-reduce.php
 */
final class UploadValidationPipeline
{
    /**
     * Run the validation pipeline over a list of uploaded files.
     *
     * The pipeline is built as an array of closures — each one is a pure
     * validator that maps (files, rules) → list<UploadValidationError>.
     * array_map applies every validator and the results are merged flat.
     *
     * The original $files array is never modified (no mutation).
     *
     * @param  list<UploadedFile>  $files
     * @return list<UploadValidationError>
     */
    public function validate(array $files, UploadRules $rules): array
    {
        if ($files === []) {
            return [];
        }

        // ── Heart of the functional pipeline ──────────────────────────────────
        // Each element is a callable (arrow function) that captures $rules via
        // PHP's implicit closure binding — no shared mutable state.
        $validators = [
            fn (array $f): array => $this->validateMaxFiles($f, $rules),
            fn (array $f): array => $this->validateEachFileSize($f, $rules),
            fn (array $f): array => $this->validateTotalSize($f, $rules),
            fn (array $f): array => $this->validateExtensions($f, $rules),
            fn (array $f): array => $this->validateUploadState($f),
        ];

        // array_map applies each callable validator to the file list,
        // producing a list<list<UploadValidationError>>.
        // Spread (...) + array_merge flattens to list<UploadValidationError>.
        return array_values(array_merge(
            ...array_map(
                fn (callable $validator): array => $validator($files),
                $validators,
            )
        ));
    }

    // ── Validator implementations ─────────────────────────────────────────────

    /** @param list<UploadedFile> $files */
    private function validateMaxFiles(array $files, UploadRules $rules): array
    {
        if (count($files) <= $rules->maxFiles) {
            return [];
        }

        return [new UploadValidationError(
            code: 'max_files',
            message: "Puedes adjuntar hasta {$rules->maxFiles} archivos.",
        )];
    }

    /**
     * Per-file size check using array_filter to strip nulls then array_map
     * to produce error objects — a pure functional transform.
     *
     * @param  list<UploadedFile>  $files
     * @return list<UploadValidationError>
     */
    private function validateEachFileSize(array $files, UploadRules $rules): array
    {
        $limit = $rules->maxFileSizeBytes();

        // array_map produces a sparse list (null for valid files).
        // array_filter removes nulls, leaving only error objects.
        return array_values(array_filter(
            array_map(
                fn (UploadedFile $file): ?UploadValidationError => $file->getSize() > $limit
                    ? new UploadValidationError(
                        code: 'max_file_size',
                        message: "El archivo \"{$file->getClientOriginalName()}\" supera el límite de {$rules->maxFileSizeLabel}.",
                        filename: $file->getClientOriginalName(),
                    )
                    : null,
                $files,
            )
        ));
    }

    /**
     * Total size check using array_reduce to accumulate bytes immutably.
     *
     * array_reduce replaces a mutable $total variable with a pure fold:
     * (carry, file) -> carry + file.size, starting from 0.
     *
     * @param  list<UploadedFile>  $files
     * @return list<UploadValidationError>
     */
    private function validateTotalSize(array $files, UploadRules $rules): array
    {
        $totalBytes = $this->totalBytes($files);

        if ($totalBytes <= $rules->maxTotalSizeBytes()) {
            return [];
        }

        return [new UploadValidationError(
            code: 'max_total_size',
            message: "El total de archivos supera el límite de {$rules->maxTotalSizeLabel}.",
        )];
    }

    /**
     * Extension check — normalises to lowercase before comparing.
     *
     * array_map extracts extensions; array_filter removes valid ones;
     * the remaining files each get an error object.
     *
     * @param  list<UploadedFile>  $files
     * @return list<UploadValidationError>
     */
    private function validateExtensions(array $files, UploadRules $rules): array
    {
        $allowed = $rules->normalizedExtensions();

        return array_values(array_filter(
            array_map(
                function (UploadedFile $file) use ($allowed): ?UploadValidationError {
                    $ext = strtolower((string) $file->getClientOriginalExtension());

                    return in_array($ext, $allowed, true)
                        ? null
                        : new UploadValidationError(
                            code: 'invalid_extension',
                            message: "El archivo \"{$file->getClientOriginalName()}\" no es compatible.",
                            filename: $file->getClientOriginalName(),
                        );
                },
                $files,
            )
        ));
    }

    /**
     * UploadedFile::isValid() ensures the PHP upload itself succeeded
     * (no upload error, file is readable).
     *
     * @param  list<UploadedFile>  $files
     * @return list<UploadValidationError>
     */
    private function validateUploadState(array $files): array
    {
        return array_values(array_filter(
            array_map(
                fn (UploadedFile $file): ?UploadValidationError => $file->isValid()
                    ? null
                    : new UploadValidationError(
                        code: 'invalid_upload',
                        message: "No se pudo subir el archivo \"{$file->getClientOriginalName()}\". Verifica que no supere el tamaño permitido.",
                        filename: $file->getClientOriginalName(),
                    ),
                $files,
            )
        ));
    }

    // ── Pure helper ───────────────────────────────────────────────────────────

    /**
     * Accumulate total bytes using array_reduce — no mutation, no loop variable.
     *
     * @param  list<UploadedFile>  $files
     */
    private function totalBytes(array $files): int
    {
        return array_reduce(
            $files,
            fn (int $carry, UploadedFile $file): int => $carry + (int) $file->getSize(),
            0,
        );
    }
}
