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

    function setSeamless(input, btn) {
        if (input && btn) {
            input.style.borderRight = "none";
            
            btn.style.borderLeft = "none";
            btn.style.backgroundColor = "white";
            btn.style.color = "#6c757d"; 
            
            input.style.borderColor = "#dee2e6";
            btn.style.borderColor = "#dee2e6";
        }
    }

    setSeamless(loginInput, toggleLoginBtn);
    setSeamless(passwordInput, toggleRegisterPasswordBtn);
    setSeamless(confirmInput, toggleRegisterConfirmBtn);

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

        if (password.length > 0) {
            if (errors.length === 0) {
                passwordInput.classList.remove('is-invalid');
                passwordInput.classList.add('is-valid');
                
                passwordInput.style.borderColor = "#198754";
                passwordInput.style.borderRight = "none";
                
                if(toggleRegisterPasswordBtn) {
                    toggleRegisterPasswordBtn.style.borderColor = "#198754";
                    toggleRegisterPasswordBtn.style.borderLeft = "none";
                    toggleRegisterPasswordBtn.style.backgroundColor = "white";
                    toggleRegisterPasswordBtn.style.color = "#6c757d";
                }
                passwordFeedback.textContent = "";
            } else {
                passwordInput.classList.remove('is-valid');
                passwordInput.classList.add('is-invalid');
                
                passwordInput.style.borderColor = "#dc3545";
                passwordInput.style.borderRight = "none";
                
                if(toggleRegisterPasswordBtn) {
                    toggleRegisterPasswordBtn.style.borderColor = "#dc3545";
                    toggleRegisterPasswordBtn.style.borderLeft = "none";
                    toggleRegisterPasswordBtn.style.backgroundColor = "white";
                    toggleRegisterPasswordBtn.style.color = "#6c757d";
                }
                passwordFeedback.textContent = "Must contain at least " + errors.join(", ");
            }
        } else {
            passwordInput.classList.remove('is-valid', 'is-invalid');
            passwordInput.style.borderColor = "#dee2e6";
            passwordInput.style.borderRight = "none"; 
            
            if(toggleRegisterPasswordBtn) {
                toggleRegisterPasswordBtn.style.borderColor = "#dee2e6";
                toggleRegisterPasswordBtn.style.borderLeft = "none";
                toggleRegisterPasswordBtn.style.backgroundColor = "white";
                toggleRegisterPasswordBtn.style.color = "#6c757d";
            }
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
                
                confirmInput.style.borderColor = "#198754";
                confirmInput.style.borderRight = "none";
                
                if(toggleRegisterConfirmBtn) {
                    toggleRegisterConfirmBtn.style.borderColor = "#198754";
                    toggleRegisterConfirmBtn.style.borderLeft = "none";
                    toggleRegisterConfirmBtn.style.backgroundColor = "white";
                    toggleRegisterConfirmBtn.style.color = "#6c757d";
                }
                confirmFeedback.textContent = "";
            } else {
                confirmInput.classList.remove('is-valid');
                confirmInput.classList.add('is-invalid');
                
                confirmInput.style.borderColor = "#dc3545";
                confirmInput.style.borderRight = "none";
                
                if(toggleRegisterConfirmBtn) {
                    toggleRegisterConfirmBtn.style.borderColor = "#dc3545";
                    toggleRegisterConfirmBtn.style.borderLeft = "none";
                    toggleRegisterConfirmBtn.style.backgroundColor = "white";
                    toggleRegisterConfirmBtn.style.color = "#6c757d";
                }
                confirmFeedback.textContent = "Passwords do not match";
            }
        } else {
            confirmInput.classList.remove('is-valid', 'is-invalid');
            
            confirmInput.style.borderColor = "#dee2e6";
            confirmInput.style.borderRight = "none";
            
            if(toggleRegisterConfirmBtn) {
                toggleRegisterConfirmBtn.style.borderColor = "#dee2e6";
                toggleRegisterConfirmBtn.style.borderLeft = "none";
                toggleRegisterConfirmBtn.style.backgroundColor = "white";
                toggleRegisterConfirmBtn.style.color = "#6c757d";
            }
            confirmFeedback.textContent = "";
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
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
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

    setupPasswordToggle('toggleLoginPassword', 'loginPassword', 'iconLoginPassword');
    setupPasswordToggle('toggleRegisterPassword', 'registerPassword', 'iconRegisterPassword');
    setupPasswordToggle('toggleRegisterConfirmPassword', 'registerConfirmPassword', 'iconRegisterConfirmPassword');
});