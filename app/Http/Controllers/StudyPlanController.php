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
        try {
            $validator = Validator::make($request->all(), [
                "course_code" => "required",
                "course_title" => "required",
                "course_description" => "required",
                'uploaded_file' => 'required|file|max:10240',
            ]);
            if ($validator->fails()) {
                return sendError('Validation Error.', $validator->errors(), 400);
            }

            $userId = Auth::id();
            return DB::transaction(function () use ($userId, $request) {
                $file = $request->file('uploaded_file');
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                $filename = Auth::id() . '_' . Str::slug($originalName, '_') . '.' . $extension;
                $path = 'uploads/' . $filename;


                if (Storage::disk('public')->exists($path)) {
                    return response()->json([
                        'error' => "A file with the same name already exists for this user: {$filename}"
                    ], 409);
                }

                $storedPath = Storage::disk('public')->putFileAs('uploads', $file, $filename);


                $uploadedFiles[] = [
                    'stored_path' => $storedPath,
                    'file_url' => asset('storage/' . $storedPath),
                ];

                // Save study plan (after ensuring no conflicts)
                $studyplan = StudyPlan::create([
                    "user_id" => $userId,
                    "course_code" => $request->course_code,
                    "course_title" => $request->course_title,
                    "course_description" => $request->course_description,
                    "uploaded_files" => json_encode($uploadedFiles),
                ]);

                return $this->process($uploadedFiles, $studyplan->id);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function process($files, $id)
    {
        try {
            $summaries = [];
            foreach ($files as $file) {

                $summary = $this->processPdfWithGemini($file['stored_path']);

                if ($summary) {
                    StudyPlan::where('id', $id)->update([
                        "simplified_notes" => $summary
                    ]);
                    app(QuizController::class)->generateQuizQuestion($id);
                }
                $summaries[] = [
                    'file' => $file['stored_path'],
                    'summary' => $summary,
                ];
            }

            return response()->json([
                'simplified' => $summaries,
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private function summarizeText($text)
    {
        $geminiApiKey = env('GEMINI_API_KEY');
        $geminiModel = 'gemini-1.5-flash';

        $fullPrompt = "
        You are a helpful study assistant. Based on the content below, generate clear, simplified, and well-structured study notes in **valid JSON format**.

        Each note should:
        - Contain a 'topic' field (short summary or title of the section)
        - Include a 'note' field with an easy-to-understand explanation

        Each topic should:
        - Contain **at least 3-5 examples** there could be more

        Output only the JSON structure. Do NOT include markdown code blocks (e.g. ```json) or extra formatting.
        Example output format:

        [
            {
                'topic': 'Photosynthesis',
                'note': 'Photosynthesis is the process where plants use sunlight, water, and carbon dioxide to make food. This happens in chloroplasts.\\n\\nExamples:\\n- Plants convert sunlight into energy.\\n- Oxygen is released as a by-product.\\n- Glucose is stored as energy.'
            },
            {
                'topic': 'Cell Structure',
                'note': 'Cells have parts like the nucleus, mitochondria, and membrane. Each part has a specific job.\\n\\nExamples:\\n- The nucleus stores DNA.\\n- The mitochondria create energy.\\n- The membrane protects the cell.'
            }
        ]

        Content to summarize:
        " . $text;

        // Make the HTTP POST request to the Gemini API
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
            return 'Error generating notes from AI.'; // Or throw an exception for better error handling
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
        $studyplan = json_decode($cleanedJson, true);

        Log::info("cleaned json data", ['response' => $studyplan]);

        return $studyplan; // Returns a PHP array of notes
    }

    private function processPdfWithGemini(
        string $pdfFilePath,
        string $geminiModel = 'gemini-1.5-flash' // 'gemini-1.5-pro' is also excellent for vision
    ) {
        $geminiApiKey = env('GEMINI_API_KEY');

        if (empty($geminiApiKey)) {
            Log::error('GEMINI_API_KEY is not set in the .env file.');
            return sendError('API key not configured.', [], 400);
        }

        // Read the PDF file content
        if (!Storage::disk('public')->exists($pdfFilePath)) {
            return sendError('PDF file not found', [], 400);
        }
        $pdfContent = Storage::disk('public')->get($pdfFilePath);

        $mimeType = 'application/pdf'; // Explicitly define MIME type

        // Determine if to use inline data or File API based on size (conceptual)
        // For simplicity, this example uses inline data. For larger files,
        // you'd implement the File API upload first.
        // $fileSize = strlen($pdfContent);
        // if ($fileSize > 20 * 1024 * 1024) { // 20 MB limit
        //     // Implement File API upload here, get fileUri, then use it in contents
        //     // This would involve a separate POST to /v1beta/files and then referencing the file.
        //     Log::warning('PDF is too large for inline, File API not implemented in this example.');
        //     return 'PDF too large for direct upload in this example. File API needed.';
        // }

        $userPrompt = "
        You are a helpful study assistant. Based on the content below, generate clear, simplified, and well-structured study notes in **valid JSON format**.

        Each note should:
        - Contain a 'topic' field (short summary or title of the section)
        - Include a 'note' field with an easy-to-understand explanation

        Each topic should:
        - Contain **at least 3-5 examples** there could be more

        Output only the JSON structure. Do NOT include markdown code blocks (e.g. ```json) or extra formatting.
        Example output format:

        [
            {
                'topic': 'Photosynthesis',
                'note': 'Photosynthesis is the process where plants use sunlight, water, and carbon dioxide to make food. This happens in chloroplasts.\\n\\nExamples:\\n- Plants convert sunlight into energy.\\n- Oxygen is released as a by-product.\\n- Glucose is stored as energy.'
            },
            {
                'topic': 'Cell Structure',
                'note': 'Cells have parts like the nucleus, mitochondria, and membrane. Each part has a specific job.\\n\\nExamples:\\n- The nucleus stores DNA.\\n- The mitochondria create energy.\\n- The membrane protects the cell.'
            }
        ]
        ";

        // Encode PDF content to base64 for inline inclusion
        $base64Pdf = base64_encode($pdfContent);

        // Make the HTTP POST request to the Gemini API
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $geminiApiKey,
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$geminiModel}:generateContent", [
            'contents' => [
                [
                    'parts' => [
                        [
                            'inlineData' => [ // Use 'inlineData' for base64 encoded files
                                'mimeType' => $mimeType,
                                'data' => $base64Pdf,
                            ]
                        ],
                        ['text' => $userPrompt] // Your text prompt
                    ]
                ]
            ]
        ]);

        // Error handling and response parsing (similar to previous example)
        if (!$response->successful()) {
            Log::error('Gemini PDF processing failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return sendError('Error processing PDF with AI.', [], 400);
        }

        $responseData = $response->json();

        if (
            !isset($responseData['candidates'][0]['content']['parts'][0]['text']) ||
            empty($responseData['candidates'][0]['content']['parts'][0]['text'])
        ) {
            Log::warning('Gemini response did not contain expected text content after PDF processing.', ['response' => $responseData]);
            return sendError('No AI content generated from PDF or unexpected response format.', [], 400);
        }

        $generatedJsonString = $responseData['candidates'][0]['content']['parts'][0]['text'];

        $cleanedJson = preg_replace('/^```json|```$/m', '', $generatedJsonString);
        $cleanedJson = trim($cleanedJson);
        $studynotes = json_decode($cleanedJson, true);
        return $studynotes;
    }

    public function getAll()
    {
        $all = StudyPlan::where('user_id', Auth::id())->get();
        if ($all) {
            return sendResponse('All Study Notes', $all);
        }

        return sendError('Error fetching Notes', [], 400);
    }

     public function getSimplifiedNotes($id)
    {
        $simplefied_notes = StudyPlan::where('id', $id)->pluck('simplified_notes')->first();
        if ($simplefied_notes) {
            return sendResponse('All Study Notes', json_decode($simplefied_notes));
        }

        return sendError('Error fetching Notes', [], 400);
    }
}
