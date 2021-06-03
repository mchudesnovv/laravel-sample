<?php

use App\ScriptInstance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScriptInstancesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('script_instances', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('script_id')->nullable();
            $table->string('tag_name')->nullable();
            $table->string('tag_user_email')->nullable();
            $table->string('aws_instance_id')->nullable();
            $table->string('aws_public_ip')->nullable();
            $table->unsignedInteger('aws_region_id')->nullable();
            $table->unsignedInteger('up_time')->default(0);
            $table->unsignedInteger('total_up_time')->default(0);
            $table->unsignedInteger('cron_up_time')->default(0);
            $table->boolean('is_in_queue')->default(1);

            $table->enum('aws_status', [
                ScriptInstance::STATUS_PENDING,
                ScriptInstance::STATUS_RUNNING,
                ScriptInstance::STATUS_STOPPED,
                ScriptInstance::STATUS_TERMINATED
            ])->default(ScriptInstance::STATUS_PENDING);

            $table->enum('status', [
                'active', 'inactive'
            ])->default('active');

            $table->timestamp('start_time')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('script_id')
                ->references('id')
                ->on('scripts')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('aws_region_id')
                ->references('id')
                ->on('aws_regions')
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
        Schema::table('script_instances', function (Blueprint $table) {
            $table->dropForeign(['user_id', 'script_id', 'aws_region_id']);
        });

        Schema::dropIfExists('script_instances');
    }
}
