<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConfig extends Model
{
    use HasFactory;

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

    /**
     * Campos que se pueden asignar masivamente.
     */
    protected $guarded = [];

    /**
     * Casting de atributos para asegurar consistencia en la API.
     * Esto ayuda a Scramble a documentar los tipos correctamente.
     */
    protected $casts = [
        'user_id'    => 'integer',
        'fixed'      => 'integer',
        'hundred'    => 'integer',
        'parlet'     => 'integer',
        'triplet'    => 'integer',
        'runner1'    => 'integer',
        'runner2'    => 'integer',
        'commission' => 'float', // Para manejar decimales en el porcentaje
    ];

    /**
     * Relación: La configuración pertenece a un usuario (vendedor).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
