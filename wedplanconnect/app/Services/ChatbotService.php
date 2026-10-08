<?php

namespace App\Services;

use App\Models\ChatbotSession;
use App\Models\Faq;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rule-based FAQ assistant (keyword matching against the faqs table) with an
 * optional Google Gemini fallback when no FAQ scores above the confidence threshold.
 * Every exchange is logged to chatbot_sessions for audit.
 */
class ChatbotService
{
    private const STOPWORDS = [
        'the', 'and', 'for', 'are', 'you', 'your', 'can', 'how', 'what', 'when', 'where', 'who', 'why',
        'does', 'did', 'will', 'with', 'this', 'that', 'there', 'have', 'has', 'our', 'about', 'from',
        'any', 'much', 'many', 'please', 'there', 'which', 'would', 'could', 'should', 'into', 'its', 'get',
        'need', 'want', 'know', 'tell', 'hello', 'thanks', 'thank',
    ];

    /**
     * @return array{answer: string, source: string, faq_id: int|null}
     */
    public function ask(string $question, ?int $userId = null): array
    {
        $question = trim($question);
        [$faq, $score] = $this->bestMatch($question);

        if ($faq && $score >= config('wedplan.chatbot.threshold')) {
            $result = ['answer' => $faq->answer, 'source' => 'faq', 'faq_id' => $faq->id];
        } elseif ($generated = $this->askGemini($question)) {
            $result = ['answer' => $generated, 'source' => 'ai', 'faq_id' => null];
        } else {
            $result = ['answer' => config('wedplan.chatbot.fallback'), 'source' => 'fallback', 'faq_id' => null];
        }

        if ($guidance = $this->catalogGuidance($question)) {
            $result['answer'] .= "\n\n".$guidance;
        }

        ChatbotSession::create([
            'user_id' => $userId,
            'faq_id' => $result['faq_id'],
            'question' => $question,
            'answer' => $result['answer'],
            'api_used' => $result['source'] === 'ai',
        ]);

        return $result;
    }

    /**
     * @return array{0: Faq|null, 1: float}
     */
    public function bestMatch(string $question): array
    {
        $input = $this->tokens($question);

        if ($input->isEmpty()) {
            return [null, 0.0];
        }

        $best = null;
        $bestScore = 0.0;

        foreach (Faq::all() as $faq) {
            $terms = $this->tokens($faq->question.' '.str_replace(',', ' ', (string) $faq->keywords));
            $score = $input->intersect($terms)->count() / $input->count();

            if ($score > $bestScore) {
                [$best, $bestScore] = [$faq, $score];
            }
        }

        return [$best, round($bestScore, 2)];
    }

    /** Lower-cased, de-pluralized content words. */
    public function tokens(string $text): Collection
    {
        return collect(preg_split('/[^a-z0-9]+/', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($w) => strlen($w) > 2 && ! in_array($w, self::STOPWORDS, true))
            ->map(fn ($w) => strlen($w) > 3 ? preg_replace('/(ies|es|s)$/', '', $w) : $w)
            ->unique()
            ->values();
    }

    /** Guides the client through the supplier catalog when a category is mentioned. */
    private function catalogGuidance(string $question): ?string
    {
        $words = $this->tokens($question);

        $category = collect(Supplier::CATEGORIES)->first(function ($cat) use ($words) {
            return $this->tokens($cat)->intersect($words)->isNotEmpty();
        });

        if (! $category) {
            return null;
        }

        $names = Supplier::where('category', $category)->where('availability', 'available')->orderBy('name')->limit(5)->pluck('name');

        if ($names->isEmpty()) {
            return "We don't have available {$category} suppliers listed right now. Your planner can recommend one.";
        }

        return "Available {$category} suppliers in our catalog: ".$names->join(', ').'. Open the Supplier Catalog to view their profiles and mark your preferences.';
    }

    private function askGemini(string $question): ?string
    {
        $key = config('wedplan.chatbot.gemini_key');

        if (! $key) {
            return null;
        }

        $business = config('wedplan.business_name');
        $context = Faq::limit(40)->get(['question', 'answer'])
            ->map(fn ($f) => "Q: {$f->question}\nA: {$f->answer}")->join("\n\n");

        $system = "You are WedBot, the assistant of {$business}, a wedding decoration company in Cebu City, Philippines. "
            .'Only answer questions about wedding planning, our services, and how to use the WedPlanConnect client portal. '
            .'Never invent prices, dates, or supplier commitments; refer those to the Wedding Planner. '
            .'Keep answers under 90 words. If the question is out of scope, say so and suggest contacting the Wedding Planner.'
            ."\n\nKnown FAQ entries:\n".$context;

        try {
            $response = Http::timeout(12)
                ->withHeaders(['x-goog-api-key' => $key])
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('wedplan.chatbot.gemini_model').':generateContent', [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
                ]);

            $text = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;

            return $text ? trim($text) : null;
        } catch (Throwable $e) {
            Log::warning('Gemini fallback failed: '.$e->getMessage());

            return null;
        }
    }
}
