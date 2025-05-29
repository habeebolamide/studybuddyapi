<?php
namespace App\Jobs;

use App\Models\StudyPlan\StudyPlan;
use App\Http\Controllers\QuizController;
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
            $summary = $this->processPdfWithGemini($this->file['stored_path']);

            if (!$summary) {
                Log::error("Gemini returned no summary for file: " . $this->file['stored_path']);
                return;
            }

            StudyPlan::where('id', $this->studyPlanId)->update([
                "simplified_notes" => $summary
            ]);

            app(QuizController::class)->generateQuizQuestion($this->studyPlanId);

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

        $prompt = <<<EOT
You are a helpful study assistant. Based on the content below, generate clear, simplified, and well-structured study notes in valid JSON format.

Each note should:
- Contain a 'topic' field (short summary or title of the section) 
- Include a 'note' field with an easy-to-understand explanation and each note should contain at least 3 examples for better understanding

Return ONLY the JSON. No code block formatting.
EOT;

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
        return json_decode($cleaned, true);
    }
}
