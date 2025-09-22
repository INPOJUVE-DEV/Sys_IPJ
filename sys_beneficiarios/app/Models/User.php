<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Str;
use App\Models\Municipio;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
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

    public function beneficiarios()
    {
        return $this->hasMany(Beneficiario::class, 'created_by', 'uuid');
    }

    protected static function booted()
    {
        static::creating(function (self $user) {
            if (empty($user->uuid)) {
                $user->uuid = (string) Str::uuid();
            }
        });
    }

    public function municipiosAsignados()
    {
        return $this->belongsToMany(Municipio::class, 'encargado_municipios', 'user_uuid', 'municipio_id', 'uuid', 'id');
    }

    // Salud360 relations
    public function asignacionesRecibidas()
    {
        return $this->hasMany(Salud360Assignment::class, 'psicologo_id');
    }

    public function sesionesComoPsicologo()
    {
        return $this->hasMany(Salud360Session::class, 'psicologo_id');
    }
}
