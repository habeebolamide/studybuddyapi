<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\StudyPlan\StudyPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashBoardController extends Controller
{
   public function dashboardAnalytics(Request $request)
   {
    // return 145;
       $analytics = [];


       $analytics['recent_uploads'] = StudyPlan::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->take(3)
            ->get(['id','course_title', 'created_at']);

        $analytics['completed_quizzes_count'] = Quiz::where('user_id',Auth::id())
            ->where('status', 'completed')
            ->count();
            
        $analytics['total_quizzes_count'] = Quiz::where('user_id',Auth::id())->count();

        return sendResponse('Dashboard Analytics', $analytics);
   }
}
