<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Support\Dashboard\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

/**
 * Validates the date-range selector shared by the maintenance dashboard and
 * the PDF report. All inputs are optional; absence resolves to the default
 * range (last 30 days). Authorization is handled by the route middleware.
 */
class DashboardDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'preset' => ['nullable', 'string', 'in:'.implode(',', DateRange::SELECTABLE_PRESETS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'preset.in' => 'El rango seleccionado no es valido.',
            'from.date' => 'La fecha inicial no es valida.',
            'to.date' => 'La fecha final no es valida.',
            'to.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
                return;
            }

            try {
                $days = (int) CarbonImmutable::parse($from)->startOfDay()
                    ->diffInDays(CarbonImmutable::parse($to)->startOfDay()) + 1;
            } catch (Throwable) {
                return; // The date rules already reject unparseable values.
            }

            if ($days > DateRange::MAX_DAYS) {
                $validator->errors()->add('to', 'El rango no puede exceder '.DateRange::MAX_DAYS.' dias.');
            }
        });
    }

    public function toDateRange(): DateRange
    {
        return DateRange::resolve(
            $this->stringOrNull('preset'),
            $this->stringOrNull('from'),
            $this->stringOrNull('to'),
        );
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : null;
    }
}
