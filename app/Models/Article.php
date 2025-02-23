<?php

namespace App\Models;

use App\Traits\Hashable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use Hashable, HasFactory;

    protected $table = "articles";
    protected $default = ['x_id'];
    protected $appends = ['x_id'];
    protected $fillable = [
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
    ];

    protected $hidden = [
        'id',
        'created_at',
        'updated_at'
    ];
}
