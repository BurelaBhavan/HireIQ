<?php
/**
 * Login Page
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

// Redirect authenticated users straight to their dashboard
redirectIfLoggedIn();

$errors   = [];
$oldEmail = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    $oldEmail = $email;

    // Basic validation
    if (!isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        $user = loginUser($email, $password);

        if ($user) {
            $map = [
                'super_admin' => BASE_URL . '/admin/dashboard.php',
                'candidate'   => BASE_URL . '/candidate/dashboard.php',
            ];
            redirect($map[$user['role']] ?? BASE_URL . '/');
        } else {
            $errors[] = 'Invalid email or password. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/layout.php';
renderHeader('Sign In');
?>

<div class="auth-page">

  <!-- ── Left decorative panel ── -->
  <aside class="auth-panel">
    <div>
      <div class="brand-logo mb-4">
        Hire<span>IQ</span>
      </div>
      <p class="auth-panel__headline">Smarter Hiring Starts Here</p>
      <p class="auth-panel__sub">
        AI-powered video interviews, automated scoring, and real-time analytics — all in one enterprise platform.
      </p>

      <div class="auth-panel__stats">
        <div>
          <div class="auth-panel__stat-value">2.4M+</div>
          <div class="auth-panel__stat-label">Interviews conducted</div>
        </div>
        <div>
          <div class="auth-panel__stat-value">98%</div>
          <div class="auth-panel__stat-label">Recruiter satisfaction</div>
        </div>
      </div>
    </div>

    <ul class="auth-panel__badge-list list-unstyled">
      <li class="auth-panel__badge">
        <i class="bi bi-shield-check"></i>
        SOC 2 Type II Certified
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-globe2"></i>
        Available in 45+ languages
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-lightning-charge"></i>
        AI-powered proctoring
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-people"></i>
        Trusted by 500+ enterprises
      </li>
    </ul>
  </aside>

  <!-- ── Right form panel ── -->
  <main class="auth-form-wrap">
    <div class="auth-card">

      <h1 class="auth-card__title">Welcome back</h1>
      <p class="auth-card__subtitle">Sign in to your HireIQ account</p>

      <!-- Flash messages -->
      <?php renderAlert(getFlash('login')); ?>

      <!-- Inline error summary -->
      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
          <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
          <ul class="mb-0 ps-2">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Login form -->
      <form method="POST" action="<?= BASE_URL ?>/login.php" data-validate novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />

        <!-- Email -->
        <div class="mb-3">
          <label for="email" class="form-label">Email address</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="you@company.com"
            value="<?= e($oldEmail) ?>"
            required
            autocomplete="email"
          />
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center">
            <label for="password" class="form-label mb-0">Password</label>
            <a href="<?= BASE_URL ?>/forgot-password.php" class="text-primary-custom" style="font-size:.8125rem;">
              Forgot password?
            </a>
          </div>
          <div class="input-group mt-1">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Enter your password"
              required
              autocomplete="current-password"
            />
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-toggle-password="password"
              aria-label="Toggle password visibility"
            >
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div class="invalid-feedback">Password is required.</div>
        </div>

        <!-- Remember me -->
        <div class="mb-4 form-check">
          <input type="checkbox" class="form-check-input" id="remember" name="remember" />
          <label class="form-check-label" for="remember" style="font-size:.875rem;color:var(--color-text-secondary);">
            Keep me signed in
          </label>
        </div>

        <button type="submit" class="btn-primary-custom">
          <i class="bi bi-box-arrow-in-right"></i>
          Sign In
        </button>
      </form>

      <div class="form-divider">or</div>

      <p class="text-center mb-0" style="font-size:.9rem;color:var(--color-text-secondary);">
        Don't have an account?
        <a href="<?= BASE_URL ?>/register.php" class="fw-600 text-primary-custom ms-1">Create account</a>
      </p>

    </div>
  </main>

</div>

<?php renderFooter(); ?>
