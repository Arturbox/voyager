<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDataTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create table for storing roles
        Schema::create('data_types', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('display_name_singular');
            $table->string('display_name_plural');
            $table->string('icon')->nullable();
            $table->string('model_name')->nullable();
            $table->string('description')->nullable();
            $table->boolean('generate_permissions')->default(false);
            $table->boolean('show_filters')->default(false);
            $table->boolean('filter_browse')->default(false);
            $table->boolean('filter_read')->default(false);
            $table->boolean('filter_update')->default(false);
            $table->boolean('filter_add')->default(false);
            $table->boolean('child_redirect')->default(false);
            $table->timestamps();
        });

        // Create table for storing roles
        Schema::create('data_rows', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('data_type_id')->unsigned();
            $table->string('field');
            $table->string('type');
            $table->string('display_name');
            $table->boolean('required')->default(false);
            $table->boolean('browse')->default(true);
            $table->boolean('read')->default(true);
            $table->boolean('edit')->default(true);
            $table->boolean('add')->default(true);
            $table->boolean('delete')->default(true);
            $table->text('details')->nullable();

            $table->foreign('data_type_id')->references('id')->on('data_types')
                ->onUpdate('cascade')->onDelete('cascade');
        });


        // Create table for storing roles
        Schema::create('data_filters', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('data_type_id')->unsigned();
            $table->integer('data_type_parent_id')->unsigned();
            $table->integer('parent_id')->unsigned()->nullable();
            $table->string('display_field');
            $table->string('display_name');
            $table->boolean('required')->default(false);
            $table->text('details')->nullable();
            $table->integer('order');

            $table->foreign('data_type_id')->references('id')->on('data_types')
                ->onUpdate('cascade')->onDelete('cascade');
            $table->foreign('data_type_parent_id')->references('id')->on('data_types')
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
        Schema::drop('data_filters');
        Schema::drop('data_rows');
        Schema::drop('data_types');
    }
}
