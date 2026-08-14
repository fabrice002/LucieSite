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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            // Chaque programme d'immigration a sa page, référencée séparément :
            // c'est ce qui apporte le trafic organique dans ce secteur.
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('icon')->nullable();
            $table->string('highlight')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
