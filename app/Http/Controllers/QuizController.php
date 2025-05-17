<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;

use App\Services\FileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    //

    public function generateQuizQuestion(Request $request)
    {


        $processor = new FileProcessingService();

        $text = $processor->extractText($request->stored_path);

        return $this->generateQuizFromText($text);
    }


    private function generateQuizFromText($text)
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

            Here is the content:\n\n" . $text,
        ]);

        if (!$response->successful()) {
            Log::error('Cohere quiz generation failed', ['body' => $response->body()]);
            return 'Error generating quiz.';
        }

        return $response->json('text') ?? 'No quiz generated.';
    }
}
