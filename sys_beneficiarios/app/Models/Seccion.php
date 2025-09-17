<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seccion extends Model
{
    use HasFactory;

    protected $table = 'secciones';

    protected $fillable = [
        'seccional', 'municipio_id', 'distrito_local', 'distrito_federal'
    ];

    public function municipio()
    {
        return $this->belongsTo(Municipio::class);
    }
}
