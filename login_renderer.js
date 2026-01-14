const { ipcRenderer } = require('electron');

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const errorMessage = document.getElementById('errorMessage');
    const loading = document.getElementById('loading');

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const repid = document.getElementById('repid').value.trim();
        const password = document.getElementById('password').value.trim();

        // Show loading indicator
        loading.style.display = 'block';
        errorMessage.textContent = '';

        try {
            // Send login credentials to main process for authentication
            // Using RepID as username and NIC as password
            const result = await ipcRenderer.invoke('login', repid, password);
            
            if (result.success) {
                // Login successful, navigate to main application
                console.log('Login successful:', result.user);
                ipcRenderer.invoke('login-success', result.user);
            } else {
                // Login failed, show error message
                errorMessage.textContent = result.message || 'Login failed. Please check your credentials.';
            }
        } catch (error) {
            console.error('Login error:', error);
            errorMessage.textContent = 'An error occurred during login. Please try again.';
        } finally {
            // Hide loading indicator
            loading.style.display = 'none';
        }
    });
});