document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const userId = document.getElementById('ID').value;
    const password = document.getElementById('password').value;
    const errorDiv = document.getElementById('loginError');

    // Clear previous error
    errorDiv.classList.add('d-none');
    errorDiv.textContent = '';

    try {
        const response = await fetch('login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `ID=${encodeURIComponent(userId)}&password=${encodeURIComponent(password)}`,
        });

        const data = await response.json();

        if (data.success) {
            // Redirect to staff.php
            window.location.href = "staff.php";
        } else {
            // Show error message
            errorDiv.textContent = data.error || 'An error occurred';
            errorDiv.classList.remove('d-none');
        }
    } catch (error) {
        errorDiv.textContent = 'Network error: Unable to connect to the server';
        errorDiv.classList.remove('d-none');
    }
});

// Password toggle functionality (already in your HTML, but included here for completeness)
document.querySelector('.password__icon').addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordInput.setAttribute('type', type);
    this.classList.toggle('bi-eye');
    this.classList.toggle('bi-eye-slash');
});