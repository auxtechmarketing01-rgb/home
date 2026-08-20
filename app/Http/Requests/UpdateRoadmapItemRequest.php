<?php

namespace App\Http\Requests;

use App\Models\RoadmapItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoadmapItemRequest extends FormRequest
{
    /**
     * The route already runs the `update` ability through
     * RoadmapItemPolicy, which is deliberately distinct from `assign`
     * (FR-MENT-06).
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('roadmap_items', 'id')->where('roadmap_id', $this->item()->roadmap_id),
            ],
            'day_number' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'scheduled_date' => ['sometimes', 'nullable', 'date'],
            'estimated_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000'],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'done', 'skipped'])],
            'reflection_note' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('parent_id')) {
                return;
            }

            $parentId = $this->input('parent_id');

            if ($parentId === null) {
                return;
            }

            if ((int) $parentId === $this->item()->id) {
                $validator->errors()->add('parent_id', 'A roadmap item cannot be its own parent.');

                return;
            }

            $parent = RoadmapItem::query()->whereKey($parentId)->first();

            if ($parent !== null && $parent->parent_id !== null) {
                $validator->errors()->add(
                    'parent_id',
                    'Roadmap items may only be nested one level deep.'
                );
            }
        });
    }

    protected function item(): RoadmapItem
    {
        return $this->route('item');
    }
}
