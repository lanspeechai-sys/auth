
const form = document.getElementById('loginForm');
const statusBox = document.getElementById('status');
const loginBtn = document.getElementById('loginBtn');

function setError(field, message) {
  const small = document.querySelector(`small.error[data-for="${field.id}"]`);
  if (small) small.textContent = message || '';
  field.classList.toggle('invalid', !!message);
}

function validate() {
  let ok = true;
  const email = document.getElementById('email');
  const password = document.getElementById('password');
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  if (!emailPattern.test(email.value.trim()) || email.value.length > 50) {
    setError(email, 'Enter a valid email (max 50 chars).');
    ok = false;
  } else setError(email, '');

  if ((password.value || '').length < 8) {
    setError(password, 'Password must be at least 8 characters.');
    ok = false;
  } else setError(password, '');

  return ok;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  statusBox.textContent = '';
  if (!validate()) return;

  loginBtn.disabled = true;
  loginBtn.textContent = 'Logging in...';

  const payload = {
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value
  };

  try {
    const res = await fetch('/auth/actions/login_customer_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.ok) {
      window.location.href = '/auth/public/index.php';
    } else {
      statusBox.textContent = data.message || 'Login failed.';
      loginBtn.disabled = false;
      loginBtn.textContent = 'Login';
    }
  } catch (err) {
    statusBox.textContent = 'Network or server error.';
    loginBtn.disabled = false;
    loginBtn.textContent = 'Login';
  }
});
