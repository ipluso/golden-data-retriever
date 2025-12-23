<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dog extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Die Felder, die massenhaft zugewiesen werden dürfen.
     */
    protected $fillable = [
        // Identifikation
        'drc_id',
        'registration_number',

        // Stammdaten
        'name',
        'breed',
        'date_of_birth',
        'sex',
        'offspring_count',

        // Gesundheit (Phänotyp)
        'hd_score',
        'ed_score',
        'pra_status',

        // Zuchtwerte (Genotyp)
        'zw_hd',
        'zw_ed',
        'zw_hc',

        // JSON Container für dynamische Merkmale
        'genetic_tests',
        'eye_exams',
        'orthopedic_details',
        'work_exams',
        'raw_data',
    ];

    /**
     * Automatische Typ-Konvertierung durch Laravel.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        // JSON Felder automatisch in Arrays umwandeln
        'genetic_tests' => 'array',
        'eye_exams' => 'array',
        'orthopedic_details' => 'array',
        'work_exams' => 'array',
        'raw_data' => 'array',
    ];

    public function father()
    {
        return $this->belongsTo(Dog::class, 'father_id');
    }

    public function mother()
    {
        return $this->belongsTo(Dog::class, 'mother_id');
    }

    public function children()
    {
        return $this->hasMany(Dog::class, 'father_id')
            ->orWhere('mother_id', $this->id);
    }
}
