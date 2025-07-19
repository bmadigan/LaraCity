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
        Schema::create('user_questions', function (Blueprint $table) {
            $table->id();
            
            // Question and response
            $table->text('question'); // Raw user input
            $table->json('parsed_filters')->nullable(); // Extracted filters JSON
            $table->text('ai_response')->nullable(); // Generated answer from RAG system
            
            // For RAG similarity search (Phase F)
            $table->text('embedding')->nullable(); // Question embedding
            
            // Chat session tracking
            $table->string('conversation_id')->nullable(); // For multi-turn chat sessions
            
            // User tracking
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index('conversation_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_questions');
    }
};
