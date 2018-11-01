<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropUniqueConstraints extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('manufacturer_size_mappings', function (Blueprint $table) {
            $table->index('manufacturer_id', 'idx_m');
            $table->dropUnique('uq_mgss');
        });

        Schema::table('manufacturers', function (Blueprint $table) {
            $table->dropUnique('manufacturers_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('manufacturer_size_mappings', function (Blueprint $table) {
            $table->unique(['manufacturer_id', 'gender', 'source_size'], 'uq_mgss');
        });

        Schema::table('manufacturers', function (Blueprint $table) {
            $table->unique('name');
        });
    }
}
