    <?php
    session_start();

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    include "db.php";

    function getUserIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        $stmt = $conn->prepare("SELECT id, email, password, is_verified FROM signup WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        $response = [];

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {
                if ($user['is_verified'] == 1) {
                    // Start session
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['logged_in'] = true;

                    // Log login history
                    $ip = getUserIP();
                    $login_time = date("Y-m-d H:i:s");
                    $insert = $conn->prepare("INSERT INTO login_history (user_id, login_time, ip_address) VALUES (?, ?, ?)");
                    $insert->bind_param("iss", $user['id'], $login_time, $ip);
                    $insert->execute();

                    // store login_history id in session for logout
                    $_SESSION['login_history_id'] = $insert->insert_id;

                    $insert->close();

                    // Redirect to profile management page
                    $response = ["status" => "success", "redirect" => "../profile-manage.php"];
                } else {
                    $response = ["status" => "error", "message" => "Please verify your email before logging in."];
                }
            } else {
                $response = ["status" => "error", "message" => "Invalid password."];
            }
        } else {
            $response = ["status" => "error", "message" => "No account found with that email."];
        }

        $stmt->close();

        header("Content-Type: application/json");
        echo json_encode($response);
        exit();
    }
    ?>
