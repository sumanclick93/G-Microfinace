<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    // --- 3. Prepare Query ---
    $sql = "SELECT
                l.id,
                l.loan_amount,
                l.interest_rate,
                l.monthly_installment,
                l.total_repayable_amount,
                l.status,
                l.application_date,
                l.approval_date,
                l.loan_start_date,
                l.tenure,
                l.repayment_cycle,
                l.loan_type,
                l.interest_calculation_type,
                l.gold_weight_grams,
                l.gold_photo_path,
                l.gold_rate_per_gram,
                l.processing_fee,
                COUNT(p.id) AS no_of_paid_emi,
                SUM(p.amount_paid) AS total_paid
            FROM loans l
            LEFT JOIN payments p ON l.id = p.loan_id
            WHERE l.customer_id = ?
            GROUP BY l.id
            ORDER BY l.application_date DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("SQL Prepare Error in get_all_loans.php: " . $conn->error);
        send_api_json_response(['status' => 'error', 'message' => 'Database error preparing statement.'], 500, $conn);
    }

    $stmt->bind_param("i", $customer_id);

    // --- 4. Execute and Fetch Data ---
    $loans = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Format data
            $row['loan_amount'] = (float)$row['loan_amount'];
            $row['interest_rate'] = (float)($row['interest_rate'] ?? 0);
            $row['monthly_installment'] = (float)($row['monthly_installment'] ?? 0);
            $row['total_repayable_amount'] = (float)$row['total_repayable_amount'];
            $row['loan_type'] = $row['loan_type'] ?? 'standard';
            $row['interest_calculation_type'] = $row['interest_calculation_type'] ?? 'flat_total';
            $row['gold_weight_grams'] = !is_null($row['gold_weight_grams']) ? (float)$row['gold_weight_grams'] : null;
            $row['gold_rate_per_gram'] = !is_null($row['gold_rate_per_gram']) ? (float)$row['gold_rate_per_gram'] : null;
            $row['processing_fee'] = (float)$row['processing_fee'];

            if (!empty($row['gold_weight_grams']) && !empty($row['gold_rate_per_gram'])) {
                $row['gold_valuation'] = round($row['gold_weight_grams'] * $row['gold_rate_per_gram'], 2);
            } else {
                $row['gold_valuation'] = null;
            }

            $raw_api_photo = $row['gold_photo_path'] ?? '';
            $gold_photo_urls = [];
            if (!empty($raw_api_photo)) {
                $raw_paths = explode(',', $raw_api_photo);
                foreach ($raw_paths as $raw_path_item) {
                    $raw_path_item = trim($raw_path_item);
                    if (empty($raw_path_item)) continue;

                    if (strpos($raw_path_item, 'http') === 0) {
                        $gold_photo_urls[] = $raw_path_item;
                    } else {
                        $rel_api = (strpos($raw_path_item, 'Agents/') === 0) ? $raw_path_item : 'Agents/' . ltrim($raw_path_item, '/');
                        $url_api = $rel_api;
                        $disk_p = __DIR__ . '/../../' . $rel_api;
                        if (!file_exists($disk_p)) {
                            if (strpos($rel_api, 'Agents/upload/') === 0) {
                                $alt_api = 'Agents/uploads/' . substr($rel_api, 14);
                                if (file_exists(__DIR__ . '/../../' . $alt_api)) $url_api = $alt_api;
                            } elseif (strpos($rel_api, 'Agents/uploads/') === 0) {
                                $alt_api = 'Agents/upload/' . substr($rel_api, 15);
                                if (file_exists(__DIR__ . '/../../' . $alt_api)) $url_api = $alt_api;
                            }
                        }
                        $gold_photo_urls[] = $url_api;
                    }
                }
            }
            $row['gold_photo_url'] = !empty($gold_photo_urls) ? $gold_photo_urls[0] : null;
            $row['gold_photo_urls'] = $gold_photo_urls;
            $row['gold_photos'] = $gold_photo_urls;
            unset($row['gold_photo_path']);

            // Add keys
            $row['total_emi'] = (int)$row['tenure'];
            $row['no_of_paid_emi'] = (int)$row['no_of_paid_emi'];
            
            $is_monthly_interest_all = (($row['interest_calculation_type'] ?? '') === 'monthly_interest_only');
            $accrued_months_all = 0;
            $total_accrued_interest_all = 0.0;
            $pending_interest_due_all = 0.0;
            $pending_emis_count_all = 0;
            $total_paid_all = (float)($row['total_paid'] ?? 0);

            if ($is_monthly_interest_all) {
                $m_inst_all = (float)($row['monthly_installment'] ?? 0);
                if ($m_inst_all <= 0 && (float)$row['loan_amount'] > 0 && (float)$row['interest_rate'] > 0) {
                    $m_inst_all = round(((float)$row['loan_amount'] * (float)$row['interest_rate']) / 100, 2);
                }
                
                $start_date_val_all = !empty($row['loan_start_date']) ? $row['loan_start_date'] : (!empty($row['approval_date']) ? $row['approval_date'] : $row['application_date']);
                $st_clean_all = strtolower(trim($row['status'] ?? ''));

                if (!empty($start_date_val_all) && !in_array($st_clean_all, ['rejected', 'pending'])) {
                    $start_dt_all = new DateTime($start_date_val_all);
                    $today_dt_all = new DateTime();
                    
                    if ($today_dt_all >= $start_dt_all) {
                        $ys_a = (int)$start_dt_all->format('Y');
                        $ms_a = (int)$start_dt_all->format('m');
                        $yt_a = (int)$today_dt_all->format('Y');
                        $mt_a = (int)$today_dt_all->format('m');
                        
                        $accrued_months_all = ($yt_a - $ys_a) * 12 + ($mt_a - $ms_a) + 1;
                        if ($accrued_months_all < 0) $accrued_months_all = 0;
                    }
                }

                if (in_array($st_clean_all, ['closed', 'paid'])) {
                    $pending_interest_due_all = 0.0;
                    $pending_emis_count_all = 0;
                    $total_accrued_interest_all = $total_paid_all;
                } else {
                    $total_accrued_interest_all = $accrued_months_all * $m_inst_all;
                    $pending_interest_due_all = max(0, $total_accrued_interest_all - $total_paid_all);
                    $paid_months_calc_all = ($m_inst_all > 0) ? (int)floor($total_paid_all / $m_inst_all) : 0;
                    $pending_emis_count_all = max(0, $accrued_months_all - $paid_months_calc_all);
                }
            }

            $row['accrued_months'] = $accrued_months_all;
            $row['total_accrued_interest'] = round($total_accrued_interest_all, 2);
            $row['pending_interest_due'] = round($pending_interest_due_all, 2);
            $row['pending_emis_count'] = $pending_emis_count_all;
            $row['pending_emi_description'] = $is_monthly_interest_all ? ($pending_emis_count_all . ' Pending EMI(s) (₹' . number_format($pending_interest_due_all, 2) . ')') : null;

            // Tenure description
            if ($is_monthly_interest_all) {
                 $row['tenure_description'] = 'Monthly (Until Closed)';
            } elseif (isset($row['tenure']) && isset($row['repayment_cycle'])) {
                 $row['tenure_description'] = $row['tenure'] . ' ' . ucfirst($row['repayment_cycle']) . ' Payments';
            } else {
                 $row['tenure_description'] = 'N/A';
            }

            // Clean up original columns
            unset($row['tenure']);
            unset($row['repayment_cycle']);
             
            $loans[] = $row;
        }

        $stmt->close();
        send_api_json_response(['status' => 'success', 'data' => $loans], 200, $conn);

    } else {
        error_log("SQL Execute Error in get_all_loans.php: " . $stmt->error);
        $stmt->close();
        send_api_json_response(['status' => 'error', 'message' => 'Database error fetching loans.'], 500, $conn);
    }

} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>