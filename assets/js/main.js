/**
 * Main JavaScript — AI Interview Assessment Platform
 */

'use strict';

/* ── Password visibility toggle ──────────────────────────────── */
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId = btn.dataset.togglePassword;
    const input    = document.getElementById(targetId);
    if (!input) return;

    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const icon = btn.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-eye',      !isPassword);
      icon.classList.toggle('bi-eye-slash', isPassword);
    }
  });
});

/* ── Password strength meter ─────────────────────────────────── */
const pwInput    = document.getElementById('password');
const strengthFill = document.getElementById('strengthFill');
const strengthText = document.getElementById('strengthText');

if (pwInput && strengthFill) {
  pwInput.addEventListener('input', () => {
    const score = calcStrength(pwInput.value);
    const map = [
      { pct: '0%',   bg: 'transparent',         label: '' },
      { pct: '25%',  bg: '#DC2626',             label: 'Weak' },
      { pct: '50%',  bg: '#D97706',             label: 'Fair' },
      { pct: '75%',  bg: '#2563EB',             label: 'Good' },
      { pct: '100%', bg: '#16A34A',             label: 'Strong' },
    ];
    const s = map[score];
    strengthFill.style.width      = s.pct;
    strengthFill.style.background = s.bg;
    if (strengthText) strengthText.textContent = s.label;
  });
}

function calcStrength(pw) {
  if (!pw) return 0;
  let score = 0;
  if (pw.length >= 8)             score++;
  if (/[A-Z]/.test(pw))          score++;
  if (/[0-9]/.test(pw))          score++;
  if (/[^A-Za-z0-9]/.test(pw))   score++;
  return score;
}

/* ── Auto-dismiss alerts after 5 seconds ────────────────────── */
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
    bsAlert.close();
  }, 5000);
});

/* ── Client-side form validation feedback ────────────────────── */
document.querySelectorAll('form[data-validate]').forEach(form => {
  form.addEventListener('submit', e => {
    if (!form.checkValidity()) {
      e.preventDefault();
      e.stopPropagation();
    }
    form.classList.add('was-validated');
  });
});

/* ── Confirm password match check ────────────────────────────── */
const confirmInput = document.getElementById('confirm_password');
if (confirmInput && pwInput) {
  confirmInput.addEventListener('input', () => {
    if (confirmInput.value && confirmInput.value !== pwInput.value) {
      confirmInput.setCustomValidity('Passwords do not match.');
    } else {
      confirmInput.setCustomValidity('');
    }
  });
  pwInput.addEventListener('input', () => {
    if (confirmInput.value) {
      confirmInput.dispatchEvent(new Event('input'));
    }
  });
}

/* ── Prevent double-submit ───────────────────────────────────── */
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', () => {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      const original = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Please wait…';
      // Re-enable after 8 s as safety net
      setTimeout(() => { btn.disabled = false; btn.innerHTML = original; }, 8000);
    }
  });
});
