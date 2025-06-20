<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nip');
            $table->string('employee_name');
            $table->string('employe_gender');
            $table->integer('employee_position_id');
            $table->integer('employee_majors_id');
            $table->integer('employee_category');
            $table->string('employee_born_place');
            $table->date('employee_born_date');
            $table->string('employee_strata');
            $table->string('employee_phone');
            $table->string('employee_address');
            $table->string('employee_photo');
            $table->string('employee_start');
            $table->string('employee_end');
            $table->integer('employee_status');
            $table->string('employee_email');
            $table->string('employee_password');
            $table->integer('sekolah_id');
            $table->enum('status_absen', ['Y', 'N']);
            $table->enum('status_absen_temp', ['LOCK', 'UNLOCK']);
            $table->string('area_absen');
            $table->integer('jarak_radius');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
