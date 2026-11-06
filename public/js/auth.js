const toggle = document.getElementById('password-visibility');
toggle?.addEventListener('change', () => {
    document.querySelectorAll('input[type="password"], input[data-pw]').forEach((input) => {
        input.dataset.pw = '1';
        input.type = toggle.checked ? 'text' : 'password';
    });
});
