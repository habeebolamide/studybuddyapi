<?php

namespace App\Models\StudyPlan;

use App\Models\Quiz;
use Illuminate\Database\Eloquent\Model;

class StudyPlan extends Model
{
    //
    protected $guarded = [];

    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }

    
}
