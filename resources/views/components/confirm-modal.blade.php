{{--
    One shared confirmation dialog for the whole app.

    Any form carrying data-confirm opens it instead of submitting immediately:

        <form method="POST" action="…" data-confirm="Delete Aarav Mehta?"
              data-confirm-title="Delete customer" data-confirm-action="Delete">
--}}
<div class="nv-modal-backdrop" data-confirm-modal role="dialog" aria-modal="true" aria-labelledby="nv-confirm-title">
    <div class="nv-modal">
        <span class="nv-modal-icon"><x-icon name="alert" /></span>

        <h3 id="nv-confirm-title" data-confirm-title>Are you sure?</h3>
        <p data-confirm-text>This action cannot be undone.</p>

        <div class="nv-modal-actions">
            <button type="button" class="nv-btn nv-btn-outline" data-confirm-cancel>Cancel</button>
            <button type="button" class="nv-btn nv-btn-danger" data-confirm-accept>Delete</button>
        </div>
    </div>
</div>
