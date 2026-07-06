<?php
/**
 * Reusable HTML Header Component
 * AI Interview Assessment Platform
 *
 * @param string $title      Browser tab title
 * @param string $bodyClass  Extra CSS classes for <body>
 */
function renderHeader(string $title = 'HireIQ', string $bodyClass = ''): void
{ ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="HireIQ — Enterprise AI-powered interview assessment platform." />
  <title><?= e($title) ?> | HireIQ</title>

  <!-- Bootstrap 5.3 -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
    crossorigin="anonymous"
  />

  <!-- Bootstrap Icons -->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
  />

  <!-- Google Fonts: Inter + Space Grotesk -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />

  <!-- Custom stylesheet -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css" />
</head>
<body class="<?= e($bodyClass) ?>">
<?php }

/**
 * Reusable HTML Footer Component
 */
function renderFooter(): void
{ ?>
<!-- Bootstrap Bundle JS -->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
  integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
  crossorigin="anonymous"
></script>
<!-- Custom JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
<?php }

/**
 * Render an alert div from a flash message array.
 *
 * @param array{message: string, type: string}|null $flash
 */
function renderAlert(?array $flash): void
{
    if (!$flash) {
        return;
    }

    $map = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ];

    $cls = $map[$flash['type']] ?? 'alert-info';
    $icon = match ($flash['type']) {
        'success' => 'bi-check-circle-fill',
        'error'   => 'bi-exclamation-triangle-fill',
        'warning' => 'bi-exclamation-circle-fill',
        default   => 'bi-info-circle-fill',
    };
    ?>
    <div class="alert <?= $cls ?> alert-dismissible d-flex align-items-center gap-2 fade show" role="alert">
      <i class="bi <?= $icon ?>"></i>
      <span><?= e($flash['message']) ?></span>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php
}
