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
        Schema::create('complaint_analyses', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to complaints
            $table->foreignId('complaint_id')->constrained()->onDelete('cascade');
            
            // AI-generated insights
            $table->text('summary')->nullable();
            $table->decimal('risk_score', 3, 2)->default(0.0); // 0.0-1.0 scale
            $table->string('category')->nullable();
            $table->json('tags')->nullable(); // JSON array of extracted tags
            
            $table->timestamps();
            
            // Indexes
            $table->index('complaint_id');
            $table->index('risk_score');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaint_analyses');
    }
};
