<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('oo-auto-weave.tables.actions'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('action_set_id')
                ->constrained(config('oo-auto-weave.tables.action_sets'))
                ->onDelete('cascade');

            $table->string('type');
            $table->string('branch_type')->nullable()->default('default');
            $table->json('parameters')->nullable();
            $table->integer('order')->default(0);
            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('oo-auto-weave.tables.actions'));
    }
};
