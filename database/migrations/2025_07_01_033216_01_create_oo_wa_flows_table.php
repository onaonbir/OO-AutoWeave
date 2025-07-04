<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('oo-auto-weave.tables.flows'), function (Blueprint $table) {
            $table->id();
            $table->string('morphable_type')->nullable()->index('oo_wa_f_m_type_idx');
            $table->string('morphable_id')->nullable()->index('oo_wa_f_m_id_idx');
            $table->string('key')->unique();
            $table->string('name');
            $table->json('structure');
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('oo-auto-weave.tables.flows'));
    }
};
