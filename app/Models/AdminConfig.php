<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminConfig extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->created_by) {
                $model->created_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (!$model->updated_by) {
                $model->updated_by = auth()->id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
