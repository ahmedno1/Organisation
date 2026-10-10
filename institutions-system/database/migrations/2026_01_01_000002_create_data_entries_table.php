<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataEntriesTable extends Migration
{
    public function up()
    {
        Schema::create('data_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('administrator_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('data')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_entries');
    }
}