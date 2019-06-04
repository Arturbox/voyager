<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDataTableRowsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('data_table_rows', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('data_table_id')->unsigned();
            $table->string('name');
            $table->string('type');
            $table->text('details');
            $table->integer('order');

            $table->foreign('data_table_id')->references('id')->on('data_tables')
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
        Schema::dropIfExists('data_table_rows');
    }
}
