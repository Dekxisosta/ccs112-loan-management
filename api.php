<?php
/**
 * Simple CRUD API for managing students in a school database.
 * 
 * This API supports the following operations:
 * - GET: Retrieve all students
 * - POST: Add a new student
 * - PUT: Update an existing student
 * - DELETE: Remove a student
 * 
 * The API returns JSON responses and handles CORS for cross-origin requests.
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
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass); // Create a new PDO instance for database connection
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode to exception for better error handling
} catch (PDOException $e) {
    // Handle connection error and return a JSON response with an error message
    echo json_with_code(["error" => "Connection failed: " . $e->getMessage()], 500);
    exit;
}

$method = $_SERVER['REQUEST_METHOD']; // Get the HTTP request method

// Handle the request based on the HTTP method
// HTTP methods are used to perform different operations on the server. Each case corresponds to a CRUD operation.
switch ($method) {
    case 'GET':
        // READ: Get all students
        $stmt = $pdo->query("SELECT * FROM students ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        // CREATE: Add a new student
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['name']) && !empty($data['email']) && !empty($data['course'])) {
            $stmt = $pdo->prepare("INSERT INTO students (name, email, course) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['name'], 
                $data['email'], 
                $data['course']
            ]);
            echo json_encode(["message" => "Student added successfully!"]);
        } else {
            echo json_with_code(["error" => "All fields are required"], 400);
        }
        break;

    case 'PUT':
        // UPDATE: Modify an existing student
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['id']) && !empty($data['name']) && !empty($data['email']) && !empty($data['course'])) {
            $stmt = $pdo->prepare("UPDATE students SET name = ?, email = ?, course = ? WHERE id = ?");
            $stmt->execute([
                $data['name'], 
                $data['email'], 
                $data['course'], 
                $data['id']
            ]);
            echo json_encode(["message" => "Student updated successfully!"]);
        } else {
            echo json_with_code(["error" => "Invalid data provided"], 400);
        }
        break;

    case 'DELETE':
        // DELETE: Remove a student
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

/**
 * Helper function to return JSON response with a specific HTTP status code.
 *
 * @param array $data The data to be returned as JSON.
 * @param int $code The HTTP status code to set for the response.
 * @return string JSON encoded data.
 */
function json_with_code($data, $code) {
    http_response_code($code);
    return json_encode($data);
}
?>