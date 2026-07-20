<?php
/**
 * Unified CRUD API for Students, Loans, and Payments.
 * Routes based on the '?entity=' query parameter (defaults to 'student').
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$host = "db";
$user = "root"; 
$pass = "rootpassword"; 
$dbname = "school_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_with_code(["error" => "Connection failed: " . $e->getMessage()], 500);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
// Use 'entity' parameter to route the request
$entity = $_GET['entity'] ?? 'student'; 

// ---------------------------------------------------------
// 1. STUDENT MANAGEMENT
// ---------------------------------------------------------
if ($entity === 'student') {
    switch ($method) {
        case 'GET':
            $stmt = $pdo->query("SELECT * FROM students ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

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
elseif ($entity === 'loan') {
    switch ($method) {
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

        default:
            echo json_with_code(["error" => "Method not allowed for loans"], 405);
            break;
    }
}

// ---------------------------------------------------------
// 3. PAYMENT MANAGEMENT
// ---------------------------------------------------------
elseif ($entity === 'payment') {
    switch ($method) {
        case 'GET':
            $loan_id = $_GET['loan_id'] ?? null;
            if ($loan_id) {
                // Fetch payments
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
                $stmt->execute([$loan_id]);
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Fetch loan amount to compute balance
                $loanStmt = $pdo->prepare("SELECT amount FROM loans WHERE id = ?");
                $loanStmt->execute([$loan_id]);
                $loan = $loanStmt->fetch(PDO::FETCH_ASSOC);

                $total_paid = 0;
                foreach ($payments as $payment) {
                    $total_paid += (float)$payment['payment_amount'];
                }
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

        default:
            echo json_with_code(["error" => "Method not allowed for payments"], 405);
            break;
    }
}

// ---------------------------------------------------------
// INVALID ENTITY ROUTE
// ---------------------------------------------------------
else {
    echo json_with_code(["error" => "Invalid entity specified"], 400);
}

/**
 * Helper function to return JSON
 */
function json_with_code($data, $code) {
    http_response_code($code);
    return json_encode($data);
}
?>