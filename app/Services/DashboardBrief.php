<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The short "what is happening today" brief at the top of the dashboard.
 *
 * Built from ClinicSnapshot (counts and medicine names only, limited to what
 * the user may see). One quick AI call turns the facts into 3 to 5 calm
 * sentences; when the AI is off, unset, slow or returns anything unexpected,
 * the same facts are written by fixed rules instead. Links are always set
 * here from the fact type, never taken from the model.
 *
 * Shape: ['lines' => [['text' => string, 'href' => ?string]], 'source' => 'ai'|'rules', 'generated_at' => ISO 8601]
 */
class DashboardBrief
{
    private const TTL       = 600;  // Seconds, per date and permission set
    private const MIN_LINES = 3;
    private const MAX_LINES = 5;
    private const MAX_CHARS = 120;  // The model is asked for 100; a little slack before rejecting

    public function __construct(
        private readonly ClinicSnapshot $snapshot,
        private readonly AiAssistantService $ai,
    ) {}

    public function for(?User $user): array
    {
        $areas = $this->snapshot->areasFor($user);
        $key   = 'dashboard_brief:'.today()->toDateString().':'.($areas ? implode(',', $areas) : 'none');

        return Cache::remember($key, self::TTL, fn () => $this->build($user));
    }

    private function build(?User $user): array
    {
        try {
            $facts = $this->facts($this->snapshot->forUser($user));
        } catch (\Throwable $e) {
            Log::warning('Dashboard brief snapshot failed', ['error' => $e->getMessage()]);
            $facts = [];
        }

        $lines  = $facts !== [] && settings('ai_enabled', true) ? $this->aiLines($facts) : null;
        $source = $lines === null ? 'rules' : 'ai';
        $lines ??= $this->ruleLines($facts);

        if ($lines === []) {
            $lines = [['text' => 'Nothing to report for your role today.', 'href' => null]];
        }

        return ['lines' => $lines, 'source' => $source, 'generated_at' => now()->toIso8601String()];
    }

    // ── Facts ────────────────────────────────────────────────────────────────

    /**
     * Each fact: topic (sets the link), weight (higher = more important) and
     * a plain sentence used by the rules brief and given to the AI.
     *
     * @return list<array{topic: string, weight: int, text: string}>
     */
    private function facts(array $s): array
    {
        $facts = [];
        $add   = function (string $topic, int $weight, string $text) use (&$facts) {
            $facts[] = ['topic' => $topic, 'weight' => $weight, 'text' => $text];
        };

        if (isset($s['medicines'])) {
            $m    = $s['medicines'];
            $out  = (int) $m['out_of_stock'];
            $low  = max(0, (int) $m['low_stock_total'] - $out);
            $outN = $this->names(array_filter($m['low_stock'], fn ($r) => $r['qty'] <= 0), $out);
            $lowN = $this->names(array_filter($m['low_stock'], fn ($r) => $r['qty'] > 0), $low);

            if ($out > 0) {
                $add('stock', 100, $this->count($out, 'medicine', 'is', 'are')." out of stock: {$outN}."
                    .($low > 0 ? " {$low} more ".($low === 1 ? 'is' : 'are').' running low.' : ''));
            } elseif ($low > 0) {
                $add('stock', 70, $this->count($low, 'medicine', 'is', 'are')." running low: {$lowN}.");
            } else {
                $add('stock', 5, 'No medicines are low on stock.');
            }

            if ($m['expired_in_stock'] > 0) {
                $add('expiring', 80, $this->count((int) $m['expired_in_stock'], 'expired medicine', 'is', 'are').' still in stock and should be disposed of.');
            }

            if ($m['expiring_total'] > 0) {
                $first = $m['expiring'][0] ?? null;
                $add('expiring', 50, $this->count((int) $m['expiring_total'], 'medicine').' '.((int) $m['expiring_total'] === 1 ? 'expires' : 'expire')
                    ." within {$m['expiry_days']} days".($first ? ", first {$first['short']} on {$first['expires']}" : '').'.');
            } else {
                $add('expiring', 3, "No medicines expire in the next {$m['expiry_days']} days.");
            }
        }

        if (isset($s['appointments'])) {
            $a = $s['appointments'];

            if ($a['pending'] > 0) {
                $days = (int) $a['oldest_pending_days'];
                $add('appointments', $days >= 3 ? 95 : 90, $this->count((int) $a['pending'], 'appointment request', 'is', 'are').' waiting for approval'
                    .($days >= 1 ? ', the oldest for '.$this->count($days, 'day').'.' : ', all from today.'));
            } else {
                $add('appointments', 4, 'No appointment requests are waiting for approval.');
            }

            $approved = (int) ($a['today']['approved'] ?? 0);
            $add('appointments_today', $approved > 0 ? 20 : 2, $approved > 0
                ? $this->count($approved, 'approved appointment').' scheduled for today.'
                : 'No approved appointments are scheduled for today.');
        }

        if (isset($s['consultations'])) {
            $n = (int) $s['consultations']['today'];
            $add('consultations', $n > 0 ? 12 : 1, $n > 0
                ? $this->count($n, 'consultation').' recorded today.'
                : 'No consultations recorded yet today.');
        }

        if (isset($s['intake']) && $s['intake']['pending'] > 0) {
            $add('intake', 60, $this->count((int) $s['intake']['pending'], 'online health form', 'is', 'are').' waiting for review.');
        }

        if (isset($s['visits'])) {
            $v   = $s['visits'];
            $avg = (float) $v['weekday_average'];

            if ($v['today'] > 0) {
                $busy = $avg > 0 && $v['today'] >= 5 && $v['today'] >= $avg * 1.5;
                $add('visits', $busy ? 40 : 25, $this->count((int) $v['today'], 'visit').' so far today'
                    .($v['in_clinic'] > 0 ? ", {$v['in_clinic']} still in the clinic" : '')
                    .($avg > 0 ? ". A usual {$v['weekday']} has about ".$this->number($avg).'.' : '.'));
            } else {
                $add('visits', 10, 'No clinic visits logged yet today'.($avg > 0 ? ". A usual {$v['weekday']} has about ".$this->number($avg).'.' : '.'));
            }

            if ($v['top_reason']) {
                $add('visits', 15, "{$v['top_reason']['reason']} is the top reason for visits this month ("
                    .$this->count((int) $v['top_reason']['total'], 'visit').').');
            }
        }

        return $facts;
    }

