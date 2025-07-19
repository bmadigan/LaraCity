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
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            
            // Core complaint identifiers
            $table->string('complaint_number')->unique();
            $table->string('complaint_type');
            $table->text('descriptor')->nullable();
            
            // Agency information
            $table->string('agency', 10);
            $table->string('agency_name');
            
            // Location details
            $table->string('borough')->nullable();
            $table->string('city')->default('NEW YORK');
            $table->string('incident_address')->nullable();
            $table->string('street_name')->nullable();
            $table->string('cross_street_1')->nullable();
            $table->string('cross_street_2')->nullable();
            $table->string('incident_zip', 10)->nullable();
            $table->string('address_type')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 11, 7)->nullable();
            $table->string('location_type')->nullable();
            
            // Status and priority
            $table->enum('status', ['Open', 'InProgress', 'Closed', 'Escalated'])->default('Open');
            $table->text('resolution_description')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Critical'])->default('Low');
            
            // Geographic/Administrative divisions
            $table->string('community_board')->nullable();
            $table->string('council_district')->nullable();
            $table->string('police_precinct')->nullable();
            $table->string('school_district')->nullable();
            
            // Dates
            $table->timestamp('submitted_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('due_date')->nullable();
            
            // Facility information
            $table->string('facility_type')->nullable();
            $table->string('park_facility_name')->nullable();
            $table->string('vehicle_type')->nullable();
            
            // For future vector embeddings (Phase F)
            $table->text('embedding')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['complaint_type', 'status']);
            $table->index(['borough', 'submitted_at']);
            $table->index('agency');
            $table->index('submitted_at');
            $table->index(['latitude', 'longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
