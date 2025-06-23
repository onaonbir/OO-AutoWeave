<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('oo-auto-weave.tables.triggers'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('automation_id')
                ->nullable()
                ->constrained(config('oo-auto-weave.tables.automations'))
                ->onDelete('cascade');
            $table->string('label')->nullable();

            $table->string('morphable_type')->nullable()->index('oo_wa_t_morphable_type_idx');
            $table->string('morphable_id')->nullable()->index('oo_wa_t_morphable_id_idx');

            $table->string('key');
            $table->string('group');
            $table->string('type');

            $table->json('settings')->nullable();

            $table->integer('order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('oo-auto-weave.tables.triggers'));
    }
};
