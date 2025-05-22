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
        $studyPlan = StudyPlan::whereJsonContains('uploaded_files', ['stored_path' => $request->stored_path])->first();
        if ($studyPlan) {
            return $this->generateQuizFromText($studyPlan->simplified_notes,$studyPlan->id);
        }
    }


    private function generateQuizFromText($jsonDataString,$study_plan_id)
    {
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . env('COHERE_API_KEY'),
        //     'Content-Type' => 'application/json',
        // ])->post('https://api.cohere.ai/v1/chat', [
        //     'model' => 'command-r-plus',
        //     'chat_history' => [],
            // 'message' => "Based on the following educational content, generate a set of quiz questions in JSON format. 
            // Support questions involving math or calculations. Where necessary, format equations using LaTeX notation (e.g., \\frac{a}{b}, x^2, etc.).

            // Output should follow this JSON structure:
            // [
            // {
            //     \"question\": \"<The question text>\",
            //     \"type\": \"multiple_choice\"  ,
            //     \"options\": [\"<Option 1>\", \"<Option 2>\", ...] (only if needed for MCQs),
            //     \"answer\": \"<The actual correct answer text, not a letter like A or B>\"
            // },
            // ...
            // ]

            // Make sure:
            // - Questions assess key understanding from the content.
            // - It is only multiple choice questions
            // - The 'answer' field contains the actual correct response (not a label like 'A', 'B', etc.).
            // - Include at least 10 questions.
            // - Focus on clarity and student-friendliness.,
            // - You just json object no other text.,
            // - Make sure the questions contains beginner,intermediate and advanced so you shuffle it all together.

        //     Here is the content:\n\n" . $text,
        // ]);

        $geminiApiKey = env('GEMINI_API_KEY'); 
        $geminiModel = 'gemini-1.5-flash';    
        $userPrompt = "generate a set of quiz questions in JSON format. 
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
            - Include at least 10 questions.
            - Focus on clarity and student-friendliness.,
            - You just json object no other text.,
            - Make sure the questions contains beginner,intermediate and advanced so you shuffle it all together.";

         $fullPrompt = "Here is some context data in JSON format:\n" .
                  "```json\n" .
                  $jsonDataString . "\n" .
                  "```\n\n" .
                  "Based on the above JSON data, please perform the following task:\n" .
                  $userPrompt;

         $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . env('GEMINI_API_KEY'), [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $fullPrompt] 
                    ]
                ]
            ]
        ]);          
        if (!$response->successful()) {
            Log::error('Gemini simplification failed', ['status' => $response->status(), 'body' => $response->body()]);
            return 'Error generating quiz from AI.'; // Or throw an exception for better error handling
        }

         $responseData = $response->json();

        // Check if the expected keys exist before accessing
        if (
            !isset($responseData['candidates'][0]['content']['parts'][0]['text']) ||
            empty($responseData['candidates'][0]['content']['parts'][0]['text'])
        ) {
            Log::warning('Gemini response did not contain expected text content.', ['response' => $responseData]);
            return 'No notes generated or unexpected AI response format.';
        }

        $generatedJsonString = $responseData['candidates'][0]['content']['parts'][0]['text'];

        $cleanedJson = preg_replace('/^```json|```$/m', '', $generatedJsonString);
        $cleanedJson = trim($cleanedJson);
        $quizArray = json_decode($cleanedJson, true); 

        Log::info("cleaned json data" ,['response' => $quizArray]);

        return $this->save($quizArray,$study_plan_id);

        // $quizJson = $response->json('text'); // This is a string
        // $cleanedJson = preg_replace('/^```json|```$/m', '', $quizJson);
        // $cleanedJson = trim($cleanedJson);
        // $quizArray = json_decode($cleanedJson, true); 
        // return $this->save($quizArray,$study_plan_id);

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
