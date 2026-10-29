<?php
/**
 * Staff & Coordinator Login View
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

// Redirect if already logged in
if (Auth::check()) {
    $redirect = match (Auth::role()) {
        'Administrator' => 'views/admin/dashboard.php',
        'Relief Coordinator' => 'views/coordinator/dashboard.php',
        default => 'views/citizen/dashboard.php'
    };
    Helper::redirect($redirect);
}

$error = null;
$redirectParam = $_GET['redirect'] ?? '';

// Handle Direct POST Login Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please provide both email and password.';
    } else {
        if (Auth::login($email, $password)) {
            $user = Auth::user();
            Helper::setFlash('success', "Welcome back, {$user['full_name']}!");

            if (!empty($redirectParam)) {
                header("Location: " . urldecode($redirectParam));
                exit;
            }

            $target = match ($user['role_name']) {
                'Administrator' => 'views/admin/dashboard.php',
                'Relief Coordinator' => 'views/coordinator/dashboard.php',
                default => 'views/citizen/dashboard.php'
            };
            Helper::redirect($target);
        } else {
            $error = 'Invalid email address or password. Please check credentials.';
        }
    }
}

$pageTitle = "Staff Login";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow border-0 rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white text-center py-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-25 text-info mb-2">
                        <i class="bi bi-shield-lock-fill fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Official Portal Login</h4>
                    <p class="text-white-50 small mb-0">Disaster Coordinators &amp; Administrators</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= Helper::e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="loginForm">
                        <?= CSRF::inputField() ?>

                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold">Official Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="name@floodrelief.org" required autofocus 
                                       value="<?= Helper::e($_POST['email'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Enter password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid gap-2 mb-4">
                            <button type="submit" class="btn btn-primary py-2 fw-semibold">
                                <i class="bi bi-box-arrow-in-right me-2"></i> Sign In to Portal
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <span class="text-muted small">Are you a citizen?</span>
                        <a href="<?= BASE_URL ?>/views/auth/register.php" class="small fw-bold text-decoration-none text-primary ms-1">Create Free Citizen Account &rarr;</a>
                    </div>
                </div>

                <div class="card-footer bg-light text-center py-3">
                    <a href="<?= BASE_URL ?>/views/citizen/index.php" class="text-decoration-none small text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Return to Public Flood Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function() {
    const pwdInput = document.getElementById('password');
    const icon = this.querySelector('i');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        pwdInput.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>