<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBvnVerifiedToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
        Schema::table('users', function (Blueprint $table) {
 
            $table->enum('bvn_verified',['false','true'])->default('false');
            $table->enum('license_verified',['false','true'])->default('false');
            $table->enum('phone_verified',['false','true'])->default('false'); 
            $table->enum('facebook_verified',['false','true'])->default('false');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
}
