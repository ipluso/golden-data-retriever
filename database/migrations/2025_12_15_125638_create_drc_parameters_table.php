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
        Schema::create('drc_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('param_key')->unique();
            $table->string('category');
            $table->string('description');
            $table->string('label')->nullable();
            $table->string('target_column');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drc_parameters');
    }
};
