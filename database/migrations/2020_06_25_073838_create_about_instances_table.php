<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAboutInstancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('about_instances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('instance_id')->index();
            $table->string('tag_name')->nullable();
            $table->string('tag_user_email')->nullable();
            $table->string('script_path')->nullable();
            $table->string('script_name')->nullable();
            $table->string('aws_region')->nullable();
            $table->string('aws_instance_type')->nullable();
            $table->unsignedSmallInteger('aws_storage_gb')->nullable();
            $table->string('aws_image_id')->nullable();
            $table->json('params');
            $table->string('s3_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('instance_id')
                ->references('id')
                ->on('script_instances')
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
        Schema::table('about_instances', function (Blueprint $table) {
            $table->dropForeign(['instance_id']);
        });

        Schema::dropIfExists('about_instances');
    }
}
