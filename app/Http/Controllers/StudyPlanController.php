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
            return DB::transaction(function () use ($userId, $request, $uploadedFiles) {
                StudyPlan::create([
                    "user_id" => $userId,
                    "course_code" => $request->course_code,
                    "course_title" => $request->course_title,
                    "course_description" => $request->course_description,
                    "uploaded_files" => json_encode($uploadedFiles),
                ]);

                return $this->process($uploadedFiles);
            });
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function process($files)
    {
        try {
            $processor = new FileProcessingService();
            $summaries = [];
            foreach ($files as $file) {
                $text = $processor->extractText($file['stored_path']);
                $summary = $this->summarizeText($text);
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
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('COHERE_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post('https://api.cohere.ai/v1/chat', [
            'model' => 'command-r-plus',
            'chat_history' => [],
            'message' => "Please simplify the following content into concise, well-explained study notes that are easy for students to understand. Use clear language and highlight key points. Include relevant examples where needed to make complex concepts easier to grasp, but omit examples if the material is already straightforward. The target audience is  students who don't understand. Present the notes in  Q&A, summary.:\n\n" . $text,
        ]);

        if (!$response->successful()) {
            Log::error('Cohere simplification failed', ['body' => $response->body()]);
            return 'Error generating notes.';
        }

        return $response->json('text') ?? 'No notes generated.';
    }
}
