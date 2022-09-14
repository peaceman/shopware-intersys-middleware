<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateArticleNumberEanMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('article_number_ean_mappings', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('article_id');
            $table->foreign('article_id', 'anem_ai_fk')
                ->references('id')->on('articles');

            $table->string('ean', 32)
                ->unique('anem_ean_uq');

            $table->string('article_number');

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
        Schema::dropIfExists('article_number_ean_mappings');
    }
}
