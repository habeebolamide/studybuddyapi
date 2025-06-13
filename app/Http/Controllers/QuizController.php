<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\QuizTotalScore;
use App\Models\StudyPlan\StudyPlan;
use Illuminate\Support\Facades\Http;

use App\Services\FileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    //

    public function getallQuiz()
    {
        $quiz = Quiz::where(['user_id' => Auth::id()])
           ->get();

        if ($quiz) {
            return sendResponse('All Quiz', $quiz);
        }

        return sendError('Error fetching Quiz', [], 400);
    }

     public function getQuiz($id)
    {
        $quiz = Quiz::where(['id'=> $id, 'user_id' => Auth::id()])
            ->with('questions')
            ->first();

        if ($quiz) {
            foreach ($quiz->questions as $question) {
                $question->options = json_decode($question->options);
            }

            return sendResponse('All Quiz', $quiz);
        }

        return sendError('Error fetching Quiz', [], 400);
    }

    public function generateQuizQuestion($id)
    {
        $studyPlan = StudyPlan::where(['id' => $id, 'user_id' => Auth::id()])->first();

        if (!$studyPlan) {
            return sendError('Study plan not found or you do not have permission to access it.', [], 404);
        }
        $decoded = json_decode($studyPlan->simplified_notes, true);

        Log::info("Decoded json", ['response' => $decoded]);

        if ($studyPlan) {
            return $this->generateQuizFromText($decoded, $studyPlan->id, $studyPlan->course_title);
        }
    }


    private function generateQuizFromText($jsonDataString, $study_plan_id, $course_title)
    {
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
            - Include as much questions as you think it will be enough to test a university student.
            - Focus on clarity and student-friendliness.,
            - You just json object no other text.,
            - Make sure the questions contains beginner,intermediate and advanced so you shuffle it all together.";

        $fullPrompt = "Here is some context data in JSON format:\n" .
            "```json\n" .
            json_encode($jsonDataString, JSON_PRETTY_PRINT) . "\n" .
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
            return sendError('Error generating quiz from AI.', [], 400);
        }

        $responseData = $response->json();

        // Check if the expected keys exist before accessing
        if (
            !isset($responseData['candidates'][0]['content']['parts'][0]['text']) ||
            empty($responseData['candidates'][0]['content']['parts'][0]['text'])
        ) {
            Log::warning('Gemini response did not contain expected text content.', ['response' => $responseData]);
            return sendError('No notes generated or unexpected AI response format.', [], 400);
        }

        $generatedJsonString = $responseData['candidates'][0]['content']['parts'][0]['text'];

        $cleanedJson = preg_replace('/^```json|```$/m', '', $generatedJsonString);
        if ($cleanedJson == null) {
            return sendError('Null Json.', [], 400);
        }
        $cleanedJson = trim($cleanedJson);
        $quizArray = json_decode($cleanedJson, true);

        Log::info("quiz questions", ['response' => $quizArray]);

        return $this->save($quizArray, $study_plan_id, $course_title);
    }

    public function save($questions, $study_plan_id, $course_description)
    {
        $quiz = Quiz::create([
            "title" => $course_description,
            "user_id" => Auth::id(),
            "study_plan_id" => $study_plan_id,
        ]);

        foreach ($questions as $key => $question) {
            QuizQuestion::create([
                "quiz_id" => $quiz->id,
                "question" => $question['question'],
                "type" => $question['type'],
                "options" => json_encode($question['options']),
                "answer" => $question['answer'],
            ]);
        }
        $success = [
            'quiz_id' => $quiz->id,
            'title' => $quiz->title,
        ];
        return sendResponse('Quiz Generated Successfully', $success);
    }

    public function submitQuiz(Request $request)
    {
        try {
            $answers = $request->selected_answers;
            $results = [];

            foreach ($answers as $value) {
                $question = QuizQuestion::where('id', $value['question_id'])->first();
                $existing = QuizAnswer::where('user_id', Auth::id())
                    ->where('quiz_question_id', $value['question_id'])
                    ->first();

                if ($existing) {
                    continue;
                }

                QuizAnswer::create([
                    'user_id' => Auth::id(),
                    'quiz_question_id' => $value['question_id'],
                    'chosen_option' => $value['selected_answer'],
                    'correct_option' => $question->answer,
                ]);

                if ($question->answer === $value['selected_answer']) {
                    $question->score = 1;
                    $question->save();
                }

                $results[] = [
                    'question_id' => $value['question_id'],
                    'correct' => $question->answer === $value['selected_answer']
                ];
            }
            $correctCount = count(array_filter($results, fn($res) => $res['correct'] === true));

            QuizTotalScore::create([
                'quiz_id' => $request->quiz_id,
                'user_id' => Auth::id(),
                "score" => $correctCount,
            ]);
            Quiz::where('id', $request->quiz_id)->update([
                'status' => 'completed'
            ]);
            return response()->json([
                'success' => true,
                'results' => $results
            ]);
        } catch (\Exception $e) {
            return sendError($e->getMessage(), [], 400);
        }
    }
}
