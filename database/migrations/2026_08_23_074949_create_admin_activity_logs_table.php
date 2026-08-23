<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->onDelete('cascade');
            $table->string('action'); // e.g. 'create_user', 'toggle_active', 'reset_password', 'update_user'
            $table->foreignId('target_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->json('details')->nullable(); // additional context: { old_role, new_role, target_name, etc. }
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_activity_logs');
    }
};
