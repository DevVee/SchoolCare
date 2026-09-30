{{--
    Shared "Dispose of batch" dialog. Any element with class .btn-dispose and
    data-action / data-label / data-qty / data-expired opens it (menu items included).
    It asks for a reason, so it stays a page modal instead of the global confirm dialog.
--}}
@can('dispose-medicines')
<x-ui.modal id="disposeModal" title="Dispose of batch" action="#" method="POST" sheet>
    <p class="mb-2">Dispose of <strong id="disposeQty"></strong> from <strong id="disposeLabel"></strong>?</p>
    <p class="text-muted fs-sm">The whole batch is removed from stock and recorded in the disposal history. This cannot be undone.</p>
    <x-ui.alert variant="warning" id="disposeNotExpired" class="d-none mb-3">
        This batch has not expired yet. Only dispose of it if it is damaged, recalled or otherwise unusable.
    </x-ui.alert>
    <x-ui.textarea name="reason" id="disposeReason" label="Reason" rows="2" required minlength="3" maxlength="500"
        placeholder="For example: expired, returned to supplier, damaged packaging" />
    <x-slot:footer>
        <x-ui.button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="submit" variant="danger" icon="trash3">Dispose</x-ui.button>
    </x-slot:footer>
</x-ui.modal>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('disposeModal');
    if (!modalEl) return;
    var form = modalEl.querySelector('form');
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn-dispose');
        if (!btn) return;
        e.preventDefault();
        form.action = btn.dataset.action;
        document.getElementById('disposeLabel').textContent = btn.dataset.label || '';
        document.getElementById('disposeQty').textContent = btn.dataset.qty || '';
        document.getElementById('disposeNotExpired').classList.toggle('d-none', btn.dataset.expired === '1');
        var reason = document.getElementById('disposeReason');
        reason.value = btn.dataset.expired === '1' ? 'Expired' : '';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
});
</script>
@endpush
@endcan
