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

class StudyPlanController extends Controller
{
    public function uploadfile(Request $request)
    {
        try {
            $request->validate([
                "course_code" => "required",
                "course_title" => "required",
                "course_description" => "required",
                'uploaded_files.*' => 'required|file|max:10240', // max 10MB
            ]);

            $userId = Auth::id();
            $uploadedFiles = [];
            return DB::transaction(function () use ($userId, $request, $uploadedFiles) {
                foreach ($request->file('uploaded_files') as $key => $file) {
                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();
                    // return $originalName;
                    $filename = Auth::id() . '_' . Str::slug($originalName, '_') . '.' . $extension;
                    $path = 'uploads/' . $filename;

                    // Check if file already exists for this user (based on path or logic)
                    if (Storage::disk('public')->exists($path)) {
                        return response()->json([
                            'error' => "A file with the same name already exists for this user: {$filename}"
                        ], 409); // Conflict
                    }
                    // Save file
                    $storedPath = $file->storeAs('uploads', $filename, 'public');

                    $uploadedFiles[] = [
                        'stored_path' => $storedPath,
                        'file_url' => asset('storage/' . $storedPath),
                    ];
                }

                // Save study plan (after ensuring no conflicts)
                $studyplan = StudyPlan::create([
                    "user_id" => $userId,
                    "course_code" => $request->course_code,
                    "course_title" => $request->course_title,
                    "course_description" => $request->course_description,
                    "uploaded_files" => json_encode($uploadedFiles),
                ]);

                return $this->process($uploadedFiles,$studyplan->id);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function process($files,$id)
    {
        try {
            $processor = new FileProcessingService();
            $summaries = [];
            foreach ($files as $file) {
                $text = $processor->extractText($file['stored_path']);
                // return $text;
                $summary = $this->summarizeText($text);

                if ($summary) {
                   StudyPlan::where('id',$id)->update([
                        "simplified_notes" => $summary
                   ]);
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

    // private function summarizeText($text)
    // {
    //     $response = Http::withHeaders([
    //         'Authorization' => 'Bearer ' . env('COHERE_API_KEY'),
    //         'Content-Type' => 'application/json',
    //     ])->post('https://api.cohere.ai/v1/chat', [
    //         'model' => 'command-r-plus',
    //         'chat_history' => [],
    //         'message' => " 
    //                 You are a helpful study assistant. Based on the content below, generate clear, simplified, and well-structured study notes in **valid JSON format**.

    //                 Each note should:
    //                 - Contain a 'topic' field (short summary or title of the section)
    //                 - Include a 'note' field with an easy-to-understand explanation
    //                 - Contain **at least 5 examples** there could be more

    //                 Output only the JSON structure. Do NOT include markdown code blocks (e.g. ```json) or extra formatting.Example output format:

    //                 [
    //                     {
    //                         'topic': 'Photosynthesis',
    //                         'note': 'Photosynthesis is the process where plants use sunlight, water, and carbon dioxide to make food. This happens in chloroplasts.\\n\\nExamples:\\n- Plants convert sunlight into energy.\\n- Oxygen is released as a by-product.\\n- Glucose is stored as energy.'
    //                     },
    //                     {
    //                         'topic': 'Cell Structure',
    //                         'note': 'Cells have parts like the nucleus, mitochondria, and membrane. Each part has a specific job.\\n\\nExamples:\\n- The nucleus stores DNA.\\n- The mitochondria create energy.\\n- The membrane protects the cell.'
    //                     }
    //                 ]
    //             " . $text,
    //     ]);

    //     if (!$response->successful()) {
    //         Log::error('Cohere simplification failed', ['body' => $response->body()]);
    //         return 'Error generating notes.';
    //     }

    //     return $response->json('text') ?? 'No notes generated.';
    // }

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

        Log::info("cleaned json data" ,['response' => $studyplan]);

        return $studyplan; // Returns a PHP array of notes
    }
}
