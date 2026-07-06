<?php
/**
 * Forgot Password Page
 * AI Interview Assessment Platform
 *
 * Phase 1: Creates a reset token and shows it (no mailer yet).
 * Phase 2: Wire up to SMTP/SendGrid.
 */

declare(strict_types=1);
require_once __DIR__ . '/config/app.php';

redirectIfLoggedIn();

$errors  = [];
$success = false;
$oldEmail = '';

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email    = trim($_POST['email'] ?? '');
    $oldEmail = $email;

    if (!isValidEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        // We always show the same success message to prevent email enumeration
        createPasswordResetToken($email);
        $success = true;
    }
}

require_once __DIR__ . '/includes/layout.php';
renderHeader('Forgot Password');
?>

<div class="auth-page">

  <!-- ── Left decorative panel ── -->
  <aside class="auth-panel">
    <div>
      <div class="brand-logo mb-4">Hire<span>IQ</span></div>
      <p class="auth-panel__headline">Account Recovery</p>
      <p class="auth-panel__sub">
        We'll send a secure link to your registered email address so you can reset your password quickly.
      </p>
    </div>

    <ul class="auth-panel__badge-list list-unstyled">
      <li class="auth-panel__badge">
        <i class="bi bi-envelope-check"></i>
        Secure reset link via email
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-hourglass-split"></i>
        Link expires in 1 hour
      </li>
      <li class="auth-panel__badge">
        <i class="bi bi-shield-lock"></i>
        Token invalidated after use
      </li>
    </ul>
  </aside>

  <!-- ── Right form panel ── -->
  <main class="auth-form-wrap">
    <div class="auth-card">

      <?php if ($success): ?>
        <!-- Success state -->
        <div class="text-center py-4">
          <div style="
            width:72px; height:72px; border-radius:50%;
            background:var(--color-primary-light);
            display:flex; align-items:center; justify-content:center;
            margin:0 auto 1.25rem;
          ">
            <i class="bi bi-envelope-check" style="font-size:2rem;color:var(--color-primary);"></i>
          </div>
          <h1 class="auth-card__title">Check your inbox</h1>
          <p class="auth-card__subtitle">
            If an account exists for <strong><?= e($oldEmail) ?></strong>, you'll receive a password reset link shortly.
          </p>
          <p class="text-muted" style="font-size:.85rem;">
            Didn't receive it? Check your spam folder or
            <a href="<?= BASE_URL ?>/forgot-password.php" class="text-primary-custom">try again</a>.
          </p>
          <a
            href="<?= BASE_URL ?>/login.php"
            class="btn-primary-custom mt-4"
            style="display:inline-flex; width:auto; padding:.6875rem 1.75rem;"
          >
            <i class="bi bi-arrow-left"></i>
            Back to Sign In
          </a>
        </div>

      <?php else: ?>
        <!-- Form state -->
        <a href="<?= BASE_URL ?>/login.php"
           class="d-inline-flex align-items-center gap-1 mb-4"
           style="font-size:.875rem;color:var(--color-text-secondary);"
        >
          <i class="bi bi-arrow-left"></i> Back to sign in
        </a>

        <h1 class="auth-card__title">Forgot your password?</h1>
        <p class="auth-card__subtitle">
          Enter your email address and we'll send you a secure link to reset your password.
        </p>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger d-flex align-items-center gap-2" role="alert">
            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
            <span><?= e($errors[0]) ?></span>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/forgot-password.php" data-validate novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />

          <div class="mb-4">
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

          <button type="submit" class="btn-primary-custom">
            <i class="bi bi-send"></i>
            Send Reset Link
          </button>
        </form>
      <?php endif; ?>

    </div>
  </main>

</div>

<?php renderFooter(); ?>
