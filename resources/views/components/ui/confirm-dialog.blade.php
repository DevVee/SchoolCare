{{--
    x-ui.confirm-dialog: the ONE global confirm modal (id="confirmModal").
    Render once per page, in the layout, before @stack('scripts'):
        <x-ui.confirm-dialog />
    resources/js/ui/confirm.js drives it (and injects identical markup if a page forgot it).

    Trigger it declaratively, no page JS needed:
        <form method="POST" action="..." data-confirm="This removes the record permanently."
              data-confirm-title="Delete patient?" data-confirm-variant="danger" data-confirm-button="Delete">
    or from JS:  if (await confirmDialog({ title: 'Send 24 reminders?', confirmText: 'Send' })) { ... }
    Keep this markup in sync with TEMPLATE in resources/js/ui/confirm.js.
--}}
<div class="modal fade modal-confirm" id="confirmModal" tabindex="-1" aria-hidden="true"
     aria-labelledby="confirmModalTitle" aria-describedby="confirmModalBody" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="confirm-icon tone-brand" data-confirm-el="icon"><i class="bi bi-question-circle-fill c-icon" aria-hidden="true"></i></div>
            <h2 class="confirm-title" id="confirmModalTitle" data-confirm-el="title">Are you sure?</h2>
            <p class="confirm-body" id="confirmModalBody" data-confirm-el="body"></p>
            <div class="mb-3 d-none" data-confirm-el="input-wrap">
                <label class="form-label" for="confirmModalInput" data-confirm-el="input-label">Reason</label>
                <textarea class="form-control" id="confirmModalInput" rows="3" maxlength="1000" data-confirm-el="input"></textarea>
                <div class="invalid-feedback">This field is required.</div>
            </div>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-confirm-el="cancel">Cancel</button>
                <button type="button" class="btn btn-primary" data-confirm-el="ok">Confirm</button>
            </div>
        </div>
    </div>
</div>