    /** "3 medicines are" style counts; the verb is optional. */
    private function count(int $n, string $noun, string $one = '', string $many = ''): string
    {
        return trim($n.' '.Str::plural($noun, $n).' '.($n === 1 ? $one : $many));
    }

    private function number(float $n): string
    {
        return $n == (int) $n ? (string) (int) $n : number_format($n, 1);
    }

    /** "A and B", or "A, B and 3 more". */
    private function names(array $rows, int $total): string
    {
        $names = array_slice(array_column(array_values($rows), 'short'), 0, 2);
        $more  = $total - count($names);

        if ($names === []) {
            return "{$total} items";
        }
        if ($more > 0) {
            return implode(', ', $names)." and {$more} more";
        }

        return implode(' and ', $names);
    }

    private function href(string $topic): ?string
    {
        return match ($topic) {
            'stock'              => route('medicines.low-stock'),
            'expiring'           => route('medicines.expiring'),
            'appointments'       => route('appointments.index', ['status' => 'pending']),
            'appointments_today' => route('appointments.index', ['date' => today()->toDateString()]),
            'intake'             => route('patients.intake.index'),
            'consultations'      => route('consultations.index'),
            'visits'             => route('patient-logs.index'),
            default              => null,
        };
    }

    // ── Rules brief ──────────────────────────────────────────────────────────

    private function ruleLines(array $facts): array
    {
        usort($facts, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        return array_map(
            fn ($f) => ['text' => Str::limit($f['text'], self::MAX_CHARS), 'href' => $this->href($f['topic'])],
            array_slice($facts, 0, self::MAX_LINES)
        );
    }

    // ── AI brief ─────────────────────────────────────────────────────────────

    /** 3 to 5 AI-written lines, or null when anything about the answer is off. */
    private function aiLines(array $facts): ?array
    {
        usort($facts, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        $list = [];
        foreach ($facts as $i => $f) {
            $list[] = ['id' => 'f'.($i + 1), 'importance' => $f['weight'], 'fact' => $f['text']];
        }

        $min = min(self::MIN_LINES, count($facts));
        $max = min(self::MAX_LINES, count($facts));

        $system = <<<PROMPT
You write the short daily brief at the top of a school clinic dashboard. Nurses and clinic staff read it at a glance.
- Use only the facts given. Do not add numbers, names, causes or advice that are not in the facts.
- Write at least {$min} and at most {$max} lines, most important first: things that need action (out of stock, waiting requests, expired stock) before general information. Use "all clear" facts only to reach {$min} lines.
- Each line is one calm, specific, plain sentence of at most 100 characters. Do not join facts into one line.
- No emoji, no exclamation marks, and no dashes used as punctuation. No patient names.
- Tag each line with the id of the main fact it is based on.
Reply with JSON only: {"lines":[{"text":"...","fact":"f1"}]}
PROMPT;

        $prompt = 'Today is '.now()->format('l, F j, Y').".\nFacts (higher importance first):\n"
            .json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $raw = $this->ai->complete($system, $prompt, json: true);
        if ($raw === null) {
            return null;
        }

        $data  = json_decode($raw, true);
        $items = is_array($data) ? ($data['lines'] ?? (array_is_list($data) ? $data : null)) : null;

        if (! is_array($items) || count($items) < $min || count($items) > $max) {
            Log::info('Dashboard brief: unexpected AI answer, using rules', ['answer' => mb_substr($raw, 0, 300)]);

            return null;
        }

        $topics = [];
        foreach ($facts as $i => $f) {
            $topics['f'.($i + 1)] = $f['topic'];
        }

        $lines = [];
        foreach ($items as $item) {
            $text = is_array($item) ? ($item['text'] ?? null) : null;
            if (! is_string($text)) {
                return null;
            }

            $text = $this->ai->clean($text);
            $text = trim(preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}]/u', '', $text) ?? '');

            if ($text !== '' && ! preg_match('/[.!?)]$/u', $text)) {
                $text .= '.';
            }

            if ($text === '' || mb_strlen($text) > self::MAX_CHARS) {
                Log::info('Dashboard brief: AI line rejected, using rules', ['line' => mb_substr($text, 0, 200)]);

                return null;
            }

            $topic   = $topics[(string) ($item['fact'] ?? '')] ?? null;
            $lines[] = ['text' => $text, 'href' => $topic ? $this->href($topic) : null];
        }

        return $lines;
    }
}
