<?php
/**
 * Administrator User & Staff Accounts Management
 * Satisfies PRD Section 3 (Users and Roles)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();

// Handle User Creation / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();
    $userId = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    $roleId = (int)$_POST['role_id'];
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $password = $_POST['password'] ?? '';

    $userData = [
        'role_id' => $roleId,
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'status' => $status
    ];

    if (!empty($password)) {
        $userData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
    }

    if ($userId) {
        $db->update('users', $userData, 'id = :id', [':id' => $userId]);
        Helper::setFlash('success', "User account '{$fullName}' updated.");
    } else {
        if (empty($password)) {
            $userData['password_hash'] = password_hash('Coord@1234', PASSWORD_BCRYPT);
        }
        $userId = $db->insert('users', $userData);
        Helper::setFlash('success', "New user account '{$fullName}' created.");
    }

    $db->insert('audit_logs', [
        'user_id' => Auth::id(),
        'action_type' => 'USER_ACCOUNT_SAVE',
        'table_affected' => 'users',
        'record_id' => $userId,
        'details' => "Saved user {$fullName} ({$email}) with role ID {$roleId}",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    Helper::redirect('views/admin/users.php');
}

$users = $db->fetchAll("
    SELECT u.*, r.role_name 
    FROM `users` u
    JOIN `roles` r ON u.role_id = r.id
    ORDER BY u.role_id ASC, u.full_name ASC
");

$roles = $db->fetchAll("SELECT * FROM `roles` ORDER BY `id` ASC");

$pageTitle = "User & Staff Accounts";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-people-fill text-info me-2"></i>User &amp; Staff Accounts
            </h2>
            <p class="text-muted small mb-0">Manage roles, coordinators, field respondents, and login access credentials.</p>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openUserModal()">
            <i class="bi bi-person-plus-fill me-1"></i> Register New Officer
        </button>
    </div>

    <!-- Users Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark small">
                    <tr>
                        <th>Officer Name &amp; Email</th>
                        <th>Phone Number</th>
                        <th>Assigned Role</th>
                        <th>Account Status</th>
                        <th>Last Login</th>
                        <th>Created At</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= Helper::e($u['full_name']) ?></strong>
                                <small class="text-muted"><i class="bi bi-envelope me-1"></i><?= Helper::e($u['email']) ?></small>
                            </td>
                            <td><?= Helper::e($u['phone'] ?: 'N/A') ?></td>
                            <td>
                                <span class="badge bg-<?= match($u['role_name']) {
                                    'Administrator' => 'danger',
                                    'Relief Coordinator' => 'primary',
                                    default => 'secondary'
                                } ?>">
                                    <?= Helper::e($u['role_name']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $u['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($u['status']) ?>
                                </span>
                            </td>
                            <td class="font-monospace text-muted"><?= Helper::formatDateTime($u['last_login']) ?></td>
                            <td class="font-monospace text-muted"><?= Helper::formatDateTime($u['created_at'], 'd M Y') ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openUserModal(<?= json_encode($u) ?>)'>
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- User Add/Edit Modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="userModalLabel">Staff Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="userForm">
                <?= CSRF::inputField() ?>
                <input type="hidden" id="user_id" name="id" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold small">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="Officer full name">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold small">Official Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="name@floodrelief.org">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold small">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" placeholder="+91 98470 12345">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="role_id" class="form-label fw-semibold small">Authorization Role *</label>
                            <select class="form-select" id="role_id" name="role_id" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['id'] ?>"><?= Helper::e($r['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="status" class="form-label fw-semibold small">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold small">Password (Leave blank to keep unchanged)</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Save Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let userModalInstance = null;

function openUserModal(user = null) {
    document.getElementById('user_id').value = user ? user.id : '';
    document.getElementById('full_name').value = user ? user.full_name : '';
    document.getElementById('email').value = user ? user.email : '';
    document.getElementById('phone').value = user ? (user.phone || '') : '';
    document.getElementById('role_id').value = user ? user.role_id : 2;
    document.getElementById('status').value = user ? user.status : 'active';
    document.getElementById('password').value = '';

    document.getElementById('userModalLabel').textContent = user ? 'Edit Staff Account' : 'Register New Officer';
    userModalInstance = new bootstrap.Modal(document.getElementById('userModal'));
    userModalInstance.show();
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
