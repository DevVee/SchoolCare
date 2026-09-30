{{--
    Cancel appointment modal. Any element with .btn-cancel and data-action opens it
    (buttons, or x-ui.action-menu.item class="btn-cancel" :data-action="...").
    The reason is required only when Settings, Appointments,
    "Require a reason when cancelling" is on.
--}}
@php $reasonRequired = $reasonRequired ?? (bool) settings('appointment_cancel_reason_required', true); @endphp
@once
@push('modals')
<x-ui.modal id="cancelModal" title="Cancel appointment" subtitle="The patient gets a text message when text messages are turned on." size="sm">
    <form id="cancelForm" method="POST">
        @csrf @method('PATCH')
        <x-ui.field label="Reason for cancelling" for="cancelledReason" :required="$reasonRequired" :optional="! $reasonRequired">
            <textarea name="cancelled_reason" id="cancelledReason" class="form-control" rows="3" maxlength="500"
                      placeholder="For example: the doctor is not available that day" @required($reasonRequired)></textarea>
        </x-ui.field>
    </form>
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Keep appointment</x-ui.button>
        <x-ui.button type="submit" variant="danger" icon="x-circle" form="cancelForm">Cancel appointment</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-cancel[data-action]');
        if (!btn) return;
        e.preventDefault();
        const form = document.getElementById('cancelForm');
        form.action = btn.dataset.action;
        form.reset();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('cancelModal')).show();
    });
});
</script>
@endpush
@endonce
