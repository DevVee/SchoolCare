{{--
    Expiry cell: the date plus a soft status badge ("Expired", "Expires in 5 days").
    @include('medicines.partials.expiry', ['date' => $med->expiration_date])
    Optional:
      'daysLeft' => int      days until expiry (negative when expired); computed when omitted
      'soon'     => bool     show the countdown badge (default: within the expiry warning window)
      'badge'    => bool     false shows the date only
      'ago'      => bool     expired badge reads "Expired 3 days ago" instead of "Expired"
      'empty'    => string   text when there is no date (default "-")
--}}
@php
    $expDate = $date ?? null;
    $expDays = $daysLeft ?? ($expDate ? (int) today()->diffInDays($expDate->copy()->startOfDay(), false) : null);
    $expSoon = $soon ?? ($expDays !== null && $expDays <= \App\Models\Medicine::expiryWarningDays());
    $expBadge = $badge ?? true;
    $expLabel = null;
    $expStatus = null;
    if ($expDate && $expBadge) {
        if ($expDays < 0) {
            $expStatus = 'expired';
            $expLabel = ($ago ?? false)
                ? 'Expired '.abs($expDays).' '.\Illuminate\Support\Str::plural('day', abs($expDays)).' ago'
                : 'Expired';
        } elseif ($expSoon) {
            $expStatus = 'expiring';
            $expLabel = match (true) {
                $expDays === 0 => 'Expires today',
                $expDays === 1 => 'Expires tomorrow',
                default => 'Expires in '.$expDays.' days',
            };
        }
    }
@endphp
@if ($expDate)
    <span class="d-inline-flex align-items-center gap-2">
        <span class="tabular">{{ $expDate->format('M j, Y') }}</span>
        @if ($expStatus)
            <x-ui.badge :color="$expStatus === 'expired' ? 'danger' : 'orange'" :icon="$expStatus === 'expired' ? 'calendar-x' : 'hourglass-split'" size="sm">{{ $expLabel }}</x-ui.badge>
        @endif
    </span>
@else
    <span class="text-muted">{{ $empty ?? '-' }}</span>
@endif
