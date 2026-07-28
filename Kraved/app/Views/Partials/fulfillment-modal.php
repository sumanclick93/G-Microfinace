<?php use App\Core\Helpers; $f = $fulfillment ?? []; ?>
<div class="modal fade" id="fulfillmentModal" tabindex="-1" aria-labelledby="fulfillmentTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content fulfillment-modal">
      <div class="modal-header border-0">
        <h2 class="modal-title h4" id="fulfillmentTitle">How would you like your cookies?</h2>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="toggle-pills mb-3" role="group">
          <button type="button" class="pill <?= ($f['type'] ?? 'collection') === 'collection' ? 'active' : '' ?>" data-ff-type="collection">Collection</button>
          <button type="button" class="pill <?= ($f['type'] ?? '') === 'delivery' ? 'active' : '' ?>" data-ff-type="delivery">Delivery</button>
        </div>
        <div id="ff-delivery-fields" class="<?= ($f['type'] ?? '') === 'delivery' ? '' : 'd-none' ?>">
          <label class="form-label">Postcode</label>
          <input type="text" id="ff-postcode" class="form-control mb-2" placeholder="e.g. E1 6AN" value="<?= Helpers::e($f['postcode'] ?? '') ?>">
          <div id="ff-zone-msg" class="small mb-3"></div>
        </div>
        <div id="ff-collection-note" class="small opacity-75 mb-3 <?= ($f['type'] ?? 'collection') === 'collection' ? '' : 'd-none' ?>">
          Collect from 12 Baker Street, London W1U 3BW
        </div>
        <label class="form-label">When</label>
        <select id="ff-slot" class="form-select mb-3">
          <option value="ASAP" <?= ($f['time_slot'] ?? '') === 'ASAP' ? 'selected' : '' ?>>ASAP (~35 mins)</option>
          <option value="Today 17:00–17:30">Today 17:00–17:30</option>
          <option value="Today 18:00–18:30">Today 18:00–18:30</option>
          <option value="Today 19:00–19:30">Today 19:00–19:30</option>
          <option value="Today 20:00–20:30">Today 20:00–20:30</option>
        </select>
        <button type="button" class="btn btn-accent w-100" id="ff-save">Confirm</button>
      </div>
    </div>
  </div>
</div>
