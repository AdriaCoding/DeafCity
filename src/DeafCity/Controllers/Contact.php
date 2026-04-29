<?php

namespace DeafCity\Controllers;

use \DeafCity\BaseController;

class Contact extends BaseController { 

    public function index() {
        header('Location: ./', true, 302);
    }

    public function submit() {
        session_start();
        $errors = [];
        
        if (empty($_SESSION['security_token'])) {
            $errors['security_token'] = "Security token was not set.";
        }
        else if(empty($_POST['st']) || $_SESSION['security_token']!=$_POST['st']) {
            $errors['security_token'] = "Invalid security token.";
        }
        
        if (empty($_POST['email'])) {
            $errors['email'] = "Email is required.";
        }
        else if (!strstr($_POST['email'], '@')) {
            $errors['email'] = "Invalid email.";
        }
        
        if (empty($_POST['name'])) {
            $errors['name'] = "Name is required.";
        }
        
        if (empty($_POST['message'])) {
            $errors['message'] = "Message is required.";
        }
        
        if (empty($_POST['grecaptcha-token'])) {
            $errors['grecaptcha-token'] = 'Recaptcha token missing.';
        }
        else {
            if (!$this->verifyRecaptchaToken($_POST['grecaptcha-token'])) {
                $errors['grecaptcha-token'] = 'Recaptcha validation failed.';
            }
        }
        
        $message = <<<HERE
Name: {$_POST['name']}
Email: {$_POST['email']}
Message:
{$_POST['message']}
HERE;
        
        
        if (empty($errors)) {
            $ok = mail(CONTACT_EMAIL, "Deaf.City contact form", $message, 'Reply-To: '. $_POST['name']. '<'.$_POST['email'].'>');
            if (!$ok) $errors['mail'] = 'Falied to send email.';
        }
        
        $response = [
            'data' => $_POST
        ];
        
        if (empty($errors)) {
            $response['ok'] = 1;
            
        }
        else {
            $response['error'] = 'ERROR: '.implode(" ", $errors);
            header("HTTP/1.0 400 Bad Request");
        }
        header("Content-Type: application/json");
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
    
    protected function verifyRecaptchaToken($token) {
        $ch = curl_init();

        curl_setopt($ch,CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query([
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $token,
        ]));
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

        //execute post
        $result = curl_exec($ch);
        
        if (!$result) return false;
        $result = @json_decode($result, true);
        if (!$result) return false;
        if (!empty($result['success'])) return true;
        
        return false;
    }
}
