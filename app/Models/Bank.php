<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $guarded = [];

    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function lists() {
        return $this->hasMany(BankList::class);
    }
}
