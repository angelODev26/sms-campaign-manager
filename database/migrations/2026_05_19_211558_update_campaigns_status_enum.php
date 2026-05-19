<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE campaigns MODIFY COLUMN status ENUM('draft', 'processing', 'scheduled', 'running', 'completed') DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE campaigns MODIFY COLUMN status ENUM('draft', 'scheduled', 'running', 'completed') DEFAULT 'draft'");
    }
};
