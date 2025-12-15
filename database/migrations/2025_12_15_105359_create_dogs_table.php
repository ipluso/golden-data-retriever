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
        Schema::create('dogs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('drc_id')->unique(); // "Rkey" aus der API

            $table->string('registration_number')->nullable()->index(); // Zuchtnummer
            $table->string('name'); // Name
            $table->string('breed')->nullable(); // Rasse
            $table->date('date_of_birth')->nullable(); // Wurfdatum
            $table->char('sex', 1)->nullable(); // Geschlecht: 'R' oder 'H'

            $table->string('hd_score')->nullable(); // HD Grad
            $table->string('ed_score')->nullable(); // ED Grad

            $table->integer('zw_hd')->nullable(); // ZW HD
            $table->integer('zw_ed')->nullable(); // ZW ED
            $table->integer('zw_hc')->nullable(); // ZW HC

            $table->integer('offspring_count')->default(0); // Anzahl Nachkommen

            $table->json('genetic_tests')->nullable();      // Alle 'CondGT'
            $table->json('eye_exams')->nullable();          // Alle 'CondAU', 'CondRD', 'CondHC'
            $table->json('orthopedic_details')->nullable(); // Alle 'CondOA'
            $table->json('work_exams')->nullable();         // Alle 'CondJG', 'CondTI'

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dogs');
    }
};
