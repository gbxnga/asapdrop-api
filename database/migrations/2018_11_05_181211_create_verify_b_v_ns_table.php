<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateVerifyBVNsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('verify_b_v_ns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('bvn',20);
            $table->string('user_id');
            $table->string('phone', 20);
            $table->string('code', 10);
            $table->enum('status',['verified','unverified'])->default('unverified');
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
        Schema::dropIfExists('verify_b_v_ns');
    }
}
