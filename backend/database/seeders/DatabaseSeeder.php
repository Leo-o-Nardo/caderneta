<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Popula o banco da aplicação.
     *
     * Sem o WithoutModelEvents que vinha no Laravel: ele desligaria os eventos dos modelos, e o
     * household_id dos registros é preenchido justamente num evento (BelongsToHousehold).
     */
    public function run(): void
    {
        $this->call(DemoHouseholdSeeder::class);
    }
}
