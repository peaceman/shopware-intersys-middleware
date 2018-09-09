<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateManufacturersSizeMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('manufacturer_size_mappings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('manufacturer_id');

            $table->string('gender');
            $table->string('source_size');
            $table->string('target_size');

            $table->foreign('manufacturer_id', 'fk_msm_m_id')->references('id')->on('manufacturers')
                ->onUpdate('cascade')->onDelete('cascade');

            $table->unique(['manufacturer_id', 'gender', 'source_size'], 'uq_mgss');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('manufacturer_size_mappings');
    }
}
