<?php
/**
 * Automated System Test Runner and Validation Script
 * Verifies all classes, database connection, and operations work correctly.
 */

define('TEST_MODE', true);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Employee.php';
require_once __DIR__ . '/../classes/Department.php';
require_once __DIR__ . '/../classes/Leave.php';
require_once __DIR__ . '/../classes/Attendance.php';
require_once __DIR__ . '/../classes/Appraisal.php';
require_once __DIR__ . '/../classes/Announcement.php';

$tests_run = 0;
$tests_passed = 0;

function assertTest($condition, $message) {
    global $tests_run, $tests_passed;
    $tests_run++;
    if ($condition) {
        $tests_passed++;
        echo "✅ PASS: {$message}\n";
    } else {
        echo "❌ FAIL: {$message}\n";
    }
}

echo "=== STARTING IPMC TAMALE EMS TEST SUITE ===\n\n";

// 1. Database Connection Test
try {
    $db = Database::connect();
    assertTest($db instanceof PDO, "Database::connect() returns a valid PDO instance");
} catch (Exception $e) {
    assertTest(false, "Database connection failed: " . $e->getMessage());
}

// 2. Department Model Test
$deptModel = new Department();
$allDepts = $deptModel->getAll();
assertTest(is_array($allDepts) && count($allDepts) > 0, "Department::getAll() fetches seeded departments");

// Create temporary department
$temp_code = "TEST-" . rand(100, 999);
$temp_name = "Test Department " . rand(100, 999);
$create_ok = $deptModel->create($temp_name, $temp_code, "Temporary testing department");
assertTest($create_ok, "Department::create() adds a new department successfully");

// Fetch department ID
$new_depts = $deptModel->getAll();
$temp_dept = null;
foreach ($new_depts as $d) {
    if ($d['code'] === $temp_code) {
        $temp_dept = $d;
        break;
    }
}
assertTest($temp_dept !== null, "Created department exists in department directory");

// 3. Employee Model Test
$empModel = new Employee();
$adminUser = $empModel->login('admin@ipmc.edu.gh', 'admin123');
assertTest($adminUser !== false && $adminUser['role'] === 'Admin', "Employee::login() authenticates admin successfully with correct credentials");

$badUser = $empModel->login('admin@ipmc.edu.gh', 'wrong_pass');
assertTest($badUser === false, "Employee::login() rejects incorrect password");

// Generating Employee ID
$nextId = $empModel->generateEmployeeId();
assertTest(strpos($nextId, 'IPMC/TAM/') === 0, "Employee::generateEmployeeId() formats correctly ({$nextId})");

// Create Temporary Employee
$temp_email = "test_staff" . rand(1000, 9999) . "@ipmc.edu.gh";
$emp_data = [
    'first_name' => 'Validation',
    'last_name' => 'Tester',
    'email' => $temp_email,
    'password' => 'Pass123!',
    'role' => 'Employee',
    'staff_type' => 'Non-Academic',
    'gender' => 'Other',
    'phone' => '+233000000000',
    'department_id' => $temp_dept['id'],
    'designation' => 'Automation Agent',
    'joining_date' => date('Y-m-d')
];
$new_emp_id = $empModel->create($emp_data);
assertTest($new_emp_id > 0, "Employee::create() registers employee and allocates leave balances");

// Fetch Created Employee
$created_emp = $empModel->findById($new_emp_id);
assertTest($created_emp !== false && $created_emp['email'] === $temp_email, "Employee::findById() retrieves correct employee profile details");

// Search & Filter Verification tests
$search_by_name = $empModel->getAll(['search' => 'Validation']);
$search_by_email = $empModel->getAll(['search' => $temp_email]);
$search_by_id = $empModel->getAll(['search' => $created_emp['employee_id']]);
$filter_with_dept = $empModel->getAll([
    'search' => 'Tester',
    'department_id' => $temp_dept['id'],
    'role' => 'Employee',
    'status' => 'Active'
]);

