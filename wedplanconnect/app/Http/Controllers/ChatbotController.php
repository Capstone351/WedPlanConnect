<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Services\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UC-11 Use AI Chatbot / FAQ Assistant.
 */
class ChatbotController extends Controller
{
    public function ask(Request $request, ChatbotService $chatbot): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:500']]);

        $result = $chatbot->ask($data['message'], $request->user()?->id);

        return response()->json([
            'answer' => $result['answer'],
            'source' => $result['source'],
        ]);
    }

    /** FAQ categories and questions for browsing without typing. */
    public function faqs(): JsonResponse
    {
        $grouped = Faq::orderBy('category')->orderBy('question')->get(['id', 'category', 'question', 'answer'])
            ->groupBy('category')
            ->map(fn ($items) => $items->map->only('id', 'question', 'answer')->values());

        return response()->json($grouped);
    }
}
