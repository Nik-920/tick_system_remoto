<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Functional\UploadRules;
use App\Support\Functional\UploadValidationPipeline;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Shared upload validation logic for ticket Form Requests.
 *
 * Centralises the three patterns that were copy-pasted across StoreTicketRequest,
 * UpdateReporterTicketRequest and UpdateMaintenanceTicketRequest:
 *
 *  - Building the array + per-file rules from an UploadRules profile.
 *  - Running UploadValidationPipeline after Laravel's built-in rules.
 *  - Sanitising plain-text inputs before validation.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesTicketUploads
{
    /**
     * Return the Laravel validation rules for a file-upload field.
     *
     * @param  list<string>  $extraFileRules  Additional per-file rules (e.g. mimetypes:...).
     * @return array<string, array<int, string>>
     */
    private function uploadFileRules(
        string $field,
        string $profile,
        string $arrayPresence = 'nullable',
        array $extraFileRules = []
    ): array {
        $upload = UploadRules::fromConfig($profile);

        return [
            $field => [$arrayPresence, 'array', 'max:'.$upload->maxFiles],
            $field.'.*' => [
                'file',
                'max:'.$upload->maxFileSizeKb,
                'mimes:'.implode(',', $upload->normalizedExtensions()),
                ...$extraFileRules,
            ],
        ];
    }

    /**
     * Run the functional upload pipeline inside a validator after() callback.
     *
     * Replaces the ~20-line copy-pasted block in withValidator(). The guard skips
     * the max_files pipeline error when Laravel's array 'max' rule already added
     * one to the same field key, preventing duplicate messages.
     */
    private function runUploadPipelineAfter(string $field, string $profile, Validator $validator): void
    {
        /** @var list<UploadedFile> $files */
        $files = array_values(array_filter(
            (array) $this->file($field, []),
            fn (mixed $f): bool => $f instanceof UploadedFile,
        ));

        if ($files === []) {
            return;
        }

        $rules = UploadRules::fromConfig($profile);
        $errors = (new UploadValidationPipeline)->validate($files, $rules);

        foreach ($errors as $error) {
            if ($error->code === 'max_files' && $validator->errors()->has($field)) {
                continue;
            }
            $validator->errors()->add($field, $error->message);
        }
    }

    private function sanitizePlainText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(strip_tags($value));

        return $clean === '' ? null : $clean;
    }
}
