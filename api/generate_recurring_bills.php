<?php
// api/generate_recurring_bills.php
require_once "../config/database.php";
header('Content-Type: application/json');

$conn->begin_transaction();
try {
    // Select all schedules that are currently active
    $schedules_sql = "SELECT * FROM recurring_bills WHERE (end_date IS NULL OR end_date >= CURDATE()) AND start_date <= CURDATE() FOR UPDATE";
    $schedules_result = $conn->query($schedules_sql);
    $schedules = $schedules_result->fetch_all(MYSQLI_ASSOC);
    
    $bills_created_count = 0;
    
    // Set the generation window to 10 days from now
    $generation_limit_date = (new DateTime())->modify('+15 days');

    foreach ($schedules as $schedule) {
        $start_date_obj = new DateTime($schedule['start_date']);
        
        // Determine the starting point for our check.
        // If a bill was generated before, start checking from the next period. Otherwise, start from the schedule's start_date.
        $cursor_date = $schedule['last_generated_date'] 
            ? (new DateTime($schedule['last_generated_date']))->modify('+1 day') // Start checking from the day after the last generation
            : $start_date_obj;

        // Loop and catch up on all due bills until we are past the 10-day window
        while ($cursor_date <= $generation_limit_date) {
            
            // Calculate the actual next due date for the current period
            $next_due_date = clone $cursor_date;
            $next_due_date->setDate($next_due_date->format('Y'), $next_due_date->format('m'), $schedule['recur_day']);
            
            // If the calculated due date is in the past relative to our cursor (e.g., start date is 20th, recur day is 5th), move to the next month
            if ($next_due_date < $cursor_date) {
                 $next_due_date->modify('+1 month');
            }
            
            // Now, check if this calculated due date is within our window
            if ($next_due_date <= $generation_limit_date) {
                $due_date_str = $next_due_date->format('Y-m-d');
                
                // Check for duplicates to be safe
                $check_sql = "SELECT id FROM bills WHERE biller_id = ? AND due_date = ?";
                $stmt_check = $conn->prepare($check_sql);
                $stmt_check->bind_param("is", $schedule['biller_id'], $due_date_str);
                $stmt_check->execute();
                $exists = $stmt_check->get_result()->fetch_assoc();
                $stmt_check->close();

                if (!$exists) {
                    // Generate the bill
                    $bill_date_str = date('Y-m-d'); // Bill is created today
                    $insert_sql = "INSERT INTO bills (bill_date, due_date, biller_id, chart_of_account_id, amount, description, status) VALUES (?, ?, ?, ?, ?, ?, 'Unpaid')";
                    $stmt_insert = $conn->prepare($insert_sql);
                    $stmt_insert->bind_param("ssiids", $bill_date_str, $due_date_str, $schedule['biller_id'], $schedule['chart_of_account_id'], $schedule['amount'], $schedule['description']);
                    $stmt_insert->execute();
                    $stmt_insert->close();
                    $bills_created_count++;
                    
                    // Update the last_generated_date for this schedule
                    $update_sql = "UPDATE recurring_bills SET last_generated_date = ? WHERE id = ?";
                    $stmt_update = $conn->prepare($update_sql);
                    $stmt_update->bind_param("si", $due_date_str, $schedule['id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                }

                // Set the cursor to the day after the bill we just processed
                $cursor_date = (new DateTime($due_date_str))->modify('+1 day');

            } else {
                // The next due date is outside our window, so we can stop checking for this schedule
                break;
            }
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Generated " . $bills_created_count . " new bill(s)."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
?>