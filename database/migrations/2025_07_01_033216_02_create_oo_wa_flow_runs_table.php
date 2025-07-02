<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('oo-auto-weave.tables.flow_runs'), function (Blueprint $table) {
            $table->id();
            $table->string('morphable_type')->nullable()->index('oo_wa_fr_m_type_idx');
            $table->string('morphable_id')->nullable()->index('oo_wa_fr_m_id_idx');
            $table->string('name');
            $table->json('base_structure');
            $table->json('node_states')->nullable();
            $table->json('context')->nullable();
            $table->string('status')->default('running');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('oo-auto-weave.tables.flow_runs'));
    }
};
