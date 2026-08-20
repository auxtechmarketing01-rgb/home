<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OverviewRequest extends FormRequest
{
    /**
     * No Policy: `/analytics/overview` is scoped to the acting member inside
     * AnalyticsService (02 §4).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'trend_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
        ];
    }
}
