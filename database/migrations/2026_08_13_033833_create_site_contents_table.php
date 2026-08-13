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
        Schema::create('site_contents', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('locale', 10)->default('fr');
            $table->string('label');
            $table->json('content');
            $table->timestamps();

            // Un bloc est identifié par sa clé pour une langue donnée. Ajouter une
            // langue revient à insérer de nouvelles lignes, sans migration.
            $table->unique(['key', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_contents');
    }
};
