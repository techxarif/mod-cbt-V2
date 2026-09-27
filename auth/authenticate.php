<?php
// auth/authenticate.php — JSON Authentication Endpoint
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$role     = strtolower(trim($input['role'] ?? 'teacher'));
$username = trim($input['username'] ?? ($input['uid'] ?? ''));
$password = $input['password'] ?? '';

if ($username === '' || $password === '') {
    json_response(['success' => false, 'message' => 'Missing username or password'], 400);
}

$action = $role === 'student' ? 'student_login' : 'teacher_login';
$rl = check_rate_limit($action, 5, 15);
if ($rl['blocked']) {
    $secs = max(1, (int)$rl['retry_after']);
    json_response(['success' => false, 'message' => 'Too many failed attempts. Try again in ' . $secs . ' seconds.'], 429);
}

try {
    if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT id, uid, name, password_hash, status FROM students WHERE uid = ? LIMIT 1");
        $stmt->execute([$username]);
        $student = $stmt->fetch();

        if ($student && password_verify($password, $student['password_hash'])) {
            if ($student['status'] !== 'active') {
                json_response(['success' => false, 'message' => 'Student account is inactive.'], 403);
            }
            session_regenerate_id(true);
            $_SESSION['student_id']   = $student['id'];
            $_SESSION['student_name'] = $student['name'];
            $_SESSION['student_uid']  = $student['uid'];
            clear_rate_limit($action);
            json_response(['success' => true, 'redirect' => '../exam_session/index.php']);
        }
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, name FROM teachers WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $teacher = $stmt->fetch();

        if ($teacher && password_verify($password, $teacher['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['teacher_id']   = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'] ?? $teacher['username'];
            $_SESSION['username']     = $teacher['username'];
            clear_rate_limit($action);
            json_response(['success' => true, 'redirect' => '../dashboard/index.php']);
        }
    }

    record_failed_attempt($rl);
    json_response(['success' => false, 'message' => 'Invalid credentials.'], 401);
} catch (Throwable $e) {
    $ref = app_log('error', 'Auth API error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Server error. Ref: ' . $ref], 500);
}
