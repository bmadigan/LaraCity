<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->id();
            
            // Document identification and metadata
            $table->string('document_type'); // 'complaint', 'user_question', 'analysis'
            $table->unsignedBigInteger('document_id')->nullable(); // ID of the source document
            $table->string('document_hash', 64); // SHA256 hash of content for deduplication
            
            // Content information
            $table->text('content'); // The text content that was embedded
            $table->json('metadata')->nullable(); // Additional context (source, version, etc.)
            
            // Embedding information
            $table->string('embedding_model', 100); // e.g., 'text-embedding-3-small'
            $table->integer('embedding_dimension'); // Vector dimension (1536 for text-embedding-3-small)
            
            // Indexing and performance
            $table->index(['document_type', 'document_id'], 'doc_embeddings_type_id_idx');
            $table->index('document_hash', 'doc_embeddings_hash_idx');
            $table->index('embedding_model', 'doc_embeddings_model_idx');
            
            $table->timestamps();
        });
        
        // Add vector column using raw SQL since Laravel doesn't support vector type natively
        DB::statement('ALTER TABLE document_embeddings ADD COLUMN embedding vector(1536)');
        
        // Create HNSW index for fast similarity search
        DB::statement('CREATE INDEX ON document_embeddings USING hnsw (embedding vector_cosine_ops)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');
    }
};
