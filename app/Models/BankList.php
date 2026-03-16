<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankList extends Model
{
    use SoftDeletes;
    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING = 'pending';
    const STATUS_REJECTED = 'denied';

    const STATUS_ERROR = 'error';
    protected $guarded = [];
    protected $casts = [
        'processed_text' => 'array',
        'error_log' => 'array',
        'client_created_at' => 'datetime',
        'manual_results' => 'array',
        'validated_at' => 'datetime'
    ];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
