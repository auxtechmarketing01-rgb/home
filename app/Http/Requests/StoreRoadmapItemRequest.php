<?php

namespace App\Http\Requests;

use App\Models\Roadmap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRoadmapItemRequest extends FormRequest
{
    /**
     * The route already runs the `update` ability on the roadmap's Goal.
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('roadmap_items', 'id')->where('roadmap_id', $this->roadmap()->id),
            ],
            'day_number' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'scheduled_date' => ['nullable', 'date'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done', 'skipped'])],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * FR-RM-03 allows exactly one level of nesting, so a parent must itself
     * be a top-level item.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->input('parent_id');

            if ($parentId === null) {
                return;
            }

            $parent = $this->roadmap()->items()->whereKey($parentId)->first();

            if ($parent !== null && $parent->parent_id !== null) {
                $validator->errors()->add(
                    'parent_id',
                    'Roadmap items may only be nested one level deep.'
                );
            }
        });
    }

    protected function roadmap(): Roadmap
    {
        return $this->route('roadmap');
    }
}
