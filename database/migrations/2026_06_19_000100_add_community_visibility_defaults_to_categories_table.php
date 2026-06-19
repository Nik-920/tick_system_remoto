<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('community_default_visible')->default(true)->after('description');
            $table->boolean('community_visibility_locked')->default(false)->after('community_default_visible');
            $table->text('community_visibility_help')->nullable()->after('community_visibility_locked');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn([
                'community_default_visible',
                'community_visibility_locked',
                'community_visibility_help',
            ]);
        });
    }
};
