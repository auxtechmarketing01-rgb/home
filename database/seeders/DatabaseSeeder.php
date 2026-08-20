<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Lookup rows the app depends on are always seeded; the demo fixture is
     * local-development only.
     */
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            BadgeSeeder::class,
        ]);

        if (! app()->environment('production')) {
            $this->call([
                DemoSeeder::class,
            ]);
        }
    }
}
