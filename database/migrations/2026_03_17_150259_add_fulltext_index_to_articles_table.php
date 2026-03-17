<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        // Add the FULLTEXT index that was skipped when the driver was reported
        // as 'mysql' only — Laravel 11+ reports MariaDB as 'mariadb' separately.
        DB::statement('CREATE FULLTEXT INDEX ft_search ON articles(title, description, content)');
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'])) {
            return;
        }

        if (Schema::hasTable('articles')) {
            DB::statement('DROP INDEX ft_search ON articles');
        }
    }
};
