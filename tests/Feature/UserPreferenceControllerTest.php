<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserPreferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();


    }
    public function test_store_preferences_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/preferences', [
            'preferred_sources' => 'TechCrunch,BBC',
            'preferred_categories' => 'tech,politics',
            'preferred_authors' => 'Jane Doe,John Smith',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preferences updated successfully',
            ]);

        $this->assertDatabaseHas('user_preferences', [
            'user_id' => $user->id,
            'preferred_sources' => 'TechCrunch,BBC',
            'preferred_categories' => 'tech,politics',
            'preferred_authors' => 'Jane Doe,John Smith',
        ]);
    }

    public function test_store_preferences_unauthorized()
    {
        $response = $this->postJson('/api/preferences', [
            'preferred_sources' => 'TechCrunch,BBC',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized: No token provided.',
                'status' => 401
            ]);
    }

    public function test_show_preferences_successfully()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        UserPreference::create([
            'user_id' => $user->id,
            'preferred_sources' => 'TechCrunch,BBC',
            'preferred_categories' => 'tech,politics',
            'preferred_authors' => 'Jane Doe,John Smith',
        ]);

        $response = $this->getJson('/api/preferences');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data' => [
                    'x_id',
                    'x_user_id',
                    'preferred_sources',
                    'preferred_categories',
                    'preferred_authors'
                ]
            ])
            ->assertJson([
                'status' => 200,
                'success' => true,
                'message' => 'Preferences retrieved successfully'
            ]);
    }

    public function test_show_preferences_unauthorized()
    {
        $response = $this->getJson('/api/preferences');

        $response->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'success' => false,
                'message' => 'Unauthorized: No token provided.',
            ]);
    }

    /**
     * Test retrieving personalized feed with preferences.
     *
     * @return void
     */
    public function test_feed_returns_articles_based_on_preferences()
    {
        // Arrange: Create an authenticated user with preferences
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserPreference::create([
            'user_id' => $user->id,
            'preferred_sources' => 'TechCrunch,BBC',
            'preferred_categories' => 'tech,politics',
            'preferred_authors' => 'Jane Doe',
        ]);

        // Create articles matching preferences
        Article::factory()->create([
            'source' => 'TechCrunch',
            'category' => 'tech',
            'author' => 'Jane Doe',
            'published_at' => '2025-02-23',
        ]);
        Article::factory()->create([
            'source' => 'BBC',
            'category' => 'politics',
            'author' => 'John Smith',
            'published_at' => '2025-02-22',
        ]);
        Article::factory()->create([
            'source' => 'Yahoo! News',
            'category' => 'sports',
            'author' => 'Other Author',
            'published_at' => '2025-02-21',
        ]); // Should not match

        // Act: Request the feed with per_page=2
        $response = $this->getJson('/api/feed?per_page=2');

        // Assert: Check the response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
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
                            'x_id',
                        ],
                    ],
                    'current_page',
                    'per_page',
                    'total',
                    'first_page_url',
                    'last_page_url',
                    'next_page_url',
                    'prev_page_url',
                ],
                'status',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Personalized feed retrieved successfully',
                'status' => 200,
            ]);

        // Assert: Only matching articles returned, ordered by published_at desc
        $responseData = $response->json('data');
        $this->assertEquals(2, count($responseData['data'])); // 2 per page
        $this->assertEquals(2, $responseData['total']); // Total matching articles
        $this->assertEquals('TechCrunch', $responseData['data'][0]['source']); // Latest first
        $this->assertEquals('BBC', $responseData['data'][1]['source']);
    }
    public function test_feed_retrieval_no_preferences()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/feed');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No preferences set. Please set preferences first.',
            ]);
    }
}