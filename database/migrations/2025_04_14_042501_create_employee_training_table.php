<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeeTrainingTable extends Migration
{
    public function up()
    {
        Schema::create('employee_training', function (Blueprint $table) {
            $table->id(); // PK
            $table->string('employee_nik');
            $table->unsignedBigInteger('training_id');
            $table->string('sertifikat')->nullable();

            $table->foreign('employee_nik')->references('nik')->on('employees')->onDelete('cascade');
            $table->foreign('training_id')->references('id')->on('trainings')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employee_training');
    }
}
