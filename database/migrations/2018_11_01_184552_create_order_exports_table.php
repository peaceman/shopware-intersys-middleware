<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderExportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_exports', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 8);
            $table->string('sw_order_number');
            $table->integer('sw_order_id')->unsigned();
            $table->string('storage_path');
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
        Schema::dropIfExists('order_exports');
    }
}
