<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\StudyPlan\StudyPlan;
use Illuminate\Support\Facades\Http;

use App\Services\FileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    //

    public function generateQuizQuestion(Request $request)
    {
        $processor = new FileProcessingService();

        $text = $processor->extractText($request->stored_path);
        $studyPlan = StudyPlan::whereJsonContains('uploaded_files', ['stored_path' => $request->stored_path])->first();
        return $this->generateQuizFromText($text,$studyPlan->id);
    }


    private function generateQuizFromText($text,$study_plan_id)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('COHERE_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.cohere.ai/v1/chat', [
            'model' => 'command-r-plus',
            'chat_history' => [],
            'message' => "Based on the following educational content, generate a set of quiz questions in JSON format. 
            Support questions involving math or calculations. Where necessary, format equations using LaTeX notation (e.g., \\frac{a}{b}, x^2, etc.).

            Output should follow this JSON structure:
            [
            {
                \"question\": \"<The question text>\",
                \"type\": \"multiple_choice\"  ,
                \"options\": [\"<Option 1>\", \"<Option 2>\", ...] (only if needed for MCQs),
                \"answer\": \"<The actual correct answer text, not a letter like A or B>\"
            },
            ...
            ]

            Make sure:
            - Questions assess key understanding from the content.
            - It is only multiple choice questions
            - The 'answer' field contains the actual correct response (not a label like 'A', 'B', etc.).
            - Include at least 5-7 questions.
            - Focus on clarity and student-friendliness.,
            - You just json object no other text.

            Here is the content:\n\n" . $text,
        ]);

        if (!$response->successful()) {
            Log::error('Cohere quiz generation failed', ['body' => $response->body()]);
            return 'Error generating quiz.';
        }

        $quizJson = $response->json('text'); // This is a string
        $cleanedJson = preg_replace('/^```json|```$/m', '', $quizJson);
        $cleanedJson = trim($cleanedJson);
        $quizArray = json_decode($cleanedJson, true); 
        return $this->save($quizArray,$study_plan_id);

        // return $response->json('text') ?? 'No quiz generated.';
    }

    public function save($questions,$study_plan_id){
        // return $questions;
        foreach ($questions as $key => $question) {
            Quiz::create([
                "user_id" => Auth::id(),
                "study_plan_id" => $study_plan_id,
                "question" => $question['question'],
                "type" => $question['type'],
                "options" => $question['options'],
                "answer" => $question['answer'],
            ]);
        }

         return sendResponse('Quiz Generated Successfully', $questions);  
    }
}
