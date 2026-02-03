<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankList extends Model
{
    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_REJECTED = 'denied';
    protected $guarded = [];
    protected $casts = ['processed_text' => 'array'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
