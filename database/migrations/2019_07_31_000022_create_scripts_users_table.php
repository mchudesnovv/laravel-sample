<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateScriptUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('script_user', function (Blueprint $table) {
            $table->unsignedInteger('script_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();

            $table->foreign('script_id')
                ->references('id')
                ->on('scripts')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
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
        Schema::table('script_user', function (Blueprint $table) {
            $table->dropForeign(['script_id', 'user_id']);
        });

        Schema::dropIfExists('script_user');
    }
}
