<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update all existing 'user' roles to 'operator'
        DB::table('users_management')
            ->where('role', 'user')
            ->update(['role' => 'operator']);
            
        // PostgreSQL compatible enum modification
        // Drop the old constraint and add new one
        DB::statement("ALTER TABLE users_management DROP CONSTRAINT IF EXISTS users_management_role_check");
        DB::statement("ALTER TABLE users_management ADD CONSTRAINT users_management_role_check CHECK (role::text = ANY (ARRAY['supervisor'::character varying, 'operator'::character varying]::text[]))");
        DB::statement("ALTER TABLE users_management ALTER COLUMN role SET DEFAULT 'operator'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert operators back to users (except supervisors)
        DB::table('users_management')
            ->where('role', 'operator')
            ->update(['role' => 'user']);
            
        // Add 'user' back to enum constraint
        DB::statement("ALTER TABLE users_management DROP CONSTRAINT IF EXISTS users_management_role_check");
        DB::statement("ALTER TABLE users_management ADD CONSTRAINT users_management_role_check CHECK (role::text = ANY (ARRAY['supervisor'::character varying, 'operator'::character varying, 'user'::character varying]::text[]))");
        DB::statement("ALTER TABLE users_management ALTER COLUMN role SET DEFAULT 'user'");
    }
};
