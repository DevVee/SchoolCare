<?php

namespace App\Http\Controllers;

use App\Models\AiPendingAction;
use App\Services\Coco\CocoActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirm or cancel a card the assistant prepared. Only these endpoints run an
 * action, and only for the user the card belongs to (App\Services\Coco\CocoActions).
 */
class AiAssistantActionController extends Controller
{
    public function __construct(private readonly CocoActions $actions) {}

    public function confirm(Request $request, AiPendingAction $action): JsonResponse
    {
        $this->authorize('use-ai-assistant');
        // 404 rather than 403 so other users' cards are not revealed.
        abort_unless((int) $action->user_id === (int) $request->user()->id, 404);

        $input = $request->validate([
            'message' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:300'],
            'choice'  => ['nullable', 'string', 'max:64'],
        ]);

        $outcome = $this->actions->confirm($action, $request->user(), $input);

        return response()->json($outcome + ['card' => $this->actions->card($action->fresh())]);
    }

    public function cancel(Request $request, AiPendingAction $action): JsonResponse
    {
        $this->authorize('use-ai-assistant');
        abort_unless((int) $action->user_id === (int) $request->user()->id, 404);

        $outcome = $this->actions->cancel($action, $request->user());

        return response()->json($outcome + ['card' => $this->actions->card($action->fresh())]);
    }
}
