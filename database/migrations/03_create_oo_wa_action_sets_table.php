<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('oo-auto-weave.tables.action_sets'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('trigger_id')
                ->nullable()
                ->constrained(config('oo-auto-weave.tables.triggers'))
                ->onDelete('cascade');

            $table->string('execution_type')
                ->default('default'); // default | ruled
            $table->json('rules')->nullable();
            $table->json('settings')->nullable();
            $table->integer('order')->default(0);
            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('oo-auto-weave.tables.action_sets'));
    }
};
