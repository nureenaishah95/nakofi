document.addEventListener('DOMContentLoaded', function () {
    // Password toggle functionality
    const passwordIcon = document.querySelector('.password__icon');
    const passwordInput = document.getElementById('password');
    
    if (passwordIcon && passwordInput) {
        passwordIcon.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    } else {
        console.error('Password icon or input not found');
    }

    // Login form submission
    const loginForm = document.getElementById('loginForm');
    const loginError = document.getElementById('loginError');

    if (loginForm && loginError) {
        loginForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const userId = document.getElementById('user_id').value;
            const password = document.getElementById('password').value;

            if (!userId || !password) {
                loginError.textContent = 'Please enter both ID and Password';
                loginError.classList.remove('d-none');
                return;
            }

            try {
                const response = await fetch('m.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${encodeURIComponent(userId)}&password=${encodeURIComponent(password)}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Login response:', result);

                if (result.success) {
                    switch (result.role) {
                        case 1:
                            window.location.href = 'staff.php';
                            break;
                        case 2:
                            window.location.href = 'staff_leader.php';
                            break;
                        case 3:
                            window.location.href = 'supplier.php';
                            break;
                        default:
                            loginError.textContent = `Invalid role assigned: ${result.role}`;
                            loginError.classList.remove('d-none');
                    }
                } else {
                    loginError.textContent = result.message || 'Invalid ID or Password';
                    loginError.classList.remove('d-none');
                }
            } catch (error) {
                loginError.textContent = 'An error occurred. Please try again.';
                loginError.classList.remove('d-none');
                console.error('Login fetch error:', error);
            }
        });
    } else {
        console.error('Login form or error element not found');
    }

    // Supervisor modal trigger for Register link
    const signUpLink = document.getElementById('signUp');
    if (signUpLink) {
        signUpLink.addEventListener('click', function (e) {
            e.preventDefault();
            console.log('Register link clicked'); // Debug
            try {
                const modal = new bootstrap.Modal(document.getElementById('supervisorModal'));
                modal.show();
            } catch (error) {
                console.error('Modal trigger error:', error);
            }
        });
    } else {
        console.error('SignUp link not found');
    }

    // Supervisor form submission
    const supervisorForm = document.getElementById('supervisorForm');
    const svError = document.getElementById('svError');

    if (supervisorForm && svError) {
        supervisorForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const svId = document.getElementById('svId').value;
            const svPassword = document.getElementById('svPassword').value;

            if (!svId || !svPassword) {
                svError.textContent = 'Please enter both Staff Leader ID and Password';
                svError.classList.remove('d-none');
                return;
            }

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `svId=${encodeURIComponent(svId)}&svPassword=${encodeURIComponent(svPassword)}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                console.log('Supervisor response:', result);

                if (result.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('supervisorModal'));
                    modal.hide();
                    window.location.href = 'register.php';
                } else {
                    svError.textContent = result.message || 'Invalid Staff Leader ID or Password';
                    svError.classList.remove('d-none');
                }
            } catch (error) {
                svError.textContent = 'An error occurred. Please try again.';
                svError.classList.remove('d-none');
                console.error('Supervisor fetch error:', error);
            }
        });
    } else {
        console.error('Supervisor form or error element not found');
    }
});