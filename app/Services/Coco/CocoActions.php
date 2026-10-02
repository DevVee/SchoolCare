<?php

namespace App\Services\Coco;

use App\Models\AiPendingAction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Coco\Actions\BookAppointment;
use App\Services\Coco\Actions\CancelAppointment;
use App\Services\Coco\Actions\CocoAction;
use App\Services\Coco\Actions\RescheduleAppointment;
use App\Services\Coco\Actions\SendEmail;
use App\Services\Coco\Actions\SendSms;
use App\Services\Coco\Actions\UpdateSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Actions the assistant can prepare, always with a person's confirmation.
 *
 *   1. The model calls an action tool. propose() checks everything (the
 *      admin switches, the user's permissions, the recipient, the slot, the
 *      setting) and stores a pending AiPendingAction with a preview. Nothing
 *      else happens: the model can only propose.
 *   2. The chat shows the preview as a card with Confirm and Cancel.
 *   3. Confirm (a click, never a model turn) calls confirm(), which checks it
 *      all again, runs it through the app's own services, writes the audit
 *      log and stores the result. A second Confirm returns the stored result.
 *
 * Proposals expire after TTL_MINUTES. A user may confirm HOURLY_LIMIT actions an hour.
 */
class CocoActions
{
    public const TTL_MINUTES  = 10;
    public const HOURLY_LIMIT = 20;
    public const PER_TURN     = 3;

    /** @var array<string, CocoAction> */
    private array $handlers = [];

    public function __construct(
        SendSms $sms,
        SendEmail $email,
        BookAppointment $book,
        RescheduleAppointment $reschedule,
        CancelAppointment $cancel,
        UpdateSetting $setting,
    ) {
        foreach ([$sms, $email, $book, $reschedule, $cancel, $setting] as $handler) {
            $this->handlers[$handler->type()] = $handler;
        }
    }

    // ── Switches ────────────────────────────────────────────────────────────

    /** Admin > Settings > AI Assistant > "Let the assistant take actions" (off by default). */
    public static function enabled(): bool
    {
        return (bool) settings('ai_actions_enabled', false);
    }

    /** The master switch and the switch of one group: messages, appointments or settings. */
    public static function groupEnabled(string $group): bool
    {
        return self::enabled() && (bool) settings("ai_actions_{$group}", true);
    }

    public function handler(string $type): ?CocoAction
    {
        return $this->handlers[$type] ?? null;
    }

    public function isAction(string $name): bool
    {
        return isset($this->handlers[$name]);
    }

    /** @return array<string, CocoAction> actions this user may be offered right now */
    public function available(User $user): array
    {
        return array_filter($this->handlers, fn (CocoAction $h) => self::groupEnabled($h->group()) && $h->allowedFor($user));
    }

    // ── 1. Propose ──────────────────────────────────────────────────────────

