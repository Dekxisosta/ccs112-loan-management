<?php 

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * Unified CRUD API for Students, Loans, and Payments.
 *
 * Routes based on the '?entity=' query parameter (defaults to 'student').
 * Example usage:
 *   GET  /api.php?entity=student
 *   GET  /api.php?entity=loan&student_id=3
 *   GET  /api.php?entity=payment&loan_id=5
 *   POST /api.php?entity=payment   (with JSON body)
 *
 * Relationships:
 *   students (1) --> (M) loans (1) --> (M) payments
 *   - Each loan belongs to one student (loans.student_id -> students.id)
 *   - Each payment belongs to one loan (payments.loan_id -> loans.id)
 */

// Standard headers: respond in JSON, allow cross-origin requests (needed
// since the frontend likely runs on a different port/container than this API),
// and allow the HTTP methods our CRUD operations use.
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

// ---------------------------------------------------------
// DATABASE CONNECTION
// ---------------------------------------------------------
// "db" as the host works when this runs inside Docker Compose, where the
// MySQL container is named "db" on the same network. If running locally
// without Docker, this would need to be "localhost" or "127.0.0.1" instead.
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_db";

try {
    // PDO = PHP Data Objects, a database access layer. We use it here with
    // prepared statements (see "?" placeholders below) to prevent SQL injection.
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If the DB connection itself fails, stop immediately with a 500 error.
    echo json_with_code(["error" => "Connection failed: " . $e->getMessage()], 500);
    exit;
}

// The HTTP verb used for this request (GET, POST, PUT, DELETE).
$method = $_SERVER['REQUEST_METHOD'];

// Which "resource" this request is for. Passed as a query string param,
// e.g. ?entity=payment. Defaults to 'student' if not specified.
$entity = $_GET['entity'] ?? 'student'; 

// ---------------------------------------------------------
// 1. STUDENT MANAGEMENT
// ---------------------------------------------------------
// Full CRUD for the students table. This is the base entity that
// loans and payments are ultimately tied back to.
if ($entity === 'student') {
    switch ($method) {

        // GET /api.php?entity=student
        // Returns every student, most recently added first.
        case 'GET':
            $stmt = $pdo->query("SELECT * FROM students ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        // POST /api.php?entity=student
        // Body: { "name": "...", "email": "...", "course": "..." }
        // Creates a new student row.
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['name']) && !empty($data['email']) && !empty($data['course'])) {
                $stmt = $pdo->prepare("INSERT INTO students (name, email, course) VALUES (?, ?, ?)");
                $stmt->execute([$data['name'], $data['email'], $data['course']]);
                echo json_encode(["message" => "Student added successfully!"]);
            } else {
                echo json_with_code(["error" => "All fields are required"], 400);
            }
            break;

        // PUT /api.php?entity=student
        // Body: { "id": 1, "name": "...", "email": "...", "course": "..." }
        // Updates an existing student's info by ID.
        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['id']) && !empty($data['name']) && !empty($data['email']) && !empty($data['course'])) {
                $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, course = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['email'], $data['course'], $data['id']]);
                echo json_encode(["message" => "Student updated successfully!"]);
            } else {
                echo json_with_code(["error" => "Invalid data provided"], 400);
            }
            break;

        // DELETE /api.php?entity=student
        // Body: { "id": 1 }
        // Deletes a student by ID.
        // NOTE: if the loans table has a foreign key to students without
        // ON DELETE CASCADE, this could fail when the student still has
        // loans attached. Worth checking with whoever owns the DB schema.
        case 'DELETE':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['id'])) {
                $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                $stmt->execute([$data['id']]);
                echo json_encode(["message" => "Student deleted successfully!"]);
            } else {
                echo json_with_code(["error" => "ID required"], 400);
            }
            break;

        default:
            echo json_with_code(["error" => "Method not allowed"], 405);
            break;
    }
}

