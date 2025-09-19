// public/assets/js/register.js
const form = document.getElementById('registerForm');
const statusBox = document.getElementById('status');
const submitBtn = document.getElementById('submitBtn');

const patterns = {
  name: /^[A-Za-zÀ-ÖØ-öø-ÿ'\-\s]{2,100}$/,
  email: /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/,
  phone: /^[0-9+\-\s]{6,30}$/
};

function setError(field, message) {
  const small = document.querySelector(`small.error[data-for="${field.id}"]`);
  if (small) small.textContent = message || '';
  field.classList.toggle('invalid', !!message);
}

function validate() {
  let ok = true;

  // Full name
  const fullName = document.getElementById('full_name');
  if (!patterns.name.test(fullName.value.trim())) {
    setError(fullName, 'Please enter a valid name (2-100 letters).');
    ok = false;
  } else setError(fullName, '');

  // Email
  const email = document.getElementById('email');
  if (!patterns.email.test(email.value.trim()) || email.value.length > 150) {
    setError(email, 'Please enter a valid email.');
    ok = false;
  } else setError(email, '');

  // Password
  const password = document.getElementById('password');
  if ((password.value || '').length < 8) {
    setError(password, 'Password must be at least 8 characters.');
    ok = false;
  } else setError(password, '');

  // Country
  const country = document.getElementById('country');
  if (!country.value.trim() || country.value.length > 60) {
    setError(country, 'Country is required (max 60 chars).');
    ok = false;
  } else setError(country, '');

  // City
  const city = document.getElementById('city');
  if (!city.value.trim() || city.value.length > 60) {
    setError(city, 'City is required (max 60 chars).');
    ok = false;
  } else setError(city, '');

  // Contact Number
  const contact = document.getElementById('contact_number');
  if (!patterns.phone.test(contact.value.trim())) {
    setError(contact, 'Enter a valid phone number.');
    ok = false;
  } else setError(contact, '');

  return ok;
}

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  statusBox.textContent = '';
  if (!validate()) return;

  submitBtn.disabled = true;
  submitBtn.textContent = 'Registering...';

  const payload = {
    full_name: document.getElementById('full_name').value.trim(),
    email: document.getElementById('email').value.trim(),
    password: document.getElementById('password').value,
    country: document.getElementById('country').value.trim(),
    city: document.getElementById('city').value.trim(),
    contact_number: document.getElementById('contact_number').value.trim()
  };

  try {
    const res = await fetch('../actions/register_customer_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.ok) {
      // Success -> redirect to login page
      window.location.href = './login.php';
    } else {
      statusBox.textContent = data.message || 'Registration failed.';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Register';
    }
  } catch (err) {
    statusBox.textContent = 'Network or server error.';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Register';
  }
});
