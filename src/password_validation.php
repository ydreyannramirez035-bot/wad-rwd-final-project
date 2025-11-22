<?php

class PasswordValidator {
    public static function validate($password) {

        if (strlen($password) < 8) {
            return "Password must be at least 8 characters long.";
        }

        if (!preg_match('@[A-Z]@', $password)) {
            return "Password must include at least one uppercase letter.";
        }

        if (!preg_match('@[a-z]@', $password)) {
            return "Password must include at least one lowercase letter.";
        }

        if (!preg_match('@[0-9]@', $password)) {
            return "Password must include at least one number.";
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return "Password must include at least one special character.";
        }

        return true; // passed all checks
    }
}
