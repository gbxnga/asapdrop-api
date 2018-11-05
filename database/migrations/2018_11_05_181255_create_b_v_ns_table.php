<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBVNsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bvns', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('bvn');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('dob');
            $table->string('mobile');
            $table->string('formatted_dob');
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
        Schema::dropIfExists('b_v_ns');
    }
}
