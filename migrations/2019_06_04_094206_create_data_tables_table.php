<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDataTablesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_tables', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('data_type_id')->unsigned();
            $table->string('name');
            $table->boolean('browse')->default(true);
            $table->boolean('read')->default(true);
            $table->boolean('edit')->default(true);
            $table->boolean('add')->default(true);
            $table->boolean('delete')->default(true);
            $table->text('details')->nullable();

            $table->foreign('data_type_id')->references('id')->on('data_types')
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
        Schema::dropIfExists('data_tables');
    }
}
