document.addEventListener('DOMContentLoaded', function() {

    const usernameInput = document.getElementById('registerUsername');
    const emailInput = document.getElementById('registerEmail');
    const passwordInput = document.getElementById('registerPassword');
    const confirmInput = document.getElementById('registerConfirmPassword');
    
    const loginInput = document.getElementById('loginPassword');
    const toggleLoginBtn = document.getElementById('toggleLoginPassword');

    const toggleRegisterPasswordBtn = document.getElementById('toggleRegisterPassword');
    const toggleRegisterConfirmBtn = document.getElementById('toggleRegisterConfirmPassword');

    const passwordFeedback = document.getElementById('passwordFeedback');
    const confirmFeedback = document.getElementById('confirmFeedback');

    function updateFieldState(input, btn, borderColor) {
        if (!input || !btn) return;

        if (input.value.length === 0) {
            btn.style.display = 'none';
            
            input.style.borderRadius = '0.5rem';
            input.style.borderRight = ''; 
            input.style.borderColor = borderColor;
            
        } else {
            btn.style.display = 'block';
            
            input.style.borderTopRightRadius = '0';
            input.style.borderBottomRightRadius = '0';
            input.style.borderRight = 'none';
            input.style.borderColor = borderColor;

            btn.style.borderTopLeftRadius = '0';
            btn.style.borderBottomLeftRadius = '0';
            btn.style.borderLeft = 'none';
            btn.style.borderColor = borderColor;
            
            btn.style.backgroundColor = "white";
            btn.style.color = "#6c757d"; 
        }
    }

    function setupPasswordToggle(toggleBtnId, inputId, iconId) {
        const toggleBtn = document.getElementById(toggleBtnId);
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);

        if (toggleBtn && input && icon) {
            toggleBtn.addEventListener('click', function() {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);

                if (type === 'text') {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                } else {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            });
        }
    }

    function checkUsername() {
        const value = usernameInput.value.trim();
        
        if (value.length > 0) {
            usernameInput.classList.remove('is-invalid');
            usernameInput.classList.add('is-valid');
        } else {
            usernameInput.classList.remove('is-valid');
            usernameInput.classList.remove('is-invalid');
        }
    }

    function checkEmail() {
        const value = emailInput.value.trim();
        const emailRules = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (value.length === 0) {
            emailInput.classList.remove('is-valid');
            emailInput.classList.remove('is-invalid');
        } else if (emailRules.test(value)) {
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

        if (password.length < 12) errors.push("12+ characters");
        if (!/[A-Z]/.test(password)) errors.push("1 uppercase letter");
        if (!/[a-z]/.test(password)) errors.push("1 lowercase letter");
        if (!/[0-9]/.test(password)) errors.push("1 number");

        if (password.length > 0) {
            if (errors.length === 0) {
                passwordInput.classList.remove('is-invalid');
                passwordInput.classList.add('is-valid');
                updateFieldState(passwordInput, toggleRegisterPasswordBtn, "#198754");
                passwordFeedback.textContent = "";
            } else {
                passwordInput.classList.remove('is-valid');
                passwordInput.classList.add('is-invalid');
                updateFieldState(passwordInput, toggleRegisterPasswordBtn, "#dc3545");
                passwordFeedback.textContent = "Must contain at least " + errors.join(", ");
            }
        } else {
            passwordInput.classList.remove('is-valid', 'is-invalid');
            updateFieldState(passwordInput, toggleRegisterPasswordBtn, "#dee2e6");
            passwordFeedback.textContent = "";
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
                updateFieldState(confirmInput, toggleRegisterConfirmBtn, "#198754");
                confirmFeedback.textContent = "";
            } else {
                confirmInput.classList.remove('is-valid');
                confirmInput.classList.add('is-invalid');
                updateFieldState(confirmInput, toggleRegisterConfirmBtn, "#dc3545");
                confirmFeedback.textContent = "Passwords do not match";
            }
        } else {
            confirmInput.classList.remove('is-valid', 'is-invalid');
            updateFieldState(confirmInput, toggleRegisterConfirmBtn, "#dee2e6");
            confirmFeedback.textContent = "";
        }
    }

    function checkLoginPassword() {
        updateFieldState(loginInput, toggleLoginBtn, "#dee2e6");
    }

    if (usernameInput) usernameInput.addEventListener('input', checkUsername);
    if (emailInput) emailInput.addEventListener('input', checkEmail);
    if (passwordInput) passwordInput.addEventListener('input', checkPassword);
    if (confirmInput) confirmInput.addEventListener('input', checkConfirmPassword);
    
    if (loginInput) {
        loginInput.addEventListener('input', checkLoginPassword);
        checkLoginPassword();
    }

    if (passwordInput) checkPassword();
    if (confirmInput) checkConfirmPassword();

    setupPasswordToggle('toggleLoginPassword', 'loginPassword', 'iconLoginPassword');
    setupPasswordToggle('toggleRegisterPassword', 'registerPassword', 'iconRegisterPassword');
    setupPasswordToggle('toggleRegisterConfirmPassword', 'registerConfirmPassword', 'iconRegisterConfirmPassword');
});