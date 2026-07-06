<?php
/**
 * Candidate Registration Page
 * AI Interview Assessment Platform
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

redirectIfLoggedIn();

$errors  = [];
$oldData = ['full_name' => '', 'email' => ''];

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName        = trim($_POST['full_name']        ?? '');
    $email           = trim($_POST['email']            ?? '');
    $password        = $_POST['password']              ?? '';
    $confirmPassword = $_POST['confirm_password']      ?? '';

    $oldData['full_name'] = $fullName;
    $oldData['email']     = $email;

    // Validation
    if (empty($fullName) || strlen($fullName) < 2) {
        $errors[] = 'Full name must be at least 2 characters.';
    }
    if (!isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $result = registerCandidate($fullName, $email, $password);

        if ($result === true) {
            flash('login', 'Account created successfully! Please sign in.', 'success');
            redirect(BASE_URL . '/login.php');
        } else {
            $errors[] = $result;   // Error message string returned from registerCandidate()
        }
    }
}

require_once __DIR__ . '/includes/layout.php';
renderHeader('Create Account');
?>

<div class="auth-page">

  <!-- ── Left decorative panel ── -->
  <aside class="auth-panel">
    <div>
      <div class="brand-logo mb-4">Hire<span>IQ</span></div>
      <p class="auth-panel__headline">Join Thousands of Top Candidates</p>
      <p class="auth-panel__sub">
        Complete AI-driven assessments from anywhere. Showcase your skills to the world's leading companies.
      </p>

      <div class="auth-panel__stats">
        <div>
          <div class="auth-panel__stat-value">180K+</div>
          <div class="auth-panel__stat-label">Active candidates</div>
        </div>
        <div>
          <div class="auth-panel__stat-value">3x</div>
          <div class="auth-panel__stat-label">Faster hiring</div>
        </div>
      </div>
    </div>

    <ul class="auth-panel__badge-list list-unstyled">
      <li class="auth-panel__badge">
        <i class="bi bi-camera-video"></i>
        On-demand video interviews
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-bar-chart-line"></i>
        AI-graded assessments
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-clock-history"></i>
        Complete at your own pace
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-patch-check"></i>
        Instant score feedback
      </li>
    </ul>
  </aside>

  <!-- ── Right form panel ── -->
  <main class="auth-form-wrap">
    <div class="auth-card">

      <h1 class="auth-card__title">Create your account</h1>
      <p class="auth-card__subtitle">Start your assessment journey today — it's free</p>

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

      <!-- Registration form -->
      <form method="POST" action="<?= BASE_URL ?>/register.php" data-validate novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />

        <!-- Full name -->
        <div class="mb-3">
          <label for="full_name" class="form-label">Full name</label>
          <input
            type="text"
            id="full_name"
            name="full_name"
            class="form-control"
            placeholder="Jane Doe"
            value="<?= e($oldData['full_name']) ?>"
            required
            minlength="2"
            autocomplete="name"
          />
          <div class="invalid-feedback">Please enter your full name.</div>
        </div>

        <!-- Email -->
        <div class="mb-3">
          <label for="email" class="form-label">Email address</label>
          <input
            type="email"
            id="email"
            name="email"
            class="form-control"
            placeholder="you@email.com"
            value="<?= e($oldData['email']) ?>"
            required
            autocomplete="email"
          />
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <label for="password" class="form-label">Password</label>
          <div class="input-group">
            <input
              type="password"
              id="password"
              name="password"
              class="form-control"
              placeholder="Minimum 8 characters"
              required
              minlength="8"
              autocomplete="new-password"
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
          <!-- Strength meter -->
          <div class="strength-bar mt-2">
            <div class="strength-bar__fill" id="strengthFill" style="width:0%"></div>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-1">
            <span style="font-size:.75rem;color:var(--color-text-muted);">Password strength</span>
            <span id="strengthText" style="font-size:.75rem;color:var(--color-text-secondary);font-weight:500;"></span>
          </div>
          <div class="invalid-feedback">Password must be at least 8 characters.</div>
        </div>

        <!-- Confirm password -->
        <div class="mb-4">
          <label for="confirm_password" class="form-label">Confirm password</label>
          <div class="input-group">
            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              class="form-control"
              placeholder="Re-enter your password"
              required
              autocomplete="new-password"
            />
            <button
              type="button"
              class="btn btn-outline-secondary"
              data-toggle-password="confirm_password"
              aria-label="Toggle confirm password visibility"
            >
              <i class="bi bi-eye-slash"></i>
            </button>
          </div>
          <div class="invalid-feedback">Passwords do not match.</div>
        </div>

        <!-- Terms -->
        <div class="mb-4 form-check">
          <input type="checkbox" class="form-check-input" id="terms" name="terms" required />
          <label class="form-check-label" for="terms" style="font-size:.875rem;color:var(--color-text-secondary);">
            I agree to the
            <a href="#" class="text-primary-custom">Terms of Service</a> and
            <a href="#" class="text-primary-custom">Privacy Policy</a>
          </label>
          <div class="invalid-feedback">You must accept the terms to continue.</div>
        </div>

        <button type="submit" class="btn-primary-custom">
          <i class="bi bi-person-plus"></i>
          Create Account
        </button>
      </form>

      <div class="form-divider">already have an account?</div>

      <p class="text-center mb-0" style="font-size:.9rem;color:var(--color-text-secondary);">
        <a href="<?= BASE_URL ?>/login.php" class="fw-600 text-primary-custom">Sign in instead</a>
      </p>

    </div>
  </main>

</div>

<?php renderFooter(); ?>