// ---------------------------------------------------------
// 2. LOAN MANAGEMENT
// ---------------------------------------------------------
// Loans belong to a single student (student_id foreign key).
// A student can have many loans (1:M relationship).
elseif ($entity === 'loan') {
    switch ($method) {

        // GET /api.php?entity=loan&student_id=3
        // Returns all loans belonging to ONE specific student.
        // student_id is required - without it we don't know whose loans to show.
        case 'GET':
            $student_id = $_GET['student_id'] ?? null;
            if ($student_id) {
                $stmt = $pdo->prepare("SELECT * FROM loans WHERE student_id = ? ORDER BY id DESC");
                $stmt->execute([$student_id]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } else {
                echo json_with_code(["error" => "student_id is required"], 400);
            }
            break;

        // POST /api.php?entity=loan
        // Body: { "student_id": 3, "amount": 1000, "loan_type": "Tuition", "status": "Pending" }
        // Creates a new loan tied to a student.
        // loan_type should be one of: Tuition, Books, Living Expenses
        // status should be one of: Pending, Approved, Disbursed
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['student_id']) && !empty($data['amount']) && !empty($data['loan_type']) && !empty($data['status'])) {
                $stmt = $pdo->prepare("INSERT INTO loans (student_id, amount, loan_type, status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$data['student_id'], $data['amount'], $data['loan_type'], $data['status']]);
                echo json_encode(["message" => "Loan added successfully!"]);
            } else {
                echo json_with_code(["error" => "All fields are required"], 400);
            }
            break;

        // NOTE: No PUT (update) or DELETE for loans yet.
        // A PUT would likely be needed later to move a loan through its
        // status lifecycle: Pending -> Approved -> Disbursed.
        default:
            echo json_with_code(["error" => "Method not allowed for loans"], 405);
            break;
    }
}

// ---------------------------------------------------------
// 3. PAYMENT MANAGEMENT
// ---------------------------------------------------------
// Payments belong to a single loan (loan_id foreign key).
// A loan can have many payments (1:M relationship) - representing
// installments paid over time toward that one loan.
elseif ($entity === 'payment') {
    switch ($method) {

        // GET /api.php?entity=payment&loan_id=5
        // Returns:
        //   1) the full list of payments made toward this loan
        //   2) a "summary" block with total_paid and remaining_balance,
        //      so the frontend doesn't have to calculate these itself.
        // loan_id is required - without it we don't know which loan's
        // payments to display.
        case 'GET':
            $loan_id = $_GET['loan_id'] ?? null;
            if ($loan_id) {
                // Step 1: fetch every payment row tied to this loan,
                // most recent payment date first.
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
                $stmt->execute([$loan_id]);
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Step 2: fetch the original loan amount so we can compute
                // how much of it is still owed.
                $loanStmt = $pdo->prepare("SELECT amount FROM loans WHERE id = ?");
                $loanStmt->execute([$loan_id]);
                $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

                // Step 3: sum up all payments to get total_paid.
                $total_paid = 0;
                foreach ($payments as $payment) {
                    $total_paid += (float)$payment['payment_amount'];
                }

                // Step 4: remaining_balance = original loan amount - total_paid.
                // If the loan_id didn't match any loan, $loan will be null,
                // so we fall back to 0 rather than causing a PHP warning.
                $remaining_balance = $loan ? ((float)$loan['amount'] - $total_paid) : 0;

                echo json_encode([
                    "payments" => $payments,
                    "summary" => [
                        "total_paid" => $total_paid,
                        "remaining_balance" => $remaining_balance
                    ]
                ]);
            } else {
                echo json_with_code(["error" => "loan_id is required"], 400);
            }
            break;

        // POST /api.php?entity=payment
        // Body: { "loan_id": 5, "payment_amount": 300, "payment_date": "2026-01-05", "payment_method": "Cash" }
        // Records a new payment against a loan.
        // payment_method should be one of: Cash, Bank Transfer, Online Payment
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            if (!empty($data['loan_id']) && !empty($data['payment_amount']) && !empty($data['payment_date']) && !empty($data['payment_method'])) {
                $stmt = $pdo->prepare("INSERT INTO payments (loan_id, payment_amount, payment_date, payment_method) VALUES (?, ?, ?, ?)");
                $stmt->execute([$data['loan_id'], $data['payment_amount'], $data['payment_date'], $data['payment_method']]);
                echo json_encode(["message" => "Payment recorded successfully!"]);
            } else {
                echo json_with_code(["error" => "All fields are required"], 400);
            }
            break;

        // NOTE: No PUT/DELETE for payments - not required by the spec,
        // since payments are meant to be an append-only log/record of
        // what was paid and when (like a receipt trail).
        default:
            echo json_with_code(["error" => "Method not allowed for payments"], 405);
            break;
    }
}

// ---------------------------------------------------------
// INVALID ENTITY ROUTE
// ---------------------------------------------------------
// Fires if ?entity= is something other than student, loan, or payment.
else {
    echo json_with_code(["error" => "Invalid entity specified"], 400);
}

/**
 * Helper function to send a JSON response with a specific HTTP status code.
 * Used for error responses throughout the file (400, 405, 500, etc.)
 * so the frontend can distinguish "success" from "something went wrong".
 */
function json_with_code($data, $code) {
    http_response_code($code);
    return json_encode($data);
}
?>