assertTest(count($search_by_name) >= 1 && $search_by_name[0]['id'] == $new_emp_id, "Employee search by name returns correct record");
assertTest(count($search_by_email) >= 1 && $search_by_email[0]['id'] == $new_emp_id, "Employee search by email returns correct record");
assertTest(count($search_by_id) >= 1 && $search_by_id[0]['id'] == $new_emp_id, "Employee search by Employee ID returns correct record");
assertTest(count($filter_with_dept) >= 1 && $filter_with_dept[0]['id'] == $new_emp_id, "Employee search integrates correctly with department/role/status filters");

// 4. Leave Balance & Application Test
$leaveModel = new Leave();
$balances = $leaveModel->getBalances($new_emp_id);
assertTest(count($balances) === 3, "New employee is allocated exactly 3 standard leave types (Annual, Sick, Casual)");

// Apply for Leave (1 day casual leave)
$start_date = date('Y-m-d', strtotime('+5 days'));
$end_date = date('Y-m-d', strtotime('+5 days')); // 1 day
$apply_res = $leaveModel->apply($new_emp_id, 'Casual', $start_date, $end_date, "Testing leave workflow automation.");
assertTest($apply_res === true, "Leave::apply() submits casual request successfully");

// Fetch the leave application
$emp_leaves = $leaveModel->getAll(['employee_id' => $new_emp_id]);
assertTest(count($emp_leaves) === 1, "Leave::getAll() lists the newly submitted request");
$temp_leave_id = $emp_leaves[0]['id'];

// Process and Approve Leave Request
$process_res = $leaveModel->process($temp_leave_id, 'Approved', $adminUser['id'], "Validation process test approved.");
assertTest($process_res === true, "Leave::process() approves request and modifies balances");

// Verify leave balance was updated
$casual_bal = $leaveModel->getBalanceByType($new_emp_id, 'Casual');
assertTest($casual_bal['used'] == 1, "Approved casual leave decremented allocation correctly (used days = {$casual_bal['used']})");

// 5. Attendance Marking Test
$attendanceModel = new Attendance();
$mark_ok = $attendanceModel->mark($new_emp_id, date('Y-m-d'), 'Late', '08:35:00', '17:00:00', 'Slight delay in validation process.');
assertTest($mark_ok, "Attendance::mark() records employee presence correctly");

