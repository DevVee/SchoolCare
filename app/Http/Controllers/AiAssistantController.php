<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Services\AiAssistantService;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(private readonly AiAssistantService $ai) {}

    public function index()
    {
        $this->authorize('use-ai-assistant');

        if (! settings('ai_enabled', true)) {
            return view('ai-assistant.disabled');
        }

        $conversations = AiConversation::where('user_id', auth()->id())
            ->latest()
            ->limit(30)
            ->get();

        return view('ai-assistant.index', compact('conversations'));
    }

    public function chat(Request $request)
    {
        $this->authorize('use-ai-assistant');

        if (! settings('ai_enabled', true)) {
            return response()->json([
                'response' => (settings('ai_assistant_name') ?: 'The assistant').' is turned off by an administrator.',
                'disabled' => true,
            ], 503);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        // Pass last 12 turns for context (AiAssistantService::HISTORY_LIMIT)
        $history = AiConversation::where('user_id', auth()->id())
            ->latest()
            ->limit(12)
            ->get();

        /** @var string $message */
        $message = $request->input('message');

        $result = $this->ai->chat($message, $history);

        $convo = AiConversation::create([
            'user_id'     => auth()->id(),
            'message'     => $message,
            'response'    => $result['response'],
            'tokens_used' => $result['tokens'],
        ]);

        /** @var int $convoId */
        $convoId = $convo->id;

        return response()->json([
            'response' => $result['response'],
            'id'       => $convoId,
        ]);
    }

    public function clear()
    {
        $this->authorize('use-ai-assistant');

        AiConversation::where('user_id', auth()->id())->delete();

        return response()->json(['success' => true]);
    }
}
