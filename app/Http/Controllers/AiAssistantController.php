<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\AiPendingAction;
use App\Services\AiAssistantService;
use App\Services\Coco\CocoActions;
use Illuminate\Http\Request;

class AiAssistantController extends Controller
{
    public function __construct(
        private readonly AiAssistantService $ai,
        private readonly CocoActions $actions,
    ) {}

    public function index()
    {
        $this->authorize('use-ai-assistant');

        if (! settings('ai_enabled', true)) {
            return view('ai-assistant.disabled');
        }

        $conversations = AiConversation::where('user_id', auth()->id())
            ->with('actions')
            ->latest()
            ->limit(30)
            ->get();

        // Action cards under past answers: conversation id => cards.
        $cards = $conversations
            ->filter(fn ($c) => $c->actions->isNotEmpty())
            ->mapWithKeys(fn ($c) => [$c->id => $c->actions->sortBy('created_at')->map(fn ($a) => $this->actions->card($a))->values()])
            ->all();

        return view('ai-assistant.index', compact('conversations', 'cards'));
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

        // Past turns for context (with what became of their cards); the service also caps their total length.
        $history = AiConversation::where('user_id', auth()->id())
            ->with('actions')
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

        // Cards prepared while answering belong to this question and answer.
        $actions = collect($result['actions'] ?? []);
        if ($actions->isNotEmpty()) {
            AiPendingAction::whereIn('id', $actions->pluck('id'))->update(['conversation_id' => $convoId]);
        }

        return response()->json([
            'response'   => $result['response'],
            'id'         => $convoId,
            'delete_url' => route('ai-assistant.destroy', $convoId),
            'actions'    => $actions->map(fn (AiPendingAction $a) => $this->actions->card($a))->values(),
        ]);
    }

    /** Delete one question and its answer. Users can only delete their own. */
    public function destroy(Request $request, AiConversation $conversation)
    {
        $this->authorize('use-ai-assistant');

        // 404 rather than 403 so other users' conversation IDs are not revealed.
        abort_unless((int) $conversation->user_id === (int) auth()->id(), 404);

        // A card that disappears from the chat can no longer be confirmed.
        $conversation->actions()->where('status', AiPendingAction::PENDING)->update(['status' => AiPendingAction::CANCELLED]);
        $conversation->delete();

        return $request->expectsJson()
            ? response()->json(['success' => true])
            : redirect()->route('ai-assistant.index')->with('success', 'Question deleted.');
    }

    public function clear()
    {
        $this->authorize('use-ai-assistant');

        AiPendingAction::where('user_id', auth()->id())->where('status', AiPendingAction::PENDING)->update(['status' => AiPendingAction::CANCELLED]);
        AiConversation::where('user_id', auth()->id())->delete();

        return response()->json(['success' => true]);
    }
}
