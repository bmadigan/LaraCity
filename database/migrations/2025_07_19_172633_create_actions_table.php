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
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            
            // Action details
            $table->enum('type', [
                'escalate', 
                'summarize', 
                'notify', 
                'analyze',
                'analysis_triggered',
                'status_change',
                'complaint_deleted',
                'complaint_restored'
            ]);
            $table->json('parameters'); // JSON of action context
            $table->string('triggered_by'); // user_id or 'system'
            
            // Optional reference to related complaint
            $table->foreignId('complaint_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['type', 'created_at']);
            $table->index('triggered_by');
            $table->index('complaint_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actions');
    }
};
