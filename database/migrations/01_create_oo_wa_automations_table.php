<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('oo-auto-weave.tables.automations'), function (Blueprint $table) {
            $table->id();

            $table->string('morphable_type')->nullable()->index('oo_wa_morphable_type_idx');
            $table->string('morphable_id')->nullable()->index('oo_wa_morphable_id_idx');

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            $table->json('attributes')->nullable();
            $table->json('settings')->nullable();

            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('oo-auto-weave.tables.automations'));
    }
};
