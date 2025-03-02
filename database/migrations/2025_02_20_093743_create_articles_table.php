<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string("provider");
            $table->string("category");
            $table->string("source");
            $table->string("title");
            $table->text("content");
            $table->text("summary");
            $table->string("author")->nullable();
            $table->text('article_url');
            $table->text("image_url")->nullable();
            $table->date("published_at");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
