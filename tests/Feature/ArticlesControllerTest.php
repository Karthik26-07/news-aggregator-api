<?php

namespace Tests\Feature;

use App\Classes\Common;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArticlesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
    }

    public function test_it_can_retrieve_a_list_of_articles()
    {

        Article::factory()->count(15)->create();

        $response = $this->getJson('/api/articles?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => [
                            'provider',
                            'category',
                            'source',
                            'title',
                            'content',
                            'summary',
                            'author',
                            'article_url',
                            'image_url',
                            'published_at',
                            'x_id'
                        ]
                    ],
                    'first_page_url',
                    'last_page',
                    'last_page_url',
                    'per_page',
                    'total'
                ]
            ])->assertJson(['success' => true, 'message' => 'Articles retrieved successfully', 'status' => 200]);

    }

    public function test_it_returns_empty_list_when_no_articles_found()
    {
        $response = $this->getJson('/api/articles?keyword=nonexistent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
                'data' => [
                    'current_page',
                    'data' => [],
                    'first_page_url',
                    'last_page',
                    'last_page_url',
                    'per_page',
                    'total'
                ]
            ])->assertJson(['success' => true, 'message' => 'Articles retrieved successfully', 'status' => 200]);
    }

    public function test_it_can_retrieve_a_single_article()
    {
        $article = Article::factory()->create();
        $hashedId = Common::hashId($article->id);

        $response = $this->getJson("/api/article?hashed_id={$hashedId}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'status',
                'data' => [
                    'provider',
                    'category',
                    'source',
                    'title',
                    'content',
                    'summary',
                    'author',
                    'article_url',
                    'image_url',
                    'published_at',
                    'x_id',
                ],
            ])->assertJson([
                    'success' => true,
                    'message' => 'Article details retrieved successfully',
                    'status' => 200,
                ]);
    }


    public function test_it_returns_error_when_article_id_is_missing()
    {

        $response = $this->getJson('/api/article');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Article id is required.',
                'status' => 400
            ]);
    }

    public function test_it_returns_error_for_invalid_article_id()
    {

        $response = $this->getJson('/api/article?hashed_id=invalid_id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or malformed article id.',
                'status' => 404
            ]);
    }
}