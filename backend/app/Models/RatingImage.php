<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatingImage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'hub_rating_images';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hub_rating_id',
        'storage_path',
        'url',
        'order',
    ];

    public function rating(): BelongsTo
    {
        return $this->belongsTo(HubRating::class, 'hub_rating_id');
    }
}
