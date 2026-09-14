<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $loan_id = (int)$_GET['id'];

        $sql_loan = "SELECT
                        l.id, l.loan_amount, l.total_repayable_amount, l.monthly_installment,
                        l.interest_rate, l.tenure, l.repayment_cycle, l.status,
                        l.application_date, l.approval_date, l.loan_start_date, l.notes as admin_notes,
                        l.loan_type, l.interest_calculation_type, l.gold_weight_grams,
                        l.gold_photo_path, l.gold_rate_per_gram, l.processing_fee
                    FROM loans l
                    WHERE l.id = ? AND l.customer_id = ?";

        $stmt_loan = $conn->prepare($sql_loan);
        $stmt_loan->bind_param("ii", $loan_id, $customer_id);

        if ($stmt_loan->execute()) {
            $result_loan = $stmt_loan->get_result();

            if ($result_loan->num_rows === 1) {
                $loan_details = $result_loan->fetch_assoc();

                $payments = [];
                $total_paid = 0;
                $no_of_paid_emi = 0;

                $sql_payments = "SELECT payment_date, amount_paid, notes, status, proof_image 
                                 FROM payments 
                                 WHERE loan_id = ? 
                                 ORDER BY payment_date DESC";
                                 
                $stmt_payments = $conn->prepare($sql_payments);
                $stmt_payments->bind_param("i", $loan_id);

                if ($stmt_payments->execute()) {
                    $result_payments = $stmt_payments->get_result();

                    while ($row = $result_payments->fetch_assoc()) {
                        $row['amount_paid'] = (float)$row['amount_paid'];
                        
                        if (!empty($row['proof_image'])) {
                            $row['proof_url'] = 'uploads/proofs/' . $row['proof_image'];
                        } else {
                            $row['proof_url'] = null;
                        }
                        
                        $payments[] = $row;

                        if ($row['status'] === 'approved') {
                            $total_paid += $row['amount_paid'];
                            $no_of_paid_emi++;
                        }
                    }
                    $stmt_payments->close();

                    $remaining_balance = max(0, (float)$loan_details['total_repayable_amount'] - $total_paid);

                    $gold_weight = !is_null($loan_details['gold_weight_grams']) ? (float)$loan_details['gold_weight_grams'] : null;
                    $gold_rate = !is_null($loan_details['gold_rate_per_gram']) ? (float)$loan_details['gold_rate_per_gram'] : null;
                    $gold_valuation = (!empty($gold_weight) && !empty($gold_rate)) ? round($gold_weight * $gold_rate, 2) : null;
                    $raw_dt_photo = $loan_details['gold_photo_path'] ?? '';
                    $gold_photo_urls = [];
                    if (!empty($raw_dt_photo)) {
                        $raw_paths = explode(',', $raw_dt_photo);
                        foreach ($raw_paths as $raw_path_item) {
                            $raw_path_item = trim($raw_path_item);
                            if (empty($raw_path_item)) continue;

                            if (strpos($raw_path_item, 'http') === 0) {
                                $gold_photo_urls[] = $raw_path_item;
                            } else {
                                $rel_dt = (strpos($raw_path_item, 'Agents/') === 0) ? $raw_path_item : 'Agents/' . ltrim($raw_path_item, '/');
                                $url_dt = $rel_dt;
                                $disk_dt = __DIR__ . '/../../' . $rel_dt;
                                if (!file_exists($disk_dt)) {
                                    if (strpos($rel_dt, 'Agents/upload/') === 0) {
                                        $alt_dt = 'Agents/uploads/' . substr($rel_dt, 14);
                                        if (file_exists(__DIR__ . '/../../' . $alt_dt)) $url_dt = $alt_dt;
                                    } elseif (strpos($rel_dt, 'Agents/uploads/') === 0) {
                                        $alt_dt = 'Agents/upload/' . substr($rel_dt, 15);
                                        if (file_exists(__DIR__ . '/../../' . $alt_dt)) $url_dt = $alt_dt;
                                    }
                                }
                                $gold_photo_urls[] = $url_dt;
                            }
                        }
                    }
                    $gold_photo_url = !empty($gold_photo_urls) ? $gold_photo_urls[0] : null;

                    // --- Calculate Pending EMI & Interest Details for All Loan Types ---
                    $is_monthly_interest = (($loan_details['interest_calculation_type'] ?? '') === 'monthly_interest_only');
                    $emi_amount = (float)($loan_details['monthly_installment'] ?? 0);
                    $loan_amt = (float)($loan_details['loan_amount'] ?? 0);
                    $int_rate = (float)($loan_details['interest_rate'] ?? 0);

                    if ($emi_amount <= 0 && $loan_amt > 0 && $int_rate > 0) {
                        $emi_amount = round(($loan_amt * $int_rate) / 100, 2);
                    }

                    $accrued_months = 0;
                    $total_accrued_interest = 0.0;
                    $pending_interest_due = 0.0;
                    $pending_emis_count = 0;
                    $pending_amount = 0.0;
                    $total_emi = (int)($loan_details['tenure'] ?? 0);
                    $st_clean = strtolower(trim($loan_details['status'] ?? ''));

                    if ($is_monthly_interest) {
                        $start_date_val = !empty($loan_details['loan_start_date']) ? $loan_details['loan_start_date'] : (!empty($loan_details['approval_date']) ? $loan_details['approval_date'] : $loan_details['application_date']);

                        if (!empty($start_date_val) && !in_array($st_clean, ['rejected', 'pending'])) {
                            $start_dt = new DateTime($start_date_val);
                            $today_dt = new DateTime();

                            if ($today_dt >= $start_dt) {
                                $ys = (int)$start_dt->format('Y');
                                $ms = (int)$start_dt->format('m');
                                $yt = (int)$today_dt->format('Y');
                                $mt = (int)$today_dt->format('m');

                                $accrued_months = ($yt - $ys) * 12 + ($mt - $ms) + 1;
                                if ($accrued_months < 0) $accrued_months = 0;
                            }
                        }

                        if (in_array($st_clean, ['closed', 'paid'])) {
                            $pending_interest_due = 0.0;
                            $pending_emis_count = 0;
                            $pending_amount = 0.0;
                            $total_accrued_interest = (float)$total_paid;
                        } else {
                            $total_accrued_interest = $accrued_months * $emi_amount;
                            $pending_interest_due = max(0, $total_accrued_interest - $total_paid);
                            $paid_months_calc = ($emi_amount > 0) ? (int)floor($total_paid / $emi_amount) : 0;
                            $pending_emis_count = max(0, $accrued_months - $paid_months_calc);
                            $pending_amount = round($pending_interest_due, 2);
                        }
                        $total_emi = $accrued_months;
                        $remaining_balance = 0.00;
                    } else {
                        $remaining_balance = max(0, (float)$loan_details['total_repayable_amount'] - $total_paid);
                        if (in_array($st_clean, ['closed', 'paid'])) {
                            $pending_emis_count = 0;
                            $pending_amount = 0.0;
                        } else {
                            $paid_months_calc = ($emi_amount > 0) ? (int)floor($total_paid / $emi_amount) : $no_of_paid_emi;
                            $pending_emis_count = max(0, $total_emi - $paid_months_calc);
                            $pending_amount = round($pending_emis_count * $emi_amount, 2);
                        }
                    }

                    $pending_emi_description = $pending_emis_count . ' Pending EMI(s) (₹' . number_format($pending_amount, 2) . ')';

                    $response = [
                        'status' => 'success',
                        'data' => [
                            'id' => $loan_details['id'],
                            'loan_type' => $loan_details['loan_type'] ?? 'standard',
                            'interest_calculation_type' => $loan_details['interest_calculation_type'] ?? 'flat_total',
                            'loan_amount' => (float)$loan_details['loan_amount'],
                            'total_repayable_amount' => (float)$loan_details['total_repayable_amount'],
                            'monthly_installment' => $emi_amount,
                            'emi_amount' => $emi_amount,
                            'processing_fee' => (float)$loan_details['processing_fee'],
                            'gold_weight_grams' => $gold_weight,
                            'gold_rate_per_gram' => $gold_rate,
                            'gold_valuation' => $gold_valuation,
                            'gold_photo_url' => $gold_photo_url,
                            'gold_photo_urls' => $gold_photo_urls,
                            'gold_photos' => $gold_photo_urls,
                            'interest_rate' => (float)$loan_details['interest_rate'],
                            'tenure_description' => (($loan_details['interest_calculation_type'] ?? '') === 'monthly_interest_only') ? 'Monthly (Until Closed)' : ($loan_details['tenure'] . ' ' . ucfirst($loan_details['repayment_cycle']) . ' Payments'),
                            'status' => $loan_details['status'],
                            'application_date' => $loan_details['application_date'],
                            'loan_start_date' => $loan_details['loan_start_date'] ?? $loan_details['approval_date'],
                            'approval_date' => $loan_details['approval_date'],
                            'admin_notes' => $loan_details['admin_notes'],
                            'total_paid' => round($total_paid, 2),
                            'remaining_balance' => round($remaining_balance, 2),
                            'total_emi' => $total_emi,
                            'no_of_paid_emi' => $no_of_paid_emi,
                            'no_of_pending_emi' => $pending_emis_count,
                            'pending_emis_count' => $pending_emis_count,
                            'pending_amount' => $pending_amount,
                            'pending_interest_due' => round($pending_interest_due, 2),
                            'pending_emi_description' => $pending_emi_description,
                            'accrued_months' => $accrued_months,
                            'total_accrued_interest' => round($total_accrued_interest, 2),
                            'payments' => $payments
                        ]
                    ];
                    $stmt_loan->close();
                    send_api_json_response($response, 200, $conn);

                } else {
                    $stmt_loan->close();
                    send_api_json_response(['status' => 'error', 'message' => 'Database error fetching payment history: ' . $stmt_payments->error], 500, $conn);
                }
            } else {
                $stmt_loan->close();
                send_api_json_response(['status' => 'error', 'message' => 'Loan not found or access denied.'], 404, $conn);
            }
        } else {
            $stmt_loan->close();
            send_api_json_response(['status' => 'error', 'message' => 'Database error fetching loan details: ' . $stmt_loan->error], 500, $conn);
        }
    } else {
        send_api_json_response(['status' => 'error', 'message' => 'Invalid or missing loan ID.'], 400, $conn);
    }
} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>
