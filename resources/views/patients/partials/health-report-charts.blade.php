{{--
    Health report charts (x-ui.chart, ApexCharts). Kept in its own partial so
    the page still renders (with tables only) if the chart component is missing.
--}}
<div class="row g-3">
    <div class="col-lg-6">
        <x-ui.card class="h-100">
            <x-ui.chart type="line" title="Clinic visits" subtitle="Last 6 months"
                :series="[['name' => 'Visits', 'data' => $monthly['data']]]"
                :categories="$monthly['labels']" height="260"
                empty="No clinic visits in the last 6 months." />
        </x-ui.card>
    </div>
    <div class="col-lg-6">
        <x-ui.card class="h-100">
            @if ($severity)
                <x-ui.chart type="donut" title="Visits by severity"
                    :series="array_values($severity)" :labels="array_keys($severity)" height="260" />
            @else
                @php $top = array_slice($reasons, 0, 6, true); @endphp
                <x-ui.chart type="line" title="Most frequent reasons"
                    :series="[['name' => 'Visits', 'data' => array_values($top)]]"
                    :categories="array_keys($top)" category-label="Reason" height="260"
                    empty="No clinic visits recorded." />
            @endif
        </x-ui.card>
    </div>

    @if (count($vitals['labels']) >= 2)
        @if (array_filter($vitals['temperature'], fn ($v) => $v !== null))
            <div class="col-lg-6">
                <x-ui.card class="h-100">
                    <x-ui.chart type="line" title="Temperature" subtitle="°C, by visit"
                        :series="[['name' => 'Temperature', 'data' => $vitals['temperature']]]"
                        :categories="$vitals['labels']" y-format="decimal" height="240" />
                </x-ui.card>
            </div>
        @endif
        @if (array_filter($vitals['systolic'], fn ($v) => $v !== null))
            <div class="col-lg-6">
                <x-ui.card class="h-100">
                    <x-ui.chart type="line" title="Blood pressure" subtitle="mmHg, by visit"
                        :series="[['name' => 'Systolic', 'data' => $vitals['systolic']], ['name' => 'Diastolic', 'data' => $vitals['diastolic']]]"
                        :categories="$vitals['labels']" height="240" />
                </x-ui.card>
            </div>
        @endif
        @if (array_filter($vitals['pulse'], fn ($v) => $v !== null))
            <div class="col-lg-6">
                <x-ui.card class="h-100">
                    <x-ui.chart type="line" title="Pulse" subtitle="beats per minute"
                        :series="[['name' => 'Pulse', 'data' => $vitals['pulse']]]"
                        :categories="$vitals['labels']" height="240" />
                </x-ui.card>
            </div>
        @endif
        @if (array_filter($vitals['weight'], fn ($v) => $v !== null))
            <div class="col-lg-6">
                <x-ui.card class="h-100">
                    <x-ui.chart type="line" title="Weight" subtitle="kg"
                        :series="[['name' => 'Weight', 'data' => $vitals['weight']]]"
                        :categories="$vitals['labels']" y-format="decimal" height="240" />
                </x-ui.card>
            </div>
        @endif
    @endif
</div>
