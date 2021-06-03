<?php

use App\Script;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScriptsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('scripts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('parameters')->nullable();
            $table->text('path')->nullable();
            $table->string('s3_path')->nullable();

            $table->enum('status', [
                Script::STATUS_ACTIVE,
                Script::STATUS_INACTIVE
            ])->default(Script::STATUS_ACTIVE);

            $table->enum('type', [
                Script::TYPE_PUBLIC,
                Script::TYPE_PRIVATE
            ])->default(Script::TYPE_PUBLIC);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('scripts');
    }
}
