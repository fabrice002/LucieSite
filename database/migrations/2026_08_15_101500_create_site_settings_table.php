<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();

            // La clé est l'identifiant stable, citée dans les vues et le code.
            $table->string('key')->unique();

            // Tout est stocké en texte, converti à la lecture selon « type » :
            // une couleur, un chemin de fichier et un booléen n'ont pas besoin
            // de trois colonnes distinctes.
            $table->text('value')->nullable();
            $table->string('type')->default('texte');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
