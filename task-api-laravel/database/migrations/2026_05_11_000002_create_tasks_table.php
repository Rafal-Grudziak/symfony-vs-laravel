<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('todo');
            $table->string('priority', 32)->default('medium');
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('due_date');
            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
