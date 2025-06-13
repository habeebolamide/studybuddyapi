<?php

namespace App\Models;

use App\Models\StudyPlan\StudyPlan;
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

    public function studyplan()
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }
    
}
