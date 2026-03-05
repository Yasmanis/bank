<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'main_user_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function bank_lists()
    {
        return $this->hasMany(BankList::class);
    }

    /**
     * Un Admin tiene una configuración de pagos.
     */
    public function config()
    {
        return $this->hasOne(AdminConfig::class);
    }

    public function userConfig()
    {
        return $this->hasOne(UserConfig::class);
    }

    /**
     * Obtiene la tarifa que le corresponde al usuario.
     * Prioridad: UserConfig > AdminConfig primera para todos
     */
    public function getEffectiveRates(): array
    {
        $rates = $this->userConfig ?: \App\Models\AdminConfig::first();

        if (!$rates) {
            throw new \Exception("No existe ninguna configuración de tarifas cargada en el sistema.");
        }
        return [
            'fixed'      => (int) $rates->fixed,
            'hundred'    => (int) $rates->hundred,
            'parlet'     => (int) $rates->parlet,
            'triplet'    => (int) $rates->triplet,
            'runner1'    => (int) $rates->runner1,
            'runner2'    => (int) $rates->runner2,
            'commission' => (float) $rates->commission,
        ];
    }

    public function settlements()
    {
        return $this->hasMany(Settlement::class);
    }

    /**
     * Usuarios que pertenecen a este usuario (Hijos)
     */
    public function subUsers()
    {
        return $this->hasMany(User::class, 'main_user_id');
    }

    /**
     * El usuario principal al que pertenece este usuario (Padre)
     */
    public function mainUser()
    {
        return $this->belongsTo(User::class, 'main_user_id');
    }

    /**
     * Helper para obtener todos los IDs que este usuario puede gestionar.
     * Incluye su propio ID y el de todos sus sub-usuarios.
     */
    public function getManagedUserIds(): array
    {
        $ids = $this->subUsers()->pluck('id')->toArray();
        $ids[] = $this->id; // Agregamos el ID del propio usuario
        return $ids;
    }

}
