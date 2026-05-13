<?php
require_once '../config.php';
requireLogin();

header('Content-Type: application/json');

function calculateAttendance($employee_id, $attendance_date, $check_in, $check_out) {
    global $conn;
    
    // Get employee and shift info
    $emp_result = $conn->query("SELECT e.shift_id, s.start_time, s.end_time, s.grace_minutes, s.weekly_off_days 
                                FROM employees e 
                                LEFT JOIN shifts s ON e.shift_id = s.id 
                                WHERE e.id = $employee_id LIMIT 1");
    
    if (!$emp_result || $emp_result->num_rows === 0) {
        return ['error' => 'Employee not found'];
    }
    
    $emp = $emp_result->fetch_assoc();
    
    // Check if it's a weekly off day
    $day_of_week = date('w', strtotime($attendance_date));
    $off_days = explode(',', $emp['weekly_off_days'] ?? '6,0');
    if (in_array($day_of_week, $off_days)) {
        return [
            'status' => 'weekly_off',
            'working_hours' => 0,
            'late_minutes' => 0,
            'early_minutes' => 0,
            'overtime_minutes' => 0
        ];
    }
    
    // Check if it's a holiday
    $holiday_check = $conn->query("SELECT id FROM holidays WHERE holiday_date = '$attendance_date' LIMIT 1");
    if ($holiday_check && $holiday_check->num_rows > 0) {
        return [
            'status' => 'holiday',
            'working_hours' => 0,
            'late_minutes' => 0,
            'early_minutes' => 0,
            'overtime_minutes' => 0
        ];
    }
    
    // If no check-in or check-out, mark as absent or missing punch
    if (empty($check_in) && empty($check_out)) {
        return [
            'status' => 'absent',
            'working_hours' => 0,
            'late_minutes' => 0,
            'early_minutes' => 0,
            'overtime_minutes' => 0
        ];
    }
    
    // Missing punch detection
    if (empty($check_in) || empty($check_out)) {
        return [
            'status' => 'present',
            'working_hours' => 0,
            'late_minutes' => 0,
            'early_minutes' => 0,
            'overtime_minutes' => 0,
            'notes' => empty($check_in) ? 'Missing check-in' : 'Missing check-out'
        ];
    }
    
    // Calculate working hours
    $in_time = strtotime($check_in);
    $out_time = strtotime($check_out);
    
    // Handle overnight shifts (e.g., 10 PM to 6 AM)
    if ($out_time < $in_time) {
        $out_time += 86400; // Add 24 hours
    }
    
    $working_seconds = $out_time - $in_time;
    $working_hours = round($working_seconds / 3600, 2);
    
    // Get shift times
    $shift_start = strtotime($emp['start_time']);
    $shift_end = strtotime($emp['end_time']);
    $grace_minutes = (int)($emp['grace_minutes'] ?? 15);
    
    // Calculate late minutes
    $late_seconds = $in_time - ($shift_start + ($grace_minutes * 60));
    $late_minutes = $late_seconds > 0 ? round($late_seconds / 60) : 0;
    
    // Calculate early leaving minutes
    $early_seconds = ($shift_end) - $out_time;
    $early_minutes = $early_seconds > 0 ? round($early_seconds / 60) : 0;
    
    // Calculate overtime minutes
    $overtime_seconds = $out_time - $shift_end;
    $overtime_minutes = $overtime_seconds > 0 ? round($overtime_seconds / 60) : 0;
    
    // Determine status
    $status = 'present';
    if ($late_minutes > 0) {
        $status = 'late';
    }
    if ($early_minutes > 0) {
        $status = 'early_leave';
    }
    if ($late_minutes > 0 && $early_minutes > 0) {
        $status = 'late'; // Prioritize late status
    }
    
    return [
        'status' => $status,
        'working_hours' => $working_hours,
        'late_minutes' => $late_minutes,
        'early_minutes' => $early_minutes,
        'overtime_minutes' => $overtime_minutes
    ];
}

// Handle API request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'calculate_single') {
        $employee_id = (int)$_POST['employee_id'];
        $attendance_date = $conn->real_escape_string($_POST['attendance_date']);
        $check_in = $conn->real_escape_string($_POST['check_in'] ?? NULL);
        $check_out = $conn->real_escape_string($_POST['check_out'] ?? NULL);
        
        $result = calculateAttendance($employee_id, $attendance_date, $check_in, $check_out);
        echo json_encode($result);
        exit;
    }
    
    if ($action === 'recalculate_all') {
        requireAdmin();
        
        $month = $conn->real_escape_string($_POST['month'] ?? date('Y-m'));
        
        // Get all attendance records for the month
        $result = $conn->query("SELECT id, employee_id, attendance_date, check_in, check_out 
                               FROM attendance 
                               WHERE DATE_FORMAT(attendance_date, '%Y-%m') = '$month'");
        
        $updated = 0;
        while ($row = $result->fetch_assoc()) {
            $calc = calculateAttendance($row['employee_id'], $row['attendance_date'], $row['check_in'], $row['check_out']);
            
            if (!isset($calc['error'])) {
                $sql = "UPDATE attendance SET 
                        working_hours = {$calc['working_hours']},
                        late_minutes = {$calc['late_minutes']},
                        early_minutes = {$calc['early_minutes']},
                        overtime_minutes = {$calc['overtime_minutes']},
                        status = '{$calc['status']}',
                        notes = " . (isset($calc['notes']) ? "'{$calc['notes']}'" : "NULL") . "
                        WHERE id = {$row['id']}";
                
                if ($conn->query($sql)) {
                    $updated++;
                }
            }
        }
        
        echo json_encode(['success' => true, 'updated' => $updated]);
        exit;
    }
    
    if ($action === 'mark_absent') {
        requireAdmin();
        
        $date = $conn->real_escape_string($_POST['date'] ?? date('Y-m-d'));
        
        // Get all active employees
        $employees = $conn->query("SELECT id FROM employees WHERE status = 'active'");
        
        $marked = 0;
        while ($emp = $employees->fetch_assoc()) {
            $emp_id = $emp['id'];
            
            // Check if attendance already exists
            $check = $conn->query("SELECT id FROM attendance WHERE employee_id = $emp_id AND attendance_date = '$date'");
            if (!$check || $check->num_rows === 0) {
                // Insert absent record
                $sql = "INSERT INTO attendance (employee_id, attendance_date, status, working_hours, late_minutes, early_minutes, overtime_minutes)
                        VALUES ($emp_id, '$date', 'absent', 0, 0, 0, 0)";
                if ($conn->query($sql)) {
                    $marked++;
                }
            }
        }
        
        echo json_encode(['success' => true, 'marked' => $marked]);
        exit;
    }
}

echo json_encode(['error' => 'Invalid request']);
