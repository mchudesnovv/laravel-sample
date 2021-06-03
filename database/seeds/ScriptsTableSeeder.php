<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class ScriptsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Artisan::call('script:sync-s3');
    }
}
