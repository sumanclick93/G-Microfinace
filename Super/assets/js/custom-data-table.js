$(document).ready(function () {
    // 1. Inject CSS to center all table headers, cells, and inner elements (images, text blocks, options)
    $('<style>')
        .prop('type', 'text/css')
        .html(
            'table.dataTable th, table.dataTable td { text-align: center !important; vertical-align: middle !important; } ' +
            'table.dataTable td .user-name { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; text-align: center !important; } ' +
            'table.dataTable td .table-image { margin: 0 auto !important; display: flex !important; justify-content: center !important; align-items: center !important; } ' +
            'table.dataTable td ul { display: flex !important; justify-content: center !important; align-items: center !important; gap: 10px; padding: 0 !important; margin: 0 !important; list-style: none !important; }'
        )
        .appendTo('head');

    // 2. Global fallback for missing/broken images using a clean vector person icon SVG
    var defaultAvatarSvg = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23cccccc"><circle cx="12" cy="8" r="4"/><path d="M12 14c-6.1 0-8 4-8 4v2h16v-2s-1.9-4-8-4z"/></svg>';

    // Catch image loading errors during capture phase (handles dynamically added rows too)
    document.addEventListener('error', function (event) {
        var element = event.target;
        if (element.tagName && element.tagName.toLowerCase() === 'img') {
            element.src = defaultAvatarSvg;
        }
    }, true);

    // Initial check for images that failed to load before script execution
    $('img').each(function () {
        if (this.complete && (typeof this.naturalWidth === 'undefined' || this.naturalWidth === 0)) {
            this.src = defaultAvatarSvg;
        }
    });

    // We will initialize DataTables on every table with the 'table' class
    $('table.table').each(function () {
        var $table = $(this);

        // Skip if already initialized
        if ($.fn.DataTable.isDataTable(this)) {
            return;
        }

        // Only run on tables that are intended to be DataTables (have specific IDs or class)
        var tableId = $table.attr('id') || '';
        var isDataTable = tableId === 'table_id' || tableId === 'main_summary_table' || $table.hasClass('all-package');
        if (!isDataTable) {
            return;
        }

        // Skip custom/dynamic tables that are handled manually in their respective files
        if (tableId === 'collection_table' || tableId.indexOf('inner_table_') === 0) {
            return;
        }

        // 1. Identify first column header
        var firstTh = $table.find('thead th').first();
        var firstThText = firstTh.text().trim().toLowerCase();

        // 2. Prepend S.No. header if not already present
        var hasSerial = ['s.no.', 's.no', 'serial', 'sl.no', 'sl.no.', 'rank', '#', 'no.'].some(function (term) {
            return firstThText.includes(term);
        });

        if (!hasSerial && $table.find('thead tr').length > 0) {
            // Prepend S.No. column to the head row
            $table.find('thead tr').prepend('<th class="sno-col" style="width: 60px; font-weight: 600;">S.No.</th>');
            
            // Prepend an empty cell to all body rows
            $table.find('tbody tr').each(function () {
                var $row = $(this);
                if ($row.find('td').length > 1) {
                    $row.prepend('<td class="sno-cell" style="font-weight: 500;"></td>');
                } else if ($row.find('td').length === 1 && $row.find('td').attr('colspan')) {
                    // Update colspan for no-data / empty rows
                    var colSpan = parseInt($row.find('td').attr('colspan'));
                    $row.find('td').attr('colspan', colSpan + 1);
                }
            });
        }

        // 3. Determine default alphabetical column index
        var sortColIndex = hasSerial ? 0 : 1; // Default to first data column
        $table.find('thead th').each(function (index) {
            var text = $(this).text().trim().toLowerCase();
            // Look for customer name, agent name, name, etc.
            if (text.includes('name') || text.includes('customer') || text.includes('agent')) {
                sortColIndex = index;
                return false; // Break loop
            }
        });

        // 4. Initialize the DataTable
        var t = $table.DataTable({
            paging: true,
            ordering: true,
            info: true,
            responsive: true,
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            columnDefs: !hasSerial ? [
                {
                    searchable: false,
                    orderable: false,
                    targets: 0
                }
            ] : [],
            order: [[sortColIndex, 'asc']], // Default sorting alphabetically
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search records..."
            }
        });

        // 5. If S.No. was added, draw row numbers dynamically on sort/filter events so they stay sequential 1, 2, 3...
        if (!hasSerial) {
            t.on('order.dt search.dt', function () {
                let i = 1;
                t.cells(null, 0, { search: 'applied', order: 'applied' }).every(function (cell) {
                    this.data(i++);
                });
            }).draw();
        }
    });

    // 6. Customer Delete Button Handler
    $(document).on('click', '.customer-delete-btn', function (e) {
        e.preventDefault();
        var customerId = $(this).data('id');
        var customerName = $(this).data('name');
        var activeLoans = parseInt($(this).data('active-loans') || 0);
        var activeRds = parseInt($(this).data('active-rds') || 0);
        var role = $(this).data('role') || 'agent'; // 'agent' or 'admin'

        if (activeLoans > 0 || activeRds > 0) {
            var msg = 'Customer <strong>' + customerName + '</strong> cannot be deleted because they have ' +
                      (activeLoans > 0 ? '<strong>' + activeLoans + ' active Loan(s)</strong>' : '') +
                      (activeLoans > 0 && activeRds > 0 ? ' and ' : '') +
                      (activeRds > 0 ? '<strong>' + activeRds + ' active RD(s)</strong>' : '') +
                      '.<br><br>All active accounts must be fully Paid, Closed, or Matured first.';
            
            showDeleteAlert(msg);
        } else {
            showDeleteConfirmation(customerId, customerName, role);
        }
    });

    function showDeleteAlert(message) {
        var modalHtml = 
            '<div class="modal fade" id="deleteAlertModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header bg-danger text-white">' +
                            '<h5 class="modal-title"><i class="ri-error-warning-line"></i> Cannot Delete Customer</h5>' +
                            '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<div class="modal-body text-center p-4">' +
                            '<p style="font-size: 15px; font-weight: 500; color: #333;">' + message + '</p>' +
                        '</div>' +
                        '<div class="modal-footer justify-content-center">' +
                            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        $('#deleteAlertModal').remove();
        $('body').append(modalHtml);
        
        var myModal = new bootstrap.Modal(document.getElementById('deleteAlertModal'));
        myModal.show();
    }

    function showDeleteConfirmation(customerId, customerName, role) {
        var title = role === 'admin' ? 'Permanently Delete Customer?' : 'Request Customer Deletion?';
        var actionUrl = role === 'admin' ? 'all-customers-loans.php' : 'all-customer.php';
        var hiddenFields = role === 'admin' 
            ? '<input type="hidden" name="action" value="delete_customer"><input type="hidden" name="customer_id" value="' + customerId + '">' 
            : '<input type="hidden" name="delete_id" value="' + customerId + '">';
        var descText = role === 'admin'
            ? 'Are you sure you want to <strong>permanently delete</strong> ' + customerName + '? This will erase all their historical records from the system.'
            : 'Are you sure you want to request deletion for ' + customerName + '? This will move their status to <strong>Pending Deletion</strong>.';

        var modalHtml = 
            '<div class="modal fade theme-modal" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header d-block text-center">' +
                            '<h5 class="modal-title w-100"><i class="ri-alert-line"></i> ' + title + '</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<form method="POST" action="' + actionUrl + '">' +
                            '<div class="modal-body text-center p-4">' +
                                hiddenFields +
                                '<p style="font-size: 15px; font-weight: 500;">' + descText + '</p>' +
                            '</div>' +
                            '<div class="modal-footer justify-content-center">' +
                                '<button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                                '<button type="submit" class="btn btn-animation btn-md fw-bold btn-danger">Yes, Delete</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</div>' +
            '</div>';
        
        $('#deleteConfirmModal').remove();
        $('body').append(modalHtml);
        
        var myModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
        myModal.show();
    }

    // 7. Close Loan Button Handler
    $(document).on('click', '.close-loan-trigger-btn', function (e) {
        e.preventDefault();
        var customerId = $(this).data('customer-id');
        var customerName = $(this).data('customer-name');
        var loans = $(this).data('loans') || [];
        var role = window.location.pathname.includes('Super') ? 'admin' : 'agent';
        var actionUrl = role === 'admin' ? 'all-customers-loans.php' : 'all-customer.php';

        if (loans.length === 0) {
            alert('No active loans found for this customer.');
            return;
        }

        var selectOptions = '';
        loans.forEach(function (loan, index) {
            selectOptions += '<option value="' + loan.id + '" data-remaining="' + loan.remaining + '">Loan #' + loan.id + ' (Principal: ₹' + parseFloat(loan.amount).toLocaleString('en-IN') + ')</option>';
        });

        var firstRemaining = parseFloat(loans[0].remaining).toFixed(2);

        var modalHtml = 
            '<div class="modal fade theme-modal" id="closeLoanModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header d-block text-center">' +
                            '<h5 class="modal-title w-100"><i class="ri-close-circle-line" style="color: #dc3545;"></i> Close Loan Account</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<form method="POST" action="' + actionUrl + '">' +
                            '<input type="hidden" name="action" value="close_loan">' +
                            '<div class="modal-body p-4 text-start">' +
                                '<p class="text-muted mb-3">Close active loan for <strong>' + customerName + '</strong>.</p>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Select Loan Account</label>' +
                                    '<select class="form-select" id="close_loan_id_select" name="loan_id" required>' +
                                        selectOptions +
                                    '</select>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Remaining Balance Due</label>' +
                                    '<div class="input-group">' +
                                        '<span class="input-group-text">₹</span>' +
                                        '<input type="text" class="form-control bg-light" id="close_loan_remaining_display" value="' + firstRemaining + '" readonly>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Settlement Amount Paid (₹)</label>' +
                                    '<input type="number" step="0.01" class="form-control" name="amount_paid" id="close_loan_amount_paid" value="' + firstRemaining + '" required min="0">' +
                                    '<small class="text-muted">Enter the amount collected to settle and close this loan. Enter 0 for waivers.</small>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Closure Notes</label>' +
                                    '<textarea class="form-control" name="notes" rows="2" placeholder="e.g. Settle remaining balance, full waiver, paid in cash, etc." required></textarea>' +
                                '</div>' +
                            '</div>' +
                            '<div class="modal-footer justify-content-center">' +
                                '<button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                                '<button type="submit" class="btn btn-animation btn-md fw-bold btn-danger">Confirm Close Account</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</div>' +
            '</div>';

        $('#closeLoanModal').remove();
        $('body').append(modalHtml);

        // Update displayed remaining balance on select change
        $(document).off('change', '#close_loan_id_select').on('change', '#close_loan_id_select', function () {
            var selected = $(this).find('option:selected');
            var remaining = parseFloat(selected.data('remaining')).toFixed(2);
            $('#close_loan_remaining_display').val(remaining);
            $('#close_loan_amount_paid').val(remaining);
        });

        var myModal = new bootstrap.Modal(document.getElementById('closeLoanModal'));
        myModal.show();
    });

    // 8. Close RD Button Handler
    $(document).on('click', '.close-rd-trigger-btn', function (e) {
        e.preventDefault();
        var customerId = $(this).data('customer-id');
        var customerName = $(this).data('customer-name');
        var rds = $(this).data('rds') || [];
        var role = window.location.pathname.includes('Super') ? 'admin' : 'agent';
        var actionUrl = role === 'admin' ? 'all-customers-loans.php' : 'all-customer.php';

        if (rds.length === 0) {
            alert('No active RD accounts found for this customer.');
            return;
        }

        var selectOptions = '';
        rds.forEach(function (rd, index) {
            selectOptions += '<option value="' + rd.id + '" data-remaining="' + rd.remaining + '">RD #' + rd.id + ' (Installment: ₹' + parseFloat(rd.amount).toLocaleString('en-IN') + ')</option>';
        });

        var firstRemaining = parseFloat(rds[0].remaining).toFixed(2);

        var modalHtml = 
            '<div class="modal fade theme-modal" id="closeRdModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header d-block text-center">' +
                            '<h5 class="modal-title w-100"><i class="ri-close-circle-line" style="color: #dc3545;"></i> Close RD Account</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<form method="POST" action="' + actionUrl + '">' +
                            '<input type="hidden" name="action" value="close_rd">' +
                            '<div class="modal-body p-4 text-start">' +
                                '<p class="text-muted mb-3">Close active RD account for <strong>' + customerName + '</strong>.</p>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Select RD Account</label>' +
                                    '<select class="form-select" id="close_rd_id_select" name="rd_id" required>' +
                                        selectOptions +
                                    '</select>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Remaining Balance to Maturity</label>' +
                                    '<div class="input-group">' +
                                        '<span class="input-group-text">₹</span>' +
                                        '<input type="text" class="form-control bg-light" id="close_rd_remaining_display" value="' + firstRemaining + '" readonly>' +
                                    '</div>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Settlement Amount Paid (₹)</label>' +
                                    '<input type="number" step="0.01" class="form-control" name="amount_paid" id="close_rd_amount_paid" value="' + firstRemaining + '" required min="0">' +
                                    '<small class="text-muted">Enter the final collection/maturity amount to settle this account.</small>' +
                                '</div>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Closure Notes</label>' +
                                    '<textarea class="form-control" name="notes" rows="2" placeholder="e.g. Matured, closed early, paid remaining balance, etc." required></textarea>' +
                                '</div>' +
                            '</div>' +
                            '<div class="modal-footer justify-content-center">' +
                                '<button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                                '<button type="submit" class="btn btn-animation btn-md fw-bold btn-danger">Confirm Close Account</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</div>' +
            '</div>';

        $('#closeRdModal').remove();
        $('body').append(modalHtml);

        // Update displayed remaining balance on select change
        $(document).off('change', '#close_rd_id_select').on('change', '#close_rd_id_select', function () {
            var selected = $(this).find('option:selected');
            var remaining = parseFloat(selected.data('remaining')).toFixed(2);
            $('#close_rd_remaining_display').val(remaining);
            $('#close_rd_amount_paid').val(remaining);
        });

        var myModal = new bootstrap.Modal(document.getElementById('closeRdModal'));
        myModal.show();
    });
});