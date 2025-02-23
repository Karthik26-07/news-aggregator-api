<?php

namespace App\Models;

use App\Traits\Hashable;
use Hash;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use Hashable;
    protected $table = 'user_preferences';
    protected $default = 'x_id';
    protected $fillable = [
        'user_id',
        'preferred_sources',
        'preferred_categories',
        'preferred_authors',
    ];

    protected $hidden = [
        'id',
        'user_id',
        'created_at',
        'updated_at'
    ];

    protected $appends = ['x_id', 'x_user_id'];

    // protected $casts = [
    //     'user_id' => Hash::class . ':hash'
    // ];

    protected $hashableGetterFunctions = ['getXUserIdAttribute' => 'user_id'];
}
