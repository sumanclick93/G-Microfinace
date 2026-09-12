$(document).ready(function () {
    // 1. Inject CSS to center all table headers, cells, and inner elements + Mobile Responsive Card View Mode
    $('<style>')
        .prop('type', 'text/css')
        .html(
            'table.dataTable th, table.dataTable td { text-align: center !important; vertical-align: middle !important; } ' +
            'table.dataTable td .user-name { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; text-align: center !important; } ' +
            'table.dataTable td .table-image { margin: 0 auto !important; display: flex !important; justify-content: center !important; align-items: center !important; } ' +
            'table.dataTable td ul { display: flex !important; justify-content: center !important; align-items: center !important; gap: 10px; padding: 0 !important; margin: 0 !important; list-style: none !important; } ' +
            /* Mobile Responsive Card View Mode (<= 768px) */
            '.mobile-view-toggle-container { display: none !important; } ' +
            '@media (max-width: 768px) { ' +
                '.dataTables_wrapper { padding: 0 4px !important; } ' +
                '.dataTables_wrapper .dataTables_filter { text-align: left !important; margin-bottom: 12px !important; } ' +
                '.dataTables_wrapper .dataTables_filter input { width: 100% !important; margin-left: 0 !important; margin-top: 6px !important; padding: 8px 12px !important; border-radius: 6px !important; border: 1px solid #ced4da !important; box-sizing: border-box !important; } ' +
                '.dataTables_wrapper .dataTables_length { margin-bottom: 10px !important; float: none !important; text-align: left !important; } ' +
                '.mobile-view-toggle-container { display: flex !important; justify-content: flex-end; margin-bottom: 12px; } ' +
                '.mobile-view-toggle-btn { background: #0d6efd; color: #fff !important; border: none; padding: 7px 14px; border-radius: 20px; font-size: 13px; font-weight: 600; box-shadow: 0 2px 4px rgba(13, 110, 253, 0.25); display: inline-flex; align-items: center; gap: 6px; cursor: pointer; transition: all 0.2s ease; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table thead { display: none !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table { display: block !important; width: 100% !important; border: none !important; margin-top: 0 !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody { display: block !important; width: 100% !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr { display: block !important; width: 100% !important; margin-bottom: 16px !important; background: #ffffff !important; border: 1px solid #e2e8f0 !important; border-radius: 12px !important; box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06) !important; padding: 14px !important; position: relative !important; box-sizing: border-box !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td { display: flex !important; justify-content: space-between !important; align-items: flex-start !important; text-align: right !important; padding: 12px 4px !important; border-bottom: 1px dashed #e9ecef !important; min-height: 42px !important; font-size: 13.5px !important; word-break: break-word !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td:last-child { border-bottom: none !important; padding-bottom: 4px !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td::before { content: attr(data-label); font-weight: 700 !important; color: #475569 !important; text-align: left !important; margin-right: 12px !important; flex-shrink: 0 !important; max-width: 42% !important; font-size: 12px !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; padding-top: 2px !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content { display: flex !important; flex-direction: column !important; align-items: flex-end !important; justify-content: center !important; text-align: right !important; max-width: 58% !important; width: 58% !important; gap: 4px !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content > * { max-width: 100% !important; word-break: break-word !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td.sno-cell { background: #f8fafc !important; border-radius: 6px !important; padding: 8px 10px !important; font-weight: 700 !important; color: #0d6efd !important; border-bottom: 1px solid #e2e8f0 !important; margin-bottom: 8px !important; align-items: center !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td.sno-cell .cell-content { flex-direction: row !important; justify-content: flex-end !important; width: auto !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .user-name { align-items: flex-end !important; text-align: right !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .table-image { margin: 0 !important; justify-content: flex-end !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content ul, .dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .d-flex { display: flex !important; flex-direction: row !important; flex-wrap: wrap !important; justify-content: flex-end !important; align-items: center !important; gap: 6px !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .btn, .dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content button { margin: 2px 0 !important; white-space: normal !important; text-align: right !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .input-group { max-width: 220px !important; width: auto !important; display: flex !important; flex-direction: row !important; flex-wrap: nowrap !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .input-group .form-select { max-width: 85px !important; font-size: 11.5px !important; padding: 4px 6px !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td .cell-content .input-group .amt-input { min-width: 65px !important; max-width: 85px !important; padding: 4px 6px !important; font-size: 12px !important; } ' +
                '.dataTables_wrapper.mobile-card-mode table.table tbody tr td[style*="display:none"], .dataTables_wrapper.mobile-card-mode table.table tbody tr td[style*="display: none"], .dataTables_wrapper.mobile-card-mode table.table tbody tr td.d-none { display: none !important; } ' +
                '.dataTables_wrapper:not(.mobile-card-mode) { overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; } ' +
            '}'
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

    // Helper: Initialize Mobile Responsive Card View on any Table
    function initMobileCardView($table) {
        if ($table.data('mobile-view-init')) return;
        $table.data('mobile-view-init', true);

        var $wrapper = $table.closest('.dataTables_wrapper');
        if ($wrapper.length === 0) return;

        // Default to Card Mode on mobile screens
        if (window.innerWidth <= 768) {
            $wrapper.addClass('mobile-card-mode');
        }

        // Add Toggle Switch Button to the Wrapper (Visible on Mobile only via CSS)
        if ($wrapper.find('.mobile-view-toggle-container').length === 0) {
            var $toggleBtn = $('<button type="button" class="mobile-view-toggle-btn"><i class="ri-layout-grid-line"></i> <span>Card View (Active)</span></button>');
            var $toggleContainer = $('<div class="mobile-view-toggle-container"></div>').append($toggleBtn);
            
            $wrapper.prepend($toggleContainer);

            $toggleBtn.on('click', function () {
                $wrapper.toggleClass('mobile-card-mode');
                var isCard = $wrapper.hasClass('mobile-card-mode');
                if (isCard) {
                    $(this).html('<i class="ri-layout-grid-line"></i> <span>Card View (Active)</span>');
                    $(this).css('background', '#0d6efd');
                } else {
                    $(this).html('<i class="ri-table-line"></i> <span>Switch to Card View</span>');
                    $(this).css('background', '#475569');
                }
            });
        }

        // Function to extract header names and set data-label on every table cell
        function updateCellLabels() {
            var headers = [];
            $table.find('thead tr:first th').each(function () {
                var text = $(this).text().trim();
                if (!text || text === '') {
                    text = $(this).attr('data-label-fallback') || 'Detail';
                }
                headers.push(text);
            });

            $table.find('tbody tr').each(function () {
                $(this).find('td').each(function (index) {
                    if ($(this).css('display') === 'none' || $(this).hasClass('d-none')) {
                        return;
                    }
                    var headerText = headers[index] || 'Detail';
                    if (!$(this).attr('data-label')) {
                        $(this).attr('data-label', headerText);
                    }

                    // Ensure inner content is wrapped in .cell-content so multiple divs/lines stack vertically
                    if ($(this).find('> .cell-content').length === 0) {
                        $(this).wrapInner('<div class="cell-content"></div>');
                    }
                });
            });
        }

        if ($.fn.DataTable.isDataTable($table[0])) {
            var dt = $table.DataTable();
            dt.on('draw.dt init.dt', function () {
                updateCellLabels();
            }).draw();
        } else {
            updateCellLabels();
        }
    }

    // We will initialize DataTables on every table with the 'table' class
    $('table.table').each(function () {
        var $table = $(this);

        // Skip if already initialized
        if ($.fn.DataTable.isDataTable(this)) {
            initMobileCardView($table);
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
            pageLength: 100,
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
                    this.data(i++, false);
                });
            });
        }

        initMobileCardView($table);
    });

    // Run initMobileCardView on all tables after page-specific scripts finish (e.g., #collection_table)
    setTimeout(function () {
        $('table.dataTable, table.table').each(function () {
            var tableId = $(this).attr('id') || '';
            if (tableId.indexOf('inner_table_') === 0) return;
            initMobileCardView($(this));
        });
    }, 400);

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
        var isSuper = window.location.pathname.toLowerCase().indexOf('super') !== -1 || window.location.pathname.indexOf('all-customers-loans.php') !== -1;
        var actionUrl = isSuper ? 'all-customers-loans.php' : 'all-customer.php';

        if (typeof loans === 'string') {
            try { loans = JSON.parse(loans); } catch (err) { loans = []; }
        }
        if (!Array.isArray(loans) || loans.length === 0) {
            alert('No active loans found for this customer.');
            return;
        }

        var selectOptions = '';
        loans.forEach(function (loan, index) {
            var rem = (loan && loan.remaining !== undefined && loan.remaining !== null) ? parseFloat(loan.remaining) : 0;
            if (isNaN(rem)) rem = 0;
            var amt = (loan && loan.amount !== undefined && loan.amount !== null) ? parseFloat(loan.amount) : 0;
            if (isNaN(amt)) amt = 0;
            selectOptions += '<option value="' + loan.id + '" data-remaining="' + rem.toFixed(2) + '">Loan #' + loan.id + ' (Principal: ₹' + amt.toLocaleString('en-IN') + ')</option>';
        });

        var firstRem = (loans[0] && loans[0].remaining !== undefined && loans[0].remaining !== null) ? parseFloat(loans[0].remaining) : 0;
        if (isNaN(firstRem)) firstRem = 0;
        var firstRemaining = firstRem.toFixed(2);

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
            var rem = parseFloat(selected.data('remaining'));
            if (isNaN(rem)) rem = 0;
            var remaining = rem.toFixed(2);
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
        var isSuper = window.location.pathname.toLowerCase().indexOf('super') !== -1 || window.location.pathname.indexOf('all-customers-loans.php') !== -1;
        var actionUrl = isSuper ? 'all-customers-loans.php' : 'all-customer.php';

        if (typeof rds === 'string') {
            try { rds = JSON.parse(rds); } catch (err) { rds = []; }
        }
        if (!Array.isArray(rds) || rds.length === 0) {
            alert('No active RD accounts found for this customer.');
            return;
        }

        var selectOptions = '';
        rds.forEach(function (rd, index) {
            var rem = (rd && rd.remaining !== undefined && rd.remaining !== null) ? parseFloat(rd.remaining) : 0;
            if (isNaN(rem)) rem = 0;
            var amt = (rd && rd.amount !== undefined && rd.amount !== null) ? parseFloat(rd.amount) : 0;
            if (isNaN(amt)) amt = 0;
            selectOptions += '<option value="' + rd.id + '" data-remaining="' + rem.toFixed(2) + '">RD #' + rd.id + ' (Installment: ₹' + amt.toLocaleString('en-IN') + ')</option>';
        });

        var firstRem = (rds[0] && rds[0].remaining !== undefined && rds[0].remaining !== null) ? parseFloat(rds[0].remaining) : 0;
        if (isNaN(firstRem)) firstRem = 0;
        var firstRemaining = firstRem.toFixed(2);

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
            var rem = parseFloat(selected.data('remaining'));
            if (isNaN(rem)) rem = 0;
            var remaining = rem.toFixed(2);
            $('#close_rd_remaining_display').val(remaining);
            $('#close_rd_amount_paid').val(remaining);
        });

        var myModal = new bootstrap.Modal(document.getElementById('closeRdModal'));
        myModal.show();
    });

    // 9. Delete Completed/Closed Loan Button Handler
    $(document).on('click', '.delete-loan-trigger-btn', function (e) {
        e.preventDefault();
        var customerId = $(this).data('customer-id');
        var customerName = $(this).data('customer-name');
        var loans = $(this).data('loans') || [];
        var isSuper = window.location.pathname.toLowerCase().indexOf('super') !== -1 || window.location.pathname.indexOf('all-customers-loans.php') !== -1;
        var actionUrl = isSuper ? 'all-customers-loans.php' : 'all-customer.php';

        if (typeof loans === 'string') {
            try { loans = JSON.parse(loans); } catch (err) { loans = []; }
        }
        if (!Array.isArray(loans) || loans.length === 0) {
            alert('No completed/closed loans found for this customer.');
            return;
        }

        var selectOptions = '';
        loans.forEach(function (loan) {
            var amt = (loan && loan.amount !== undefined && loan.amount !== null) ? parseFloat(loan.amount) : 0;
            if (isNaN(amt)) amt = 0;
            selectOptions += '<option value="' + loan.id + '">Loan #' + loan.id + ' (' + loan.status + ' - Principal: ₹' + amt.toLocaleString('en-IN') + ')</option>';
        });

        var modalHtml = 
            '<div class="modal fade theme-modal" id="deleteLoanAccountModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header d-block text-center">' +
                            '<h5 class="modal-title w-100"><i class="ri-delete-bin-line" style="color: #dc3545;"></i> Delete Completed/Closed Loan</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<form method="POST" action="' + actionUrl + '">' +
                            '<input type="hidden" name="action" value="delete_loan">' +
                            '<div class="modal-body p-4 text-start">' +
                                '<p class="text-danger mb-2"><strong>Warning:</strong> Permanent deletion of record.</p>' +
                                '<p class="text-muted mb-3">You are deleting a completed/closed loan for <strong>' + customerName + '</strong>. All associated payments and wallet history will also be removed.</p>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Select Loan Account to Delete</label>' +
                                    '<select class="form-select" name="loan_id" required>' +
                                        selectOptions +
                                    '</select>' +
                                '</div>' +
                            '</div>' +
                            '<div class="modal-footer justify-content-center">' +
                                '<button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                                '<button type="submit" class="btn btn-animation btn-md fw-bold btn-danger">Confirm Deletion</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</div>' +
            '</div>';

        $('#deleteLoanAccountModal').remove();
        $('body').append(modalHtml);

        var myModal = new bootstrap.Modal(document.getElementById('deleteLoanAccountModal'));
        myModal.show();
    });

    // 10. Delete Completed/Closed RD Button Handler
    $(document).on('click', '.delete-rd-trigger-btn', function (e) {
        e.preventDefault();
        var customerId = $(this).data('customer-id');
        var customerName = $(this).data('customer-name');
        var rds = $(this).data('rds') || [];
        var isSuper = window.location.pathname.toLowerCase().indexOf('super') !== -1 || window.location.pathname.indexOf('all-customers-loans.php') !== -1;
        var actionUrl = isSuper ? 'all-customers-loans.php' : 'all-customer.php';

        if (typeof rds === 'string') {
            try { rds = JSON.parse(rds); } catch (err) { rds = []; }
        }
        if (!Array.isArray(rds) || rds.length === 0) {
            alert('No completed/closed RD accounts found for this customer.');
            return;
        }

        var selectOptions = '';
        rds.forEach(function (rd) {
            var amt = (rd && rd.amount !== undefined && rd.amount !== null) ? parseFloat(rd.amount) : 0;
            if (isNaN(amt)) amt = 0;
            selectOptions += '<option value="' + rd.id + '">RD #' + rd.id + ' (' + rd.status + ' - Deposit: ₹' + amt.toLocaleString('en-IN') + ')</option>';
        });

        var modalHtml = 
            '<div class="modal fade theme-modal" id="deleteRDAccountModal" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header d-block text-center">' +
                            '<h5 class="modal-title w-100"><i class="ri-delete-bin-line" style="color: #dc3545;"></i> Delete Completed/Closed RD</h5>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                        '</div>' +
                        '<form method="POST" action="' + actionUrl + '">' +
                            '<input type="hidden" name="action" value="delete_rd">' +
                            '<div class="modal-body p-4 text-start">' +
                                '<p class="text-danger mb-2"><strong>Warning:</strong> Permanent deletion of record.</p>' +
                                '<p class="text-muted mb-3">You are deleting a completed/closed RD account for <strong>' + customerName + '</strong>. All associated deposits and wallet history will also be removed.</p>' +
                                '<div class="mb-3">' +
                                    '<label class="form-label" style="font-weight: 500;">Select RD Account to Delete</label>' +
                                    '<select class="form-select" name="rd_id" required>' +
                                        selectOptions +
                                    '</select>' +
                                '</div>' +
                            '</div>' +
                            '<div class="modal-footer justify-content-center">' +
                                '<button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                                '<button type="submit" class="btn btn-animation btn-md fw-bold btn-danger">Confirm Deletion</button>' +
                            '</div>' +
                        '</form>' +
                    '</div>' +
                '</div>' +
            '</div>';

        $('#deleteRDAccountModal').remove();
        $('body').append(modalHtml);

        var myModal = new bootstrap.Modal(document.getElementById('deleteRDAccountModal'));
        myModal.show();
    });
});