    /** @throws CocoRefusal */
    public function propose(string $type, array $args, User $user): AiPendingAction
    {
        $handler = $this->available($user)[$type] ?? null;
        if (! $handler) {
            throw CocoRefusal::because('This action is turned off or not allowed for this user.');
        }

        $proposal = $handler->propose($args, $user);
        $payload  = $proposal['payload'];
        $ids      = array_values(array_map('strval', $payload['candidates'] ?? []));
        $several  = count($proposal['candidates']) > 1;

        // Preview of the first choice; with several, the parts about one person are left out.
        $plan   = $handler->plan($payload, $ids[0] ?? null, [], $user);
        $fields = $several ? array_values(array_filter($plan['fields'], fn ($f) => empty($f['recipient']))) : $plan['fields'];

        $summary = $several ? $handler->title().' (the user picks from '.count($proposal['candidates']).' matches)' : $plan['summary'];

        $action = AiPendingAction::create([
            'user_id'    => $user->id,
            'type'       => $type,
            'payload'    => $payload,
            'preview'    => [
                'title'      => $handler->title(),
                'icon'       => $handler->icon(),
                'summary'    => $summary,
                'fields'     => array_map(fn ($f) => ['label' => $f['label'], 'value' => (string) $f['value']], $fields),
                'candidates' => $several ? $proposal['candidates'] : [],
                'editable'   => $plan['editable'] ?? null,
                'notes'      => $plan['notes'] ?? [],
            ],
            'status'     => AiPendingAction::PENDING,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        // A trail of what was proposed, including cards that are later cancelled or expire.
        AuditLogService::log(
            action: 'proposed',
            module: 'ai-assistant',
            description: Str::limit(self::assistantName()." prepared a card for {$user->name}: {$summary}. Nothing is done until they confirm.", 1000),
        );

        return $action;
    }

    // ── 3. Confirm / cancel ─────────────────────────────────────────────────

    /**
     * Run a pending action for the user who tapped Confirm.
     *
     * @param  array{message?:?string, subject?:?string, choice?:?string}  $input  the user's edits and pick
     * @return array{ok: bool, text: string, status: string, url?: ?string}
     */
    public function confirm(AiPendingAction $action, User $user, array $input = []): array
    {
        if ((int) $action->user_id !== (int) $user->id) {
            return $this->reply(false, 'This card belongs to someone else.', $action->status);
        }

        if (! $action->isPending()) {
            return $this->outcome($action);
        }

        if ($action->hasExpired()) {
            $action->update(['status' => AiPendingAction::EXPIRED]);

            return $this->outcome($action);
        }

        $handler = $this->handler($action->type);
        if (! $handler || ! self::groupEnabled($handler->group())) {
            return $this->reply(false, 'An administrator has turned this off, so it cannot be done from the chat now.', $action->status);
        }

        $key = 'coco-actions:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::HOURLY_LIMIT)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            return $this->reply(false, 'You have confirmed '.self::HOURLY_LIMIT." actions in the last hour. Please wait about {$minutes} minutes.", $action->status);
        }

        $edits = array_filter([
            'message' => isset($input['message']) ? (string) $input['message'] : null,
            'subject' => isset($input['subject']) ? (string) $input['subject'] : null,
        ], fn ($v) => $v !== null);
        $choice = isset($input['choice']) && $input['choice'] !== '' ? (string) $input['choice'] : null;

        try {
            $plan = $handler->plan($action->payload ?? [], $choice, $edits, $user);
        } catch (CocoRefusal $e) {
            // Still pending: the user can fix the text, pick again, or cancel.
            return $this->reply(false, $e->getMessage(), $action->status);
        } catch (\Throwable $e) {
            Log::error('Assistant action check failed', ['action' => $action->id, 'type' => $action->type, 'error' => $e->getMessage()]);

            return $this->reply(false, 'It could not be checked just now. Please try again, or do it on its page.', $action->status);
        }

        // Claim it: only one request can move it from pending, so a double click runs it once.
        $claimed = AiPendingAction::whereKey($action->id)
            ->where('status', AiPendingAction::PENDING)
            ->update(['status' => AiPendingAction::CONFIRMED, 'confirmed_at' => now()]);

        if ($claimed !== 1) {
            return $this->outcome($action->fresh());
        }

        RateLimiter::hit($key, 3600);

        try {
            $result = $handler->execute($plan, $user);
        } catch (\Throwable $e) {
            Log::error('Assistant action failed', ['action' => $action->id, 'type' => $action->type, 'error' => $e->getMessage()]);
            $result = ['ok' => false, 'text' => 'Something went wrong, so it was not done. Please try it on its page instead.'];
        }

        $audit = $result['audit'] ?? null;
        unset($result['audit']);

        $action->forceFill([
            'status' => $result['ok'] ? AiPendingAction::CONFIRMED : AiPendingAction::FAILED,
            'result' => $result + ['at' => now()->toIso8601String()],
        ])->save();

        AuditLogService::log(
            action: 'confirmed',
            module: 'ai-assistant',
            description: self::assistantName().', confirmed by '.$user->name.': '.$plan['summary'].'.'
                .($result['ok'] ? '' : ' Not done: '.Str::limit($result['text'], 200)),
            oldValues: $audit['old'] ?? null,
            newValues: $audit['new'] ?? ['action' => $action->type, 'result' => Str::limit($result['text'], 300)],
        );

        return $this->outcome($action);
    }

    public function cancel(AiPendingAction $action, User $user): array
    {
        if ((int) $action->user_id !== (int) $user->id) {
            return $this->reply(false, 'This card belongs to someone else.', $action->status);
        }

        $cancelled = AiPendingAction::whereKey($action->id)
            ->where('status', AiPendingAction::PENDING)
            ->update(['status' => AiPendingAction::CANCELLED, 'result' => json_encode(['ok' => false, 'text' => 'Cancelled. Nothing was done.'])]);

        $action->refresh();

        return $cancelled === 1 ? $this->reply(true, 'Cancelled. Nothing was done.', $action->status) : $this->outcome($action);
    }

    /** What happened to an action that is no longer pending. */
    private function outcome(AiPendingAction $action): array
    {
        $result = $action->result ?? [];

        return match ($action->status) {
            AiPendingAction::CONFIRMED, AiPendingAction::FAILED => $result
                ? $this->reply((bool) ($result['ok'] ?? false), (string) ($result['text'] ?? ''), $action->status, $result['url'] ?? null)
                : $this->reply(true, 'This is already being done.', $action->status),
            AiPendingAction::CANCELLED => $this->reply(false, 'This was cancelled. Nothing was done.', $action->status),
            AiPendingAction::EXPIRED   => $this->reply(false, 'This expired, so nothing was done. Ask again if you still need it.', $action->status),
            default                    => $this->reply(false, 'This is waiting for your confirmation.', $action->status),
        };
    }

    private function reply(bool $ok, string $text, string $status, ?string $url = null): array
    {
        return ['ok' => $ok, 'text' => $text, 'status' => $status, 'url' => $url];
    }

    // ── 2. Cards and notes ──────────────────────────────────────────────────

    /** The card the chat shows (resources/views/ai-assistant/index.blade.php). */
    public function card(AiPendingAction $action): array
    {
        $preview = $action->preview ?? [];
        $status  = $action->hasExpired() ? AiPendingAction::EXPIRED : $action->status;
        $ids     = array_map('strval', ($action->payload ?? [])['candidates'] ?? []);

        return [
            'id'           => $action->id,
            'type'         => $action->type,
            'title'        => $preview['title'] ?? Str::headline($action->type),
            'icon'         => $preview['icon'] ?? 'lightning',
            'status'       => $status,
            'fields'       => $preview['fields'] ?? [],
            'candidates'   => $preview['candidates'] ?? [],
            'choice'       => count($ids) === 1 ? $ids[0] : null,
            'editable'     => $preview['editable'] ?? null,
            'notes'        => $preview['notes'] ?? [],
            'expires_at'   => $action->expires_at?->toIso8601String(),
            'seconds_left' => $status === AiPendingAction::PENDING ? max(0, (int) now()->diffInSeconds($action->expires_at, false)) : 0,
            'result'       => $status === AiPendingAction::EXPIRED && ! $action->result
                ? ['ok' => false, 'text' => 'This expired, so nothing was done. Ask again if you still need it.']
                : $action->result,
            'confirm_url'  => route('ai-assistant.actions.confirm', $action),
            'cancel_url'   => route('ai-assistant.actions.cancel', $action),
        ];
    }

    /**
     * What the model is told after proposing. Details of the person are left
     * out when "Let the assistant read patient records" is off.
     */
    public function modelNote(AiPendingAction $action): array
    {
        $preview = $action->preview ?? [];
        $private = ! CocoTools::readPatientsEnabled();
        $several = ! empty($preview['candidates']);

        return array_filter([
            'status'  => 'waiting_for_user',
            'card'    => $preview['title'] ?? $action->type,
            'summary' => $private ? null : ($preview['summary'] ?? null),
            'choices' => $several ? count($preview['candidates']).' matching records are listed on the card for the user to pick from.' : null,
            'text'    => $preview['editable']['message'] ?? null,
            'next'    => 'A card with Confirm and Cancel is now shown under your reply. Nothing has been sent, booked or changed. '
                .'In one or two short sentences, say what the card will do and ask the user to check it and tap Confirm'
                .($several ? ' after picking the right person' : '').'. '
                .(empty($preview['editable']) ? 'The card cannot be edited: to change something, they cancel it and ask again. ' : 'They can edit the text on the card first. ')
                .'Do not say it is done.',
        ]);
    }

    /** One line for the history sent to the model, so it knows what became of a card. */
    public function historyNote(AiPendingAction $action): string
    {
        $status = $action->hasExpired() ? AiPendingAction::EXPIRED : $action->status;
        $title  = ($action->preview ?? [])['title'] ?? $action->type;
        $text   = (string) (($action->result ?? [])['text'] ?? '');

        $what = match ($status) {
            AiPendingAction::CONFIRMED => 'confirmed by the user and done',
            AiPendingAction::FAILED    => 'confirmed by the user but not done',
            AiPendingAction::CANCELLED => 'cancelled by the user, nothing was done',
            AiPendingAction::EXPIRED   => 'expired, nothing was done',
            default                    => 'still waiting for the user to confirm',
        };

        $detail = CocoTools::readPatientsEnabled() && $text !== '' && in_array($status, [AiPendingAction::CONFIRMED, AiPendingAction::FAILED], true)
            ? ": {$text}" : '.';

        return "[{$title} card: {$what}{$detail}]";
    }

    public static function assistantName(): string
    {
        return (string) (settings('ai_assistant_name') ?: 'Coco');
    }
}
