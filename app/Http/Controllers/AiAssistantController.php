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

        // Past turns for context; the service also caps their total length.
        $history = AiConversation::where('user_id', auth()->id())
            ->latest()
            ->limit(AiAssistantService::HISTORY_LIMIT)
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
            'response'   => $result['response'],
            'id'         => $convoId,
            'delete_url' => route('ai-assistant.destroy', $convoId),
        ]);
    }

    /** Delete one question and its answer. Users can only delete their own. */
    public function destroy(Request $request, AiConversation $conversation)
    {
        $this->authorize('use-ai-assistant');

        // 404 rather than 403 so other users' conversation IDs are not revealed.
        abort_unless((int) $conversation->user_id === (int) auth()->id(), 404);

        $conversation->delete();

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('ai-assistant.index')->with('success', 'Question deleted.');
    }

    public function clear()
    {
        $this->authorize('use-ai-assistant');

        AiConversation::where('user_id', auth()->id())->delete();

        return response()->json(['success' => true]);
    }
}
