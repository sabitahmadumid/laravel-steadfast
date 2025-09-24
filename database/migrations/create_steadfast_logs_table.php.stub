<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steadfast_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('endpoint', 255)->index();
            $table->unsignedSmallInteger('status_code')->index();
            $table->text('error')->nullable();
            $table->timestamps();
            
            // Add indexes for better query performance
            $table->index(['type', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steadfast_logs');
    }
};
