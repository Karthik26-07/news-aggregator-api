<?php

namespace App\Http\Controllers;

use App\Classes\Common;
use App\Models\Article;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Manages article-related operations including listing and viewing individual articles.
 * @OA\Tag(
 *     name="Articles",
 *     description="Operations related to user preferences and news feeds"
 * )
 */
class ArticlesController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/articles",
     *     summary="Retrieve a list of articles",
     *     description="Returns a paginated list of articles with optional filters for keyword, date, category, and source.",
     *     tags={"Articles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of articles per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10, example=10)
     *     ),
     *     @OA\Parameter(
     *         name="keyword",
     *         in="query",
     *         description="Search keyword to filter articles by title, content, or summary",
     *         required=false,
     *         @OA\Schema(type="string", example="AI")
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter by publication date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-02-23")
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by article category",
     *         required=false,
     *         @OA\Schema(type="string", example="sports")
     *     ),
     *     @OA\Parameter(
     *         name="source",
     *         in="query",
     *         description="Filter by article source",
     *         required=false,
     *         @OA\Schema(type="string", example="Gazette")
     *     ),
        *     @OA\Response(
        *         response=200,
        *         description="Articles retrieved successfully.",
        *         @OA\JsonContent(
        *             @OA\Property(property="success", type="boolean", example=true),
        *             @OA\Property(property="message", type="string", example="Articles retrieved successfully"),
        *             @OA\Property(property="status", type="integer", example=200),
        *             @OA\Property(
        *                 property="data",
        *                 type="object",
        *                 @OA\Property(property="current_page", type="integer", example=1),
        *                 @OA\Property(property="data", type="array", @OA\Items(
        *                     @OA\Property(property="provider", type="string", example="NewsData.Io"),
        *                     @OA\Property(property="category", type="string", example="sports"),
        *                     @OA\Property(property="source", type="string", example="Gazette"),
        *                     @OA\Property(property="title", type="string", example="Colorado Springs pet related information and events starting Feb. 22"),
        *                     @OA\Property(property="content", type="string", example="ONLY AVAILABLE IN PAID PLANS"),
        *                     @OA\Property(property="summary", type="string", example="Pet related information and events in and around the Colorado Springs area"),
        *                     @OA\Property(property="author", type="string", example="BY CARLOTTA OLSON The Gazette"),
        *                     @OA\Property(property="article_url", type="string", example="https://gazette.com/events-calendars/..."),
        *                     @OA\Property(property="image_url", type="string", example="https://bloximages.newyork1.vip.townnews.com/..."),
        *                     @OA\Property(property="published_at", type="string", example="2025-02-22"),
        *                     @OA\Property(property="x_id", type="string", example="ME4PbazW6OVB")
        *                 )),
        *                 @OA\Property(property="first_page_url", type="string", example="http://127.0.0.1:8000/api/articles?page=1"),
        *                 @OA\Property(property="from", type="integer", nullable=true, example=null),
        *                 @OA\Property(property="last_page", type="integer", example=1),
        *                 @OA\Property(property="last_page_url", type="string", example="http://127.0.0.1:8000/api/articles?page=1"),
        *                 @OA\Property(
        *                     property="links",
        *                     type="array",
        *                     @OA\Items(
        *                         @OA\Property(property="url", type="string", nullable=true, example=null),
        *                         @OA\Property(property="label", type="string", example="&laquo; Previous"),
        *                         @OA\Property(property="active", type="boolean", example=false)
        *                     )
        *                 ),
        *                 @OA\Property(property="next_page_url", type="string", nullable=true, example=null),
        *                 @OA\Property(property="path", type="string", example="http://127.0.0.1:8000/api/articles"),
        *                 @OA\Property(property="per_page", type="integer", example=10),
        *                 @OA\Property(property="prev_page_url", type="string", nullable=true, example=null),
        *                 @OA\Property(property="to", type="integer", nullable=true, example=null),
        *                 @OA\Property(property="total", type="integer", example=0)
        *             )
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
     *         response=429,
     *         description="Too many requests",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Too many requests. Please try again later."),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=429)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Article::query();

        // Apply filters
        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                    ->orWhere('content', 'like', "%{$keyword}%")
                    ->orWhere('summary', 'like', "%{$keyword}%");
            });
        }

        if ($request->filled('date')) {
            $query->whereDate('published_at', $request->input('date'));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        // Pagination
        $perPage = $request->input('per_page', 10); // Default 10 per page
        $articles = $query->paginate($perPage);

        return $this->successResponse(
            $articles,
            'Articles retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/article",
     *     summary="Retrieve a single article",
     *     description="Returns details of a specific article identified by its hashed ID, cached for 30 minutes.",
     *     tags={"Articles"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="hashed_id",
     *         in="query",
     *         description="Hashed ID of the article",
     *         required=true,
     *         @OA\Schema(type="string", example="G0XAW57BqoDY")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Article retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Article details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="provider", type="string", example="NewsData.Io"),
     *                 @OA\Property(property="category", type="string", example="sports"),
     *                 @OA\Property(property="source", type="string", example="Yahoo! News"),
     *                 @OA\Property(property="title", type="string", example="Nicole Kidman Believes Gender Parity in Hollywood Only Comes From \u2018Actually Being in the Films of Women\u2019"),
     *                 @OA\Property(property="content", type="string", example="ONLY AVAILABLE IN PAID PLANS"),
     *                 @OA\Property(property="summary", type="string", example="In 2017, Kidman vowed to work with a female director every 18 months and has since exceeded that quota."),
     *                 @OA\Property(property="author", type="string", example="Indiewire"),
     *                 @OA\Property(property="article_url", type="string", example="https://ca.news.yahoo.com/nicole-kidman-believes-gender-parity-200000063.html"),
     *                 @OA\Property(property="image_url", type="string", example="https://s.yimg.com/ny/api/res/1.2/WW.4xO2adhm6X4hilegfNw--/YXBwaWQ9aGlnaGxhbmRlcjt3PTEyMDA7aD04MDA-/https://media.zenfs.com/en/indiewire_268/d931154b95d796643ddcc2033b241986"),
     *                 @OA\Property(property="published_at", type="string", example="2025-02-22"),
     *                 @OA\Property(property="x_id", type="string", example="G0XAW57BqoDY")
     *             ),
     *             @OA\Property(property="status", type="integer", example=200)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Missing article ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Article id is required."),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid or not found article",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid or malformed article id."),
     *             @OA\Property(property="data", type="null", example=null),
     *             @OA\Property(property="status", type="integer", example=404)
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
     *     )
     * )
     */
    public function show(Request $request)
    {
        if (!$request->has('hashed_id')) {
            return $this->errorResponse('Article id is required.', 400);
        }

        $hashedId = $request->query('hashed_id');
        $decoded = Common::getIdFromHash($hashedId);

        if (empty($decoded)) {
            return $this->errorResponse('Invalid or malformed article id.', 404);
        }
        $cacheKey = "article_{$hashedId}";
        $ttl = now()->addMinutes(30); // 30 minutes

        $article = Cache::store('redis')->tags('article_list')->remember($cacheKey, $ttl, function () use ($decoded) {
            return Article::findOrFail($decoded);
        });
        return $this->successResponse(
            $article,
            'Article details retrieved successfully'
        );
    }


}
