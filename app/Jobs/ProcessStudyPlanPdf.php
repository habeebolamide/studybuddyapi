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
            $studyplan = StudyPlan::where(['id' => $this->studyPlanId])->first();
            if (!$studyplan) {
                Log::error("Study plan not found for ID: " . $this->studyPlanId);
                Storage::disk('public')->delete($this->file['stored_path']);
                return;
            }
            $summary = $this->processPdfWithGemini($this->file['stored_path']);

            if (!$summary) {
                Log::error("Gemini returned no summary for file: " . $this->file['stored_path']);
                Storage::disk('public')->delete($this->file['stored_path']);
                return;
            }
            
            $studyplan->simplified_notes = $summary;
            $studyplan->save();
            
        } catch (\Exception $e) {
            Storage::disk('public')->delete($this->file['stored_path']);
            Log::error('Job failed: ' . $e->getMessage());
        }
    }

    private function processPdfWithGemini(string $pdfPath, string $model = 'gemini-2.5-flash')
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

        $prompt = "You are an intelligent and pedagogically sound study assistant designed to help learners deeply understand academic material. Based on the content provided, generate high-quality, well-structured study notes in valid JSON format — optimized for use in digital learning platforms, flashcard systems, or revision tools.
        
                Each note object must include the following fields:

                - **'topic'**: A concise, descriptive title capturing the essence of the concept, chapter, or section. Avoid vague or overly broad titles.

                - **'note'**: A detailed, accessible, and pedagogically sound explanation. The note should:
                • Cover the main concept and its purpose or significance  
                • Break down complex ideas into digestible sub-parts  
                • Include analogies or real-world context where appropriate  
                • Provide step-by-step walkthroughs of key methods or formulas if the topic is procedural  
                • Avoid jargon unless clearly defined within the note  
                • Be suitable for learners aiming for deep comprehension, not just memorization  

                - **'examples'**: A minimum of 3 diverse examples to solidify understanding:
                • All examples should be relevant to the topic and illustrate different aspects or applications of the concept
                • Add a mix of real-life applications, short quizzes (with or without answers), or scenario-based questions  
                • Format examples in plain text; clarity is key

                **Output Requirements:**
                - Return only the JSON array of objects — no introductory or explanatory text, and no code block formatting.
                - Ensure all JSON syntax is valid and structured for direct parsing by front-end applications.

                **Example Output:**
                [
                    {
                        'topic': 'Photosynthesis',
                        'note': 'Photosynthesis is the process by which green plants...',
                        'examples': [
                            'What are the raw materials needed for photosynthesis?',
                            'A worked example: Calculate the rate of photosynthesis when light intensity doubles...',
                            'Real-life example: How do greenhouses optimize photosynthesis for crop yield?'
                        ]
                    }
                ]";


        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => $apiKey,
        ])->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
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

        Log::info("Raw response", ['response' => $raw]);

        if (!$raw) return null;

        $cleaned = trim(preg_replace('/^```json|```$/m', '', $raw));
        $json = json_decode($cleaned, true);

       
        Log::info("Cleaned json", ['response' => $json]);
        return $json ;
    }
}
