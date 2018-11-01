<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderExportArticlesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('order_export_articles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('order_export_id')->unsigned();
            $table->string('sw_article_number');
            $table->dateTime('date_of_trans');
            $table->timestamps();

            $table->foreign('order_export_id')
                ->references('id')->on('order_exports')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_export_articles');
    }
}
