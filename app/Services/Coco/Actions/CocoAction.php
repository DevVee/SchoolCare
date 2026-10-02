<?php

namespace App\Services\Coco\Actions;

use App\Models\User;
use App\Services\Coco\CocoRefusal;
use Illuminate\Support\Str;

/**
 * One kind of action the assistant can propose (App\Services\Coco\CocoActions).
 *
 * The model only ever reaches propose(): it checks the arguments and returns
 * what to store. plan() runs again on Confirm, against the current records,
 * for the recipient the user picked and with the user's edits, and execute()
 * then does it through the same services the app uses by hand.
 */
abstract class CocoAction
{
    /** Tool name the model calls; also the stored type. */
    abstract public function type(): string;

    /** Card title, e.g. "Send SMS". */
    abstract public function title(): string;

    /** Bootstrap Icons name (without "bi-"). */
    abstract public function icon(): string;

    /** Which admin switch turns it on: messages | appointments | settings. */
    abstract public function group(): string;

    /** The user's own permissions allow it (checked again on every step). */
    abstract public function allowedFor(User $user): bool;

    /** Function tool definition in the OpenAI format. */
    abstract public function tool(): array;

    /**
     * Check the model's arguments and resolve who or what they mean.
     *
     * @return array{payload: array, candidates: array<int, array{id:string,label:string,detail?:string,meta?:string}>}
     *               candidates: choices for the user when more than one record matched
     *               (the ids are stored in the payload; one candidate is chosen automatically)
     *
     * @throws CocoRefusal
     */
    abstract public function propose(array $args, User $user): array;

    /**
     * Everything checked again for one choice, with the user's edits.
     *
     * @param  array{message?:string,subject?:string}  $edits
     * @return array plan with at least:
     *               summary  one line for the audit log, e.g. "Send SMS to Maria Santos (guardian)"
     *               fields   [['label' => ..., 'value' => ..., 'recipient' => bool], ...] for the card
     *               editable null, or ['kind' => 'sms'|'email', 'message' => ..., 'subject' => ?, 'max' => int]
     *               notes    warnings shown on the card
     *
     * @throws CocoRefusal
     */
    abstract public function plan(array $payload, ?string $choice, array $edits, User $user): array;

    /**
     * Do it.
     *
     * @return array{ok: bool, text: string, url?: ?string}
     */
    abstract public function execute(array $plan, User $user): array;

    // ── Helpers ─────────────────────────────────────────────────────────────

    protected function makeTool(string $description, array $properties, array $required = []): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $this->type(),
                'description' => $description,
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => $properties,
                    'required'   => $required,
                ],
            ],
        ];
    }

    /** A trimmed string argument, '' when missing or not a string. */
    protected function str(array $args, string $key, int $max = 5000): string
    {
        $value = $args[$key] ?? '';

        return is_scalar($value) ? trim(mb_substr((string) $value, 0, $max)) : '';
    }

    /** Text written by the model for people to read: no em or en dashes (owner rule). */
    protected function plain(string $text): string
    {
        $text = str_replace("\u{2011}", '-', $text);
        $text = preg_replace('/(\S)[–—](\S)/u', '$1-$2', $text) ?? $text;
        $text = preg_replace('/\h*[–—]\h*/u', ', ', $text) ?? $text;

        return trim($text);
    }

    /** The chosen candidate id, checked against the stored ones. */
    protected function chosen(array $payload, ?string $choice): string
    {
        $ids = array_map('strval', $payload['candidates'] ?? []);

        if (count($ids) === 1 && ($choice === null || $choice === '')) {
            return $ids[0];
        }

        if ($choice === null || $choice === '') {
            throw CocoRefusal::because('Pick who this is for first.');
        }

        if (! in_array((string) $choice, $ids, true)) {
            throw CocoRefusal::because('That choice is not one of the options on this card.');
        }

        return (string) $choice;
    }

    protected function need(bool $condition, string $reason): void
    {
        if (! $condition) {
            throw CocoRefusal::because($reason);
        }
    }

    protected function limit(string $text, int $length): string
    {
        return Str::limit($text, $length, '...');
    }
}
