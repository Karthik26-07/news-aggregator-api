<?php

namespace App\Http\Controllers;

use App\Classes\Common;
use App\Models\Article;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticlesController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
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
     * Display the specified resource.
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
