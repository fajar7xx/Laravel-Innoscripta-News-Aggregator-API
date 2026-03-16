<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources');
            $table->string('external_id');
            $table->string('title', 500);
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('author')->nullable();
            $table->string('url', 500)->unique();
            $table->string('image_url', 500)->nullable();
            $table->timestamp('published_at');
            $table->timestamp('fetched_at');
            $table->timestamps();
            $table->softDeletes();

            // Composite unique constraint for per-source deduplication
            $table->unique(['source_id', 'external_id'], 'unique_source_article');

            // Performance index for source filtering
            $table->index('source_id', 'idx_source_id');
        });

        // FULLTEXT index for search functionality (MariaDB/MySQL)
        // Enables: Article::whereFullText(['title', 'description', 'content'], $keyword)
        DB::statement('CREATE FULLTEXT INDEX ft_search ON articles(title, description, content)');

        // Composite indexes with DESC order for published_at (raw SQL required)
        // Optimized for: ORDER BY published_at DESC (newest first)
        DB::statement('CREATE INDEX idx_published_at ON articles(published_at DESC)');

        // Composite index for common query pattern: filter by source + sort by date
        // Optimized for: WHERE source_id = X ORDER BY published_at DESC
        DB::statement('CREATE INDEX idx_source_published ON articles(source_id, published_at DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
