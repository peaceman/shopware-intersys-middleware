<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('sw_order_id')->unique();
            $table->string('sw_order_number')->unique();
            $table->dateTime('sw_order_time');
            $table->integer('sw_order_status_id');
            $table->integer('sw_payment_status_id');
            $table->integer('sw_payment_id');
            $table->dateTime('notified_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->timestamps();

            $table->index('notified_at', 'idx_notified_at');
            $table->index(['cancelled_at', 'created_at'], 'idx_cancellation');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
