<?php

namespace App\Http\Controllers;

use App\Http\Requests\Preference\CreateRequest;
use App\Models\Article;
use App\Models\UserPreference;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Manages user preferences and personalized news feeds.
 * @OA\Tag(
 *     name="Preferences",
 *     description="Operations related to user preferences and news feeds"
 * )
 */
class UserPreferenceController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Post(
     *     path="/api/preferences",
     *     summary="Store or update user preferences",
     *     description="Creates or updates the authenticated user's preferences, clearing relevant Redis caches. Each preference field is a comma-separated string, where individual values should match existing records in the articles table (sources, categories, authors).",
     *     tags={"Preferences"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="preferred_sources",
     *                 type="string",
     *                 example="TechCrunch,BBC",
     *                 description="Comma-separated list of preferred news sources. Each source must exist in the 'source' column of the articles table.",
     *                 nullable=true,
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="preferred_categories",
     *                 type="string",
     *                 example="tech,politics",
     *                 description="Comma-separated list of preferred categories. Each category must exist in the 'category' column of the articles table.",
     *                 nullable=true,
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="preferred_authors",
     *                 type="string",
     *                 example="Jane Doe,John Smith",
     *                 description="Comma-separated list of preferred authors. Each author must exist in the 'author' column of the articles table.",
     *                 nullable=true,
     *                 maxLength=255
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Preferences updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Preferences updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="preferred_sources", type="string", example="TechCrunch,BBC"),
     *                 @OA\Property(property="preferred_categories", type="string", example="tech,politics"),
     *                 @OA\Property(property="preferred_authors", type="string", example="Jane Doe,John Smith"),
     *                 @OA\Property(property="created_at", type="string", example="2025-02-23T12:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", example="2025-02-23T12:00:00.000000Z")
     *             ),
     *             @OA\Property(property="status", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized: Invalid or expired token."),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=401)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="preferred_sources",
     *                     type="array",
     *                     @OA\Items(type="string", example="The preferred_sources must be a string.")
     *                 )
     *             ),
     *             @OA\Property(property="status", type="integer", example=422)
     *         )
     *     )
     * )
     */
    public function store(CreateRequest $request)
    {
        $user = $request->user();

        $preferences = $user->preferences()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only('preferred_sources', 'preferred_categories', 'preferred_authors')
        );
        // Clear Redis caches
        Cache::store('redis')->forget("user_preference_{$user->id}");
        Cache::store('redis')->forget("user_feed_{$user->id}_" . md5(serialize($request->only('per_page'))));
        return $this->successResponse(
            $preferences,
            'Preferences updated successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/preferences",
     *     summary="Retrieve user preferences",
     *     description="Returns the authenticated user's preferences, cached for 15 minutes. If no preferences are set, 'data' will be null.",
     *     tags={"Preferences"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Preferences retrieved successfully or no preferences set yet",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Preferences retrieved successfully",
     *                 description="Will be 'Preferences retrieved successfully' if preferences exist, or 'No preferences set yet' if none exist"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 nullable=true,
     *                 description="User preferences if set, otherwise null",
     *                 @OA\Property(property="x_id", type="string", example="asjgffawf"),
     *                 @OA\Property(property="x_user_id", type="string", example="kjadkagsfka"),
     *                 @OA\Property(property="preferred_sources", type="string", example="TechCrunch,BBC"),
     *                 @OA\Property(property="preferred_categories", type="string", example="tech,politics"),
     *                 @OA\Property(property="preferred_authors", type="string", example="Jane Doe,John Smith")             
     *             ),
     *             @OA\Property(property="status", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not authenticated"),
     *             @OA\Property(property="status", type="integer", example=401)
     *         )
     *     )
     * )
     */
    public function show(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->errorResponse('User not authenticated', 401);
        }

        $userId = $user->id;
        $cacheKey = "user_preference_{$userId}";
        $ttl = now()->addMinutes(15);

        $preferences = Cache::store('redis')->remember($cacheKey, $ttl, function () use ($user) {
            return $user->preferences;
        });

        if (!$preferences) {
            return $this->successResponse(null, 'No preferences set yet');
        }

        return $this->successResponse(
            $preferences,
            'Preferences retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/feed",
     *     summary="Retrieve personalized news feed",
     *     description="Returns a paginated list of articles based on the authenticated user's preferences, cached for 15 minutes.",
     *     tags={"Feed"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of articles per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Personalized feed retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Personalized feed retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="provider", type="string", example="NewsData.Io"),
     *                         @OA\Property(property="category", type="string", example="sports"),
     *                         @OA\Property(property="source", type="string", example="Yahoo! News"),
     *                         @OA\Property(property="title", type="string", example="Nicole Kidman Believes Gender Parity in Hollywood Only Comes From ‘Actually Being in the Films of Women’"),
     *                         @OA\Property(property="content", type="string", example="ONLY AVAILABLE IN PAID PLANS"),
     *                         @OA\Property(property="summary", type="string", example="In 2017, Kidman vowed to work with a female director every 18 months and has since exceeded that quota."),
     *                         @OA\Property(property="author", type="string", example="Indiewire"),
     *                         @OA\Property(property="article_url", type="string", example="https://ca.news.yahoo.com/nicole-kidman-believes-gender-parity-200000063.html"),
     *                         @OA\Property(property="image_url", type="string", example="https://s.yimg.com/ny/api/res/1.2/WW.4xO2adhm6X4hilegfNw--/YXBwaWQ9aGlnaGxhbmRlcjt3PTEyMDA7aD04MDA-/https://media.zenfs.com/en/indiewire_268/d931154b95d796643ddcc2033b241986"),
     *                         @OA\Property(property="published_at", type="string", example="2025-02-22"),
     *                         @OA\Property(property="x_id", type="string", example="G0XAW57BqoDY")
     *                     )
     *                 ),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="first_page_url", type="string", example="http://127.0.0.1:8000/api/feed?page=1"),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=1),
     *                 @OA\Property(property="last_page_url", type="string", example="http://127.0.0.1:8000/api/feed?page=1"),
     *                 @OA\Property(
     *                     property="links",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="url", type="string", example="http://127.0.0.1:8000/api/feed?page=1", nullable=true),
     *                         @OA\Property(property="label", type="string", example="1"),
     *                         @OA\Property(property="active", type="boolean", example=true)
     *                     )
     *                 ),
     *                 @OA\Property(property="next_page_url", type="string", example=null),
     *                 @OA\Property(property="path", type="string", example="http://127.0.0.1:8000/api/feed"),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="prev_page_url", type="string", example=null),
     *                 @OA\Property(property="to", type="integer", example=5),
     *                 @OA\Property(property="total", type="integer", example=5)
     *             ),
     *             @OA\Property(property="status", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No preferences set",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No preferences set. Please set preferences first."),
     *             @OA\Property(property="status", type="integer", example=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized: Invalid or expired token."),
     *             @OA\Property(property="status", type="integer", example=401)
     *         )
     *     )
     * )
     */
    public function feed(Request $request)
    {
        $user = $request->user();
        $preferences = $user->preferences;

        if (!$preferences) {
            return $this->errorResponse('No preferences set. Please set preferences first.', 400);
        }

        $cacheKey = "user_feed_{$user->id}_" . md5(serialize($request->only('per_page')));
        $ttl = now()->addMinutes(15); // 15 minutes

        $articles = Cache::store('redis')->tags('article_list')->remember($cacheKey, $ttl, function () use ($request, $preferences) {
            $query = Article::query();

            // Use OR conditions across preference types
            $query->where(function ($q) use ($preferences) {
                if ($preferences->preferred_sources) {
                    $sources = explode(',', $preferences->preferred_sources);
                    $q->orWhereIn('source', $sources);
                }

                if ($preferences->preferred_categories) {
                    $categories = explode(',', $preferences->preferred_categories);
                    $q->orWhereIn('category', $categories);
                }

                if ($preferences->preferred_authors) {
                    $authors = explode(',', $preferences->preferred_authors);
                    $q->orWhereIn('author', $authors);
                }
            });

            $query->orderByDesc('published_at');
            $perPage = $request->input('per_page', 10);
            return $query->paginate($perPage);
        });
        return $this->successResponse(
            $articles,
            'Personalized feed retrieved successfully'
        );
    }
}