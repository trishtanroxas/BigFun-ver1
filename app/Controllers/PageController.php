<?php

namespace App\Controllers;

use App\Core\Controller;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class PageController extends Controller {
    public function home(): void {
        $this->view('general/home');
    }

    public function about(): void {
        $this->view('general/about');
    }

    public function contact(): void {
        $this->view('general/contact');
    }

    public function services(): void {
        $this->view('general/services');
    }

    public function sendMessage(): void {
        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $from = $_POST['from_email'] ?? '';
            $to = $_POST['to_email'] ?? '';
            $subject = $_POST['subject'] ?? '';
            $body = $_POST['body'] ?? '';

            $mail = new PHPMailer(true);

            try {
                // SMTP settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'alex1925tan@gmail.com'; 
                $mail->Password   = 'REDACTED_SMTP_PASSWORD';   
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                // Main email → Admin
                $mail->setFrom($from, 'Website User');
                $mail->addAddress($to);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = nl2br($body);

                $mail->send();

                // Send confirmation → User
                $mail->clearAddresses();
                $mail->addAddress($from);
                $mail->Subject = "Copy of your message: " . $subject;
                $mail->Body    = "Thank you for contacting us!<br><br><strong>Your message:</strong><br>" . nl2br($body);
                $mail->send();

                header("Location: index.php?sent=1");
                exit();
            } catch (Exception $e) {
                echo "Mailer Error: " . $mail->ErrorInfo;
            }
        }
    }
}
