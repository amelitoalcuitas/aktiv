<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubImage extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'hub_id',
        'storage_path',
        'url',
        'order',
    ];

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }
}
