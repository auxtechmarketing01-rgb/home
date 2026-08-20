<?php

namespace App\Http\Requests;

use App\Services\LeaderboardService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeaderboardRequest extends FormRequest
{
    /**
     * The route runs `view` on the Group through GroupPolicy.
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
            'period' => ['sometimes', Rule::in(LeaderboardService::PERIODS)],
        ];
    }
}
