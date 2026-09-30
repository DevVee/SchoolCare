{{--
    A drawn preview of the dashboard (HTML and CSS, not a screenshot): three stat
    tiles, who is in the clinic now and the assistant's brief. It is an
    illustration, so it is one image to screen readers and says so underneath.
    With the script, the numbers count up and the rows slide in (landing.js adds
    .is-live); without it, everything simply shows. Expects: $product.
--}}
@php $p = $product['preview']; @endphp
<figure class="lp-preview">
    <div class="lp-window lp-live" role="img" aria-label="{{ $p['label'] }}">
        <div class="lp-window-bar">
            <span class="lp-window-dots"><i></i><i></i><i></i></span>
            <span class="lp-window-title">{{ $p['title'] }}</span>
            <span class="lp-live-pill is-open"><span class="lp-dot"></span>Live</span>
        </div>
        <div class="lp-window-body">
            <div class="lp-window-head">
                <span class="lp-window-greet">{{ $p['greeting'] }}</span>
                <span class="lp-window-heading">{{ $p['heading'] }}</span>
            </div>

            <div class="lp-mock-stats">
                @foreach ($p['stats'] as $i => $s)
                    <div class="lp-mock-stat lp-anim" style="--i: {{ $i }}">
                        <span class="lp-mock-label">{{ $s['label'] }}</span>
                        <span class="lp-mock-value" data-lp-count="{{ $s['value'] }}">{{ $s['value'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="lp-mock-card">
                <p class="lp-mock-title">{{ $p['in_clinic_title'] }}<span class="lp-mock-badge">{{ count($p['in_clinic']) }}</span></p>
                <ul class="lp-mock-list">
                    @foreach ($p['in_clinic'] as $i => $row)
                        <li class="lp-mock-row lp-anim" style="--i: {{ $i + 3 }}">
                            <span class="lp-mock-who">{{ $row['who'] }}<small>{{ $row['why'] }}</small></span>
                            <span class="lp-mock-time">{{ $row['time'] }}</span>
                            <span class="lp-mock-state">{{ $row['state'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="lp-mock-card lp-mock-brief">
                <p class="lp-mock-title"><i class="bi bi-stars"></i>{{ $p['brief_title'] }}</p>
                <ul class="lp-mock-lines">
                    @foreach ($p['brief'] as $i => $line)
                        <li class="lp-anim" style="--i: {{ $i + 6 }}">{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
    <figcaption class="lp-preview-caption">{{ $p['caption'] }}</figcaption>
</figure>
