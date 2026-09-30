{{-- Test SMS: sends right away (even when SMS is off) and uses one credit. --}}
<x-ui.card title="Send a test message" subtitle="Check that text messages reach a phone. The test is sent right away, even when SMS is off, and uses 1 SMS credit.">
    <form method="POST" action="{{ route('admin.settings.test-sms') }}" class="row g-2 align-items-end">
        @csrf
        <x-ui.input wrapper-class="col-12 col-sm-7 col-md-5" name="test_number" id="test_number" type="tel" label="Mobile number"
            placeholder="09171234567" maxlength="20" inputmode="tel" autocomplete="off" help="A Philippine mobile number, for example 09171234567." />
        <div class="col-12 col-sm-auto pb-sm-4">
            <x-ui.button type="submit" variant="secondary" icon="send">Send test message</x-ui.button>
        </div>
    </form>
</x-ui.card>
