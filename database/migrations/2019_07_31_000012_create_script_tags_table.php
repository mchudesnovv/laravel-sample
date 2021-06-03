<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScriptTagsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('script_tag', function (Blueprint $table) {
            $table->unsignedInteger('script_id')->nullable();
            $table->unsignedInteger('tag_id')->nullable();

            $table->foreign('script_id')
                ->references('id')
                ->on('scripts')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('tag_id')
                ->references('id')
                ->on('tags')
                ->onUpdate('cascade')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('script_tag', function (Blueprint $table) {
            $table->dropForeign(['script_id', 'tag_id']);
        });

        Schema::dropIfExists('script_tag');
    }
}
