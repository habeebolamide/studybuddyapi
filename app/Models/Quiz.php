<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    //
    protected $guarded = [];
     
    protected $casts = [
        'options' => 'array',
    ];

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class);
    }
    
}
