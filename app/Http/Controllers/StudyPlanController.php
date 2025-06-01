<?php

namespace App\Http\Controllers;

use App\Models\StudyPlan\StudyPlan;
use App\Services\FileProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudyPlanController extends Controller
{
    public function uploadfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "course_code" => "required",
            "course_title" => "required",
            "course_description" => "required",
            'uploaded_file' => 'required|file|max:10240',
        ]);

        if ($validator->fails()) {
            return sendError('Validation Error.', $validator->errors(), 400);
        }

        try {
            $userId = Auth::id();
            return DB::transaction(function () use ($request, $userId) {
                $file = $request->file('uploaded_file');
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                $filename = "{$userId}_" . Str::slug($originalName, '_') . ".{$extension}";
                $path = "uploads/{$filename}";

                if (Storage::disk('public')->exists($path)) {
                    return response()->json([
                        'error' => "A file with the same name already exists: {$filename}"
                    ], 409);
                }

                $storedPath = Storage::disk('public')->putFileAs('uploads', $file, $filename);

                $uploadedFiles = [[
                    'stored_path' => $storedPath,
                    'file_url' => asset('storage/' . $storedPath),
                ]];

                $studyplan = StudyPlan::create([
                    "user_id" => $userId,
                    "course_code" => $request->course_code,
                    "course_title" => $request->course_title,
                    "course_description" => $request->course_description,
                    "uploaded_files" => json_encode($uploadedFiles),
                ]);
                dispatch(new \App\Jobs\ProcessStudyPlanPdf($uploadedFiles[0], $studyplan->id));
                return sendResponse('Simplified Notes', []);
            });
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'File upload failed.'], 500);
        }
    }

    // public function process($files, $id)
    // {
    //     try {
    //         $summaries = [];

    //         foreach ($files as $file) {
    //             $summary = $this->processPdfWithGemini($file['stored_path']);

    //             if (empty($summary)) {
    //                 return sendError("Couldn't generate summary note", [], 400);
    //             }

    //             StudyPlan::where('id', $id)->update([
    //                 "simplified_notes" => json_encode($summary)
    //             ]);

    //             app(QuizController::class)->generateQuizQuestion($id);

    //             $summaries[] = [
    //                 'file' => $file['stored_path'],
    //                 'summary' => $summary,
    //             ];
    //         }

    //         return sendResponse('Summaries generated successfully.', [
    //             'simplified' => $summaries,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Processing error', ['error' => $e->getMessage()]);
    //         return sendError('Internal server error while processing PDF.', [], 500);
    //     }
    // }

    // private function processPdfWithGemini($pdfFilePath, $model = 'gemini-2.0-flash')
    // {
    //     $apiKey = env('GEMINI_API_KEY');

    //     if (empty($apiKey)) {
    //         Log::error('GEMINI_API_KEY is not configured.');
    //         return null;
    //     }

    //     if (!Storage::disk('public')->exists($pdfFilePath)) {
    //         return null;
    //     }

    //     $pdfContent = Storage::disk('public')->get($pdfFilePath);
    //     $base64Pdf = base64_encode($pdfContent);
    //     $mimeType = 'application/pdf';

    //     $prompt = '
    //         You are a helpful study assistant. Based on the content below, generate clear, simplified, and well-structured study notes in valid JSON format.

    //         Each note should:
    //         - Contain a "topic" field (short summary or title of the section)
    //         - Include a "note" field with an easy-to-understand explanation
    //         - Contain at least 3–5 examples

    //         Output only the JSON structure without markdown code blocks or extra formatting.

    //         Example:
    //         [
    //             {
    //                 "topic": "Photosynthesis",
    //                 "note": "Photosynthesis is the process..."
    //             }
    //         ]
    //         ';

    //     $response = Http::withHeaders([
    //         'Content-Type' => 'application/json',
    //         'x-goog-api-key' => $apiKey,
    //     ])->timeout(120)
    //     ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
    //         'contents' => [[
    //             'parts' => [
    //                 ['inlineData' => ['mimeType' => $mimeType, 'data' => $base64Pdf]],
    //                 ['text' => $prompt]
    //             ]
    //         ]]
    //     ]);

    //     if (!$response->successful()) {
    //         Log::error('Gemini API error', [
    //             'status' => $response->status(),
    //             'body' => $response->body(),
    //         ]);
    //         return null;
    //     }

    //     $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';

    //     if (empty($content)) {
    //         return null;
    //     }

    //     $cleanedJson = trim(preg_replace('/^```json|```$/m', '', $content));
    //     return json_decode($cleanedJson, true);
    // }

    public function getAll()
    {
        $notes = StudyPlan::where('user_id', Auth::id())->get();
        return $notes->isNotEmpty()
            ? sendResponse('All Study Notes', $notes)
            : sendError('No notes found.', [], 404);
    }

    public function getSimplifiedNotes($id)
    {
        $notes = StudyPlan::where('id', $id)->pluck('simplified_notes')->first();
        $decoded = json_decode($notes, true); 
        // $studyNotes = $decoded['notes'];

        return $decoded
            ? sendResponse('Simplified Notes', $decoded)
            : sendError('No simplified notes found.', [], 404);
    }
}
