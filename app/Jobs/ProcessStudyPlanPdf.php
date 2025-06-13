<?php
namespace App\Jobs;

use App\Models\StudyPlan\StudyPlan;
use App\Http\Controllers\QuizController;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
class ProcessStudyPlanPdf implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $file;
    public $studyPlanId;

    public function __construct(array $file, int $studyPlanId)
    {
        $this->file = $file;
        $this->studyPlanId = $studyPlanId;
    }

    public function handle(): void
    {
        try {
            $studyplan = StudyPlan::where('id', $this->studyPlanId)->first();
            if (!$studyplan) {
                Log::error("Study plan not found for ID: " . $this->studyPlanId);
                return;
            }
            $summary = $this->processPdfWithGemini($this->file['stored_path']);

            if (!$summary) {
                Log::error("Gemini returned no summary for file: " . $this->file['stored_path']);
                return;
            }
            
            $studyplan->simplified_notes = $summary;
            $user = User::find($studyplan->user_id);

        } catch (\Exception $e) {
            Log::error('Job failed: ' . $e->getMessage());
        }
    }

    private function processPdfWithGemini(string $pdfPath, string $model = 'gemini-2.0-flash')
    {
        $apiKey = env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            Log::error('GEMINI_API_KEY not set.');
            return null;
        }

        if (!Storage::disk('public')->exists($pdfPath)) {
            Log::error('PDF file not found: ' . $pdfPath);
            return null;
        }

        $content = Storage::disk('public')->get($pdfPath);
        $base64 = base64_encode($content);

        $prompt = "
            You are a helpful study assistant. Based on the content below, generate clear, simplified, and well-structured study notes in valid JSON format.

            Each note should include:

            'topic': A short, clear title that summarizes the main idea or section.

            'note': A well-explained, easy-to-understand explanation of the concept that helps someone prepare for an exam. Use simple language and include step-by-step solutions or methods only if needed.

            'examples': A list containing at least 3 examples to reinforce the concept. Examples can be real-life, definitions, or short questions (with or without answers) — but if the topic requires it, include at least one full example with a solution.

            Return ONLY the JSON. No code block formatting.
             Example:
            [
                {
                    'topic': 'Photosynthesis',
                    'note': 'Photosynthesis is the process...'
                    'examples':[]
                }
            ]
            ";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $apiKey,
        ])->timeout(90)->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
            'contents' => [[
                'parts' => [
                    ['inlineData' => ['mimeType' => 'application/pdf', 'data' => $base64]],
                    ['text' => $prompt]
                ]
            ]]
        ]);

        if (!$response->successful()) {
            Log::error('Gemini API Error', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $raw = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$raw) return null;

        $cleaned = trim(preg_replace('/^```json|```$/m', '', $raw));
        $json = json_decode($cleaned, true);

       
        // Log::info("Cleaned json", ['response' => $json]);
        return $json ;
    }
}
