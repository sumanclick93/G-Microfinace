<?php
include('config.php');

if (!isset($_SESSION['admin_id'])) {
    die("<div style='font-family:sans-serif; padding:20px; color:red;'>Access Denied. Please login to Super Admin panel first, then access this URL.</div>");
}

echo "<h2>Executing Database Migration...</h2>";
echo "<ul style='font-family:sans-serif;'>";

// 1. Check & Alter loans table columns
$res = $conn->query("SHOW COLUMNS FROM loans");
$existing_columns = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
}

$alter_queries = [];
if (!in_array('loan_type', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN loan_type ENUM('standard', 'interest_only', 'gold') NOT NULL DEFAULT 'standard' AFTER id";
}
if (!in_array('interest_calculation_type', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN interest_calculation_type ENUM('flat_total', 'monthly_interest_only') NOT NULL DEFAULT 'flat_total' AFTER loan_type";
}
if (!in_array('gold_weight_grams', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN gold_weight_grams DECIMAL(10,3) DEFAULT NULL AFTER tenure";
}
if (!in_array('gold_photo_path', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN gold_photo_path VARCHAR(255) DEFAULT NULL AFTER gold_weight_grams";
}
if (!in_array('gold_rate_per_gram', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN gold_rate_per_gram DECIMAL(10,2) DEFAULT NULL AFTER gold_photo_path";
}
if (!in_array('processing_fee', $existing_columns)) {
    $alter_queries[] = "ALTER TABLE loans ADD COLUMN processing_fee DECIMAL(10,2) DEFAULT 0.00 AFTER gold_rate_per_gram";
}

if (empty($alter_queries)) {
    echo "<li style='color:green;'>`loans` table columns already updated.</li>";
} else {
    foreach ($alter_queries as $q) {
        if ($conn->query($q)) {
            echo "<li style='color:green;'>Successfully executed: <code>" . htmlspecialchars($q) . "</code></li>";
        } else {
            echo "<li style='color:red;'>Error executing query: " . htmlspecialchars($conn->error) . "</li>";
        }
    }
}

// 2. Create system_settings table
$create_table_sql = "CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($create_table_sql)) {
    echo "<li style='color:green;'>`system_settings` table checked/created successfully.</li>";
} else {
    echo "<li style='color:red;'>Error creating system_settings table: " . htmlspecialchars($conn->error) . "</li>";
}

// 3. Seed default settings
$seed_sql = "INSERT INTO system_settings (setting_key, setting_value) 
VALUES ('gold_rate_per_gram', '5500.00'), ('gold_loan_processing_fee_percent', '1.50')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);";

if ($conn->query($seed_sql)) {
    echo "<li style='color:green;'>Default gold rate and processing fee seeded into `system_settings`.</li>";
} else {
    echo "<li style='color:red;'>Error seeding system_settings: " . htmlspecialchars($conn->error) . "</li>";
}

// 4. Update existing agent-collected payments to 'approved' by default
$update_payments_sql = "UPDATE payments SET status = 'approved' WHERE (status IS NULL OR status = '' OR status = 'pending') AND (collected_by_agent_id != 'self' AND collected_by_agent_id IS NOT NULL AND collected_by_agent_id != 0)";
if ($conn->query($update_payments_sql)) {
    $affected = $conn->affected_rows;
    echo "<li style='color:green;'>Updated $affected agent-collected payment(s) to 'approved' status.</li>";
} else {
    echo "<li style='color:red;'>Error updating payment statuses: " . htmlspecialchars($conn->error) . "</li>";
}

// 5. Clean up any invalid '0' values in gold_photo_path
$conn->query("UPDATE loans SET gold_photo_path = NULL WHERE gold_photo_path = '0'");
echo "<li style='color:green;'>Cleaned up invalid photo path entries.</li>";

echo "</ul>";
echo "<h3 style='font-family:sans-serif; color:green;'>Migration Finished Successfully!</h3>";
echo "<p style='font-family:sans-serif;'><a href='settings.php'>Go to System Settings</a> | <a href='all-loans.php'>Go to All Loans</a></p>";
?>
