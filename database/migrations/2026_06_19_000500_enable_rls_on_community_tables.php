<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE community_reactions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE community_saves ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE community_moderation_logs ENABLE ROW LEVEL SECURITY');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE community_reactions DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE community_saves DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE community_moderation_logs DISABLE ROW LEVEL SECURITY');
    }
};
