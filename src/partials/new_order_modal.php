<!-- New Order modal: fresh/empty render of new_order_form.php, included on
     every customer page (layout_customer.php) and opened from
     dashboard.php's "+ New Order" button. The form submits via fetch to
     /customer/new_order.php (a POST-only JSON endpoint -- there is no
     standalone page); validation failures render inline inside this modal
     without ever leaving it. -->
<div class="modal-overlay modal-overlay--order" id="new-order-modal" hidden>
  <div class="modal modal--order" role="dialog" aria-modal="true" aria-labelledby="new-order-modal-title">
    <div class="modal__header">
      <h2 class="modal__title" id="new-order-modal-title">New order</h2>
      <button type="button" class="modal__close" data-modal-close aria-label="Close">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <div class="modal__body">
      <?php
      // Isolated in a closure so the pristine $old/$fieldErrors/$todayDate
      // this render needs never leak into (or collide with) the including
      // page's variable scope. The modal always renders the form empty --
      // submission is AJAX and validation errors are injected client-side,
      // so no server re-render ever repopulates these. $nuclides/$products/
      // $locations/$productUsers/$labId are read-only here so they're
      // passed in from the real outer scope.
      (function (array $nuclides, array $products, array $locations, array $productUsers, int $labId) {
          $old = [
              'nuclide_id'      => '',
              'product_id'      => '',
              'activity_mci'    => '',
              'requested_date'  => '',
              'requested_time'  => '',
              'notes'           => '',
              'location_id'     => '',
              'product_user_id' => '',
          ];
          $fieldErrors = [];
          $todayDate = date('Y-m-d');
          // The submit button renders in this modal's pinned footer below
          // instead of inside the form -- see the form="order-form"
          // association on Place order.
          include __DIR__ . '/new_order_form.php';
      })($nuclides, $products, $locations, $productUsers, $labId);
      ?>
    </div>
    <div class="modal__footer modal__footer--split">
      <button type="button" class="btn btn--ghost" data-modal-close>Cancel</button>
      <button type="submit" class="btn btn--primary" id="order-submit" form="order-form" disabled>Place order</button>
    </div>
  </div>
</div>
