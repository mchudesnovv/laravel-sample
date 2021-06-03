<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
         $this->call([
             AwsRegionsTableSeeder::class,
             TimezonesTableSeeder::class,
             DemoScriptSeeder::class,
             ScriptsTableSeeder::class,
             AwsAmisTableSeeder::class,
             AwsSettingsTableSeeder::class,
             AddUserSeeder::class,
         ]);
    }
}
