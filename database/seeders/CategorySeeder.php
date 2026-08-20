<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * The default global set from FR-GOAL-05. A null `user_id` is what marks
     * a category as global; user-defined ones always carry an owner.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Programming', 'icon' => 'code-bracket'],
            ['name' => 'Fitness', 'icon' => 'bolt'],
            ['name' => 'Language', 'icon' => 'language'],
            ['name' => 'Reading', 'icon' => 'book-open'],
            ['name' => 'Other', 'icon' => 'squares-2x2'],
        ];

        foreach ($categories as $category) {
            Category::query()->firstOrCreate(
                ['user_id' => null, 'name' => $category['name']],
                ['icon' => $category['icon']],
            );
        }
    }
}
