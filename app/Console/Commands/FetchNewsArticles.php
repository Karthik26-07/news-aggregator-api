<?php

namespace App\Console\Commands;

use App\Models\Article;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchNewsArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:fetch {--dynamic-q=}'; // Optional dynamic q parameter
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and store articles from news APIs';

    protected $staticQueries = [
        'technology',
        'politics',
        'sports',
        'health',
        'business',
        'science',
        'entertainment',
        'environment',
        'education',
        'world',
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dynamicQ = $this->option('dynamic-q'); // Get dynamic q from command option
        $this->fetchNewsApiArticles($dynamicQ);
        $this->fetchNewsDataArticles($dynamicQ);
        $this->fetchGuardianArticles($dynamicQ);

        $this->info('Articles fetched and stored successfully.');
    }

    protected function getQuery($dynamicQ)
    {
        return $dynamicQ ?: $this->staticQueries[array_rand($this->staticQueries)];
    }
    protected function fetchNewsApiArticles($dynamicQ)
    {
        $response = Http::get('https://newsapi.org/v2/everything', [
            'apiKey' => config('services.news.news_api_key'),
            'q' => $this->getQuery($dynamicQ), // Example query
            'language' => 'en',
            'pageSize' => 20,
        ]);

        if ($response->successful()) {
            $articles = $response->json()['articles'] ?? [];
            foreach ($articles as $article) {
                $this->storeArticle([
                    'provider' => 'NewsAPI',
                    'category' => $article['source']['name'] ?? 'general',
                    'source' => $article['source']['name'] ?? 'Unknown',
                    'title' => $article['title'] ?? 'No Title',
                    'content' => $article['content'] ?? $article['description'] ?? 'No Content',
                    'summary' => $article['description'] ?? 'No Summary',
                    'author' => $article['author'] ?? null,
                    'article_url' => $article['url'] ?? '',
                    'image_url' => $article['urlToImage'] ?? null,
                    'published_at' => $article['publishedAt'] ? date('Y-m-d', strtotime($article['publishedAt'])) : now(),
                ]);
            }
        } else {
            $this->error('Failed to fetch from NewsAPI: ' . $response->status());
        }
    }

    protected function fetchNewsDataArticles($dynamicQ)
    {
        $response = Http::get('https://newsdata.io/api/1/latest', [
            'apiKey' => config('services.news.news_data_io'),
            'q' => $this->getQuery($dynamicQ),
            'language' => 'en'
        ]);

        if ($response->successful()) {
            $articles = $response->json()['results'] ?? [];
            foreach ($articles as $article) {
                $this->storeArticle([
                    'provider' => 'NewsData.Io',
                    'category' => $article['category'][0] ?? 'general',
                    'source' => $article['source_name'] ?? 'Unknown',
                    'title' => $article['title'] ?? 'No Title',
                    'content' => $article['content'] ?? $article['description'] ?? 'No Content',
                    'summary' => $article['description'] ?? 'No Summary',
                    'author' => $article['creator'][0] ?? null,
                    'article_url' => $article['link'] ?? '',
                    'image_url' => $article['image_url'] ?? null,
                    'published_at' => $article['pubDate'] ? date('Y-m-d', strtotime($article['pubDate'])) : now(),
                ]);
            }
        } else {
            $this->error('Failed to fetch from NewsAPI: ' . $response->status());
        }

    }

    protected function fetchGuardianArticles($dynamicQ)
    {
        $response = Http::get('https://content.guardianapis.com/search', [
            'api-key' => config('services.news.news_the_guardian'),
            'q' => $this->getQuery($dynamicQ),
            'show-fields' => 'body,trailText,byline,thumbnail',
            'page-size' => 20,
        ]);

        if ($response->successful()) {
            $articles = $response->json()['response']['results'] ?? [];
            foreach ($articles as $article) {
                $this->storeArticle([
                    'provider' => 'The Guardian',
                    'category' => $article['sectionName'] ?? 'general',
                    'source' => 'The Guardian',
                    'title' => $article['webTitle'] ?? 'No Title',
                    'content' => $article['fields']['body'] ?? 'No Content',
                    'summary' => $article['fields']['trailText'] ?? 'No Summary',
                    'author' => $article['fields']['byline'] ?? null,
                    'article_url' => $article['webUrl'] ?? '',
                    'image_url' => $article['fields']['thumbnail'] ?? null,
                    'published_at' => $article['webPublicationDate'] ? date('Y-m-d', strtotime($article['webPublicationDate'])) : now(),
                ]);
            }
        } else {
            $this->error('Failed to fetch from The Guardian: ' . $response->status());
        }
    }
    protected function storeArticle(array $data)
    {
        // DB::transaction(function () use ($data) {
        Article::updateOrCreate(
            ['article_url' => $data['article_url']], // Unique key
            $data
        );

        Cache::store('redis')->tags('article_list')->flush();
        // });
    }
}
