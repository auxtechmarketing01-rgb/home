<?php

namespace App\Http\Requests;

use App\Models\Roadmap;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderRoadmapItemsRequest extends FormRequest
{
    /**
     * The route already runs the `update` ability on the roadmap's Goal.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The `exists` rule is scoped to this roadmap so an id belonging to
     * someone else's roadmap fails validation; ReorderRoadmapItemsAction
     * re-checks the same invariant inside its transaction (04 Phase 1).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('roadmap_items', 'id')->where('roadmap_id', $this->roadmap()->id),
            ],
            'items.*.position' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function roadmap(): Roadmap
    {
        return $this->route('roadmap');
    }
}
