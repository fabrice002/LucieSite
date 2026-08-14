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
        Schema::create('application_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            // Le compte de l'auteur peut disparaître ; la mise à jour, elle, reste.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Null : message adressé au candidat sans changement de statut.
            $table->string('status')->nullable();
            // Visible par le candidat sur la page de suivi. Distinct de
            // applications.internal_notes, qui reste strictement privé.
            $table->text('public_message')->nullable();
            $table->boolean('email_sent')->default(false);
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_updates');
    }
};
