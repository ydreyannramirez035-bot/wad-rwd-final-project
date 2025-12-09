document.addEventListener('DOMContentLoaded', function() {

    const usernameInput = document.getElementById('registerUsername');
    const emailInput = document.getElementById('registerEmail');
    const passwordInput = document.getElementById('registerPassword');
    const confirmInput = document.getElementById('registerConfirmPassword');

    const passwordFeedback = document.getElementById('passwordFeedback');
    const confirmFeedback = document.getElementById('confirmFeedback');

    function checkUsername() {
        const value = usernameInput.value.trim();

        if (value.length > 0) {
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
        } else {
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.add('is-invalid');
        }
    }

    function checkEmail() {
        const value = emailInput.value.trim();
        const emailRules = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (emailRules.test(value)) {
            emailInput.classList.remove('is-invalid');
            emailInput.classList.add('is-valid');
        } else {
            emailInput.classList.remove('is-valid');
            emailInput.classList.add('is-invalid');
        }
    }

    function checkPassword() {
        const password = passwordInput.value;
        const errors = [];

        if (password.length < 12) {
            errors.push("12+ characters");
        }
        if (!/[A-Z]/.test(password)) {
            errors.push("1 uppercase letter");
        }
        if (!/[a-z]/.test(password)) {
            errors.push("1 lowercase letter");
        }
        if (!/[0-9]/.test(password)) {
            errors.push("1 number");
        }

        if (errors.length === 0) {
            passwordInput.classList.remove('is-invalid');
            passwordInput.classList.add('is-valid');
            passwordInput.style.borderColor = "#198754";
            passwordFeedback.textContent = "";
        } else {
            passwordInput.classList.remove('is-valid');
            passwordInput.classList.add('is-invalid');
            
            if (password.length > 0) {
                passwordInput.style.borderColor = "#dc3545";
                passwordFeedback.textContent = "Must contain at least " + errors.join(", ");
            } else {
                passwordInput.style.borderColor = "";
                passwordFeedback.textContent = "";
            }
        }

        if (confirmInput.value.length > 0) {
            checkConfirmPassword();
        }
    }

    function checkConfirmPassword() {
        const password = passwordInput.value;
        const confirm = confirmInput.value;

        if (confirm.length > 0) {
            if (password === confirm) {
                confirmInput.classList.remove('is-invalid');
                confirmInput.classList.add('is-valid');
                confirmInput.style.borderColor = "#198754";
                confirmFeedback.textContent = "";
            } else {
                confirmInput.classList.remove('is-valid');
                confirmInput.classList.add('is-invalid');
                confirmInput.style.borderColor = "#dc3545";
                confirmFeedback.textContent = "Passwords do not match";
            }
        } else {
            confirmInput.classList.remove('is-valid', 'is-invalid');
            confirmInput.style.borderColor = "";
            confirmFeedback.textContent = "";
        }
    }
    
    if (usernameInput) {
        usernameInput.addEventListener('input', checkUsername);
    }
    
    if (emailInput) {
        emailInput.addEventListener('input', checkEmail);
    }
    
    if (passwordInput) {
        passwordInput.addEventListener('input', checkPassword);
    }
    
    if (confirmInput) {
        confirmInput.addEventListener('input', checkConfirmPassword);
    }

});