<?php

namespace App\Http\Controllers;

use App\Http\Requests\Preference\CreateRequest;
use App\Models\Article;
use App\Models\UserPreference;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserPreferenceController extends Controller
{
    use ApiResponse;

    /**
     * Store a newly created resource in storage.
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
     * Display the specified resource.
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
     * show the news feed based on preference
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