$att_record = $attendanceModel->getReportData(['employee_id' => $new_emp_id, 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d')]);
assertTest(count($att_record) === 1 && $att_record[0]['status'] === 'Late', "Attendance::getReportData() fetches logged records with correct status");

// 6. Appraisal Ratings Test
$appraisalModel = new Appraisal();
$rate_ok = $appraisalModel->create($new_emp_id, $adminUser['id'], 5, "Exceeded verification expectation standards.", "2025 - Q1");
assertTest($rate_ok === true, "Appraisal::create() records high-level employee feedback successfully");

$emp_appraisals = $appraisalModel->getByEmployee($new_emp_id);
assertTest(count($emp_appraisals) === 1 && $emp_appraisals[0]['rating'] === 5, "Appraisal::getByEmployee() fetches ratings accurately");

// 7. Announcements Test
$annModel = new Announcement();
$post_ok = $annModel->create("Testing Bulletin Board", "Automated system wide validation announcements content message details.", $adminUser['id']);
assertTest($post_ok, "Announcement::create() posts new institutional bulletin");

$announcements = $annModel->getLatest(1);
assertTest(count($announcements) === 1 && $announcements[0]['title'] === "Testing Bulletin Board", "Announcement::getLatest() retrieves correct notice");

// 8. Salary, Bonuses, and Payroll Integration Tests
require_once __DIR__ . '/../classes/Salary.php';
$salaryModel = new Salary();

// Set basic salary
$set_sal_ok = $salaryModel->updateSalary($new_emp_id, 4800.00, $adminUser['id']);
assertTest($set_sal_ok, "Salary::updateSalary() sets basic monthly salary of GHS 4,800.00");

// Check current salary matches
$curr_sal = $salaryModel->getSalary($new_emp_id);
assertTest($curr_sal && floatval($curr_sal['basic_salary']) === 4800.00, "Salary::getSalary() retrieves the correct basic rate of GHS 4,800.00");

// Check history log has entry
$sal_hist = $salaryModel->getHistory($new_emp_id);
assertTest(count($sal_hist) === 1 && floatval($sal_hist[0]['new_salary']) === 4800.00, "Salary::getHistory() logs salary modification properly");

// Add employee bonus
$add_bon_ok = $salaryModel->addBonus($new_emp_id, 'Performance Bonus', 650.00, date('Y-m-d'), 'Outstanding automation integration.');
assertTest($add_bon_ok, "Salary::addBonus() awards GHS 650.00 performance bonus");

// Verify monthly bonus total
$bonus_tot = $salaryModel->getMonthlyBonusTotal($new_emp_id, date('Y'), date('n'));
assertTest($bonus_tot === 650.00, "Salary::getMonthlyBonusTotal() returns GHS 650.00 for the current month");

// Verify monthly payroll preview calculations
$payroll_entry = $salaryModel->getMonthlyPayrollEntry($new_emp_id, date('Y'), date('n'));
assertTest(floatval($payroll_entry['basic_salary']) === 4800.00 && floatval($payroll_entry['bonus_amount']) === 650.00 && floatval($payroll_entry['total_earnings']) === 5450.00, "Salary::getMonthlyPayrollEntry() automatically calculates total earnings (Basic + Bonuses) correctly");

// Mark as paid
$save_pay_ok = $salaryModel->saveMonthlyPayment($new_emp_id, date('Y'), date('n'), 'Paid', date('Y-m-d'));
assertTest($save_pay_ok, "Salary::saveMonthlyPayment() registers monthly payout and marks status as Paid");

// Verify payout slips in history list
$pay_hist = $salaryModel->getPaymentHistory($new_emp_id);
assertTest(count($pay_hist) === 1 && $pay_hist[0]['status'] === 'Paid' && floatval($pay_hist[0]['total_earnings']) === 5450.00, "Salary::getPaymentHistory() returns pay slips with complete historical amounts");

// Clean Up Temporary Database Objects to prevent bloating
echo "\n=== CLEANING UP TEMPORARY TESTING ENTITIES ===\n";
try {
    $db_cleanup = Database::connect();

    // Delete test salary payments, history, bonuses, and salaries
    $db_cleanup->exec("DELETE FROM salary_payments WHERE employee_id = {$new_emp_id}");
    $db_cleanup->exec("DELETE FROM bonuses WHERE employee_id = {$new_emp_id}");
    $db_cleanup->exec("DELETE FROM salary_history WHERE employee_id = {$new_emp_id}");
    $db_cleanup->exec("DELETE FROM salaries WHERE employee_id = {$new_emp_id}");

    // Delete test announcement
    $db_cleanup->exec("DELETE FROM announcements WHERE title = 'Testing Bulletin Board'");

    // Delete test appraisals
    $db_cleanup->exec("DELETE FROM appraisals WHERE employee_id = {$new_emp_id}");

    // Delete test attendance
    $db_cleanup->exec("DELETE FROM attendance WHERE employee_id = {$new_emp_id}");

    // Delete test leaves and balances
    $db_cleanup->exec("DELETE FROM leaves WHERE employee_id = {$new_emp_id}");
    $db_cleanup->exec("DELETE FROM leave_balances WHERE employee_id = {$new_emp_id}");

    // Delete test employee
    $db_cleanup->exec("DELETE FROM employees WHERE id = {$new_emp_id}");
    echo "Deleted temporary test employee, salary tables, appraisals, leaves, balances, and attendance logs.\n";

    // Delete test department
    $deptModel->delete($temp_dept['id']);
    echo "Deleted temporary test department.\n";

    assertTest(true, "Cleanup process finished with absolute success.");
} catch (Exception $e) {
    assertTest(false, "Cleanup process failed: " . $e->getMessage());
}

echo "\n============================================\n";
echo "TEST RESULTS: {$tests_passed} / {$tests_run} PASSED\n";
if ($tests_passed === $tests_run) {
    echo "💯 ALL TESTS COMPLETED SUCCESSFULLY WITHOUT ERRORS!\n";
} else {
    echo "🚨 SOME TESTS ENCOUNTERED FAILURES. AUDIT NEEDED.\n";
}
echo "============================================\n";
