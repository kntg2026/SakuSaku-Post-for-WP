<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagCorpus extends Model
{
    public $timestamps = false;

    protected $table = 'tag_corpus';

    protected $fillable = [
        'tenant_id', 'term', 'document_frequency',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
