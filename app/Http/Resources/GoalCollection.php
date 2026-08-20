<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class GoalCollection extends ResourceCollection
{
    /**
     * @var class-string<GoalResource>
     */
    public $collects = GoalResource::class;
}
