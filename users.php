<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$perPage = 20;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// Flash messages
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Handle delete user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    validate_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId !== (int) $_SESSION['user_id']) {
        // Fetch profile picture path before deleting the user
        $stmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ? AND role != 'admin'");
        $stmt->execute([$userId]);
        $userToDelete = $stmt->fetch();

        if ($userToDelete) {
            // Delete profile picture file from disk if it exists
            if (!empty($userToDelete['profile_picture'])) {
                $picPath = __DIR__ . '/../' . $userToDelete['profile_picture'];
                if (file_exists($picPath)) {
                    unlink($picPath);
                }
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ? AND role != 'admin'");
            $stmt->execute([$userId]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => 'User deleted successfully.'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete admin users or user not found.'];
            }
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Cannot delete admin users or user not found.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid user.'];
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Handle add user action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    validate_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = normalize_nepal_phone(trim($_POST['phone'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? '';
    $verificationStatus = $_POST['verification_status'] ?? 'approved';

    $errors = [];

    // Validate
    if (strlen($fullName) < 3)                  $errors[] = 'Full name must be at least 3 characters.';
    if (strlen($fullName) > 120)                 $errors[] = 'Full name must be under 120 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($email) > 255)                    $errors[] = 'Email must be under 255 characters.';
    if (!is_valid_nepal_phone($phone)) $errors[] = 'Enter a valid 10-digit Nepali mobile number (e.g., 9812345678).';
    if (!is_strong_password($password))          $errors[] = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a symbol.';
    if (strlen($password) > 128)                 $errors[] = 'Password must be under 128 characters.';
    if (!in_array($role, ['tenant', 'landlord', 'admin'], true)) $errors[] = 'Please select a valid role.';
    if (!in_array($verificationStatus, ['approved', 'pending'], true)) $verificationStatus = 'approved';

    if (empty($errors)) {
        // Check email uniqueness
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'This email is already registered.';
        }
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Landlords always start as pending unless admin overrides
        $finalStatus = $role === 'landlord' ? $verificationStatus : ($role === 'admin' ? 'approved' : $verificationStatus);

        $stmt = $pdo->prepare(
            'INSERT INTO users (full_name, email, phone, password, role, verification_status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$fullName, $email, $phone, $passwordHash, $role, $finalStatus]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'User created successfully.'];
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' | ', $errors)];
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Handle toggle user verification status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_verification'])) {
    validate_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    if ($userId > 0 && in_array($newStatus, ['approved', 'rejected', 'pending'], true)) {
        $stmt = $pdo->prepare("UPDATE users SET verification_status = ? WHERE user_id = ? AND role = 'landlord'");
        $stmt->execute([$newStatus, $userId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Verification status updated.'];
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Handle citizenship document review (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_citizenship'])) {
    validate_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    if ($userId > 0 && in_array($newStatus, ['approved', 'rejected'], true)) {
        $stmt = $pdo->prepare("UPDATE users SET citizenship_status = ? WHERE user_id = ?");
        $stmt->execute([$newStatus, $userId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Citizenship status updated.'];

        // Notify the user of the outcome
        $message = $newStatus === 'approved'
            ? 'Your citizenship document was verified. Your "Verified ID" badge is now visible to other users.'
            : 'Your citizenship document was not approved. Please contact the site admin for details.';
        $pdo->prepare('INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())')
            ->execute([$userId, $message]);
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Handle admin name correction (users cannot change their own name after sign-up)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_user'])) {
    validate_csrf();
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $fullName = trim($_POST['new_name'] ?? '');
    if ($userId > 0 && strlen($fullName) >= 3 && strlen($fullName) <= 120) {
        $stmt = $pdo->prepare('UPDATE users SET full_name = ? WHERE user_id = ?');
        $stmt->execute([$fullName, $userId]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Name corrected. The user will see the new name after their next login.'];
        // Keep the admin's own session in sync if they renamed themselves
        if ($userId === (int) $_SESSION['user_id']) {
            $_SESSION['full_name'] = $fullName;
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Name must be between 3 and 120 characters.'];
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// Search
$search = trim($_GET['search'] ?? '');

// Role filter
$roleFilter = $_GET['role'] ?? '';
$allowedRoles = ['admin', 'landlord', 'tenant'];

// Verification filter
$verificationFilter = $_GET['verification'] ?? '';
$allowedVerifications = ['pending', 'approved', 'rejected'];

// Build WHERE clause
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(full_name LIKE ? OR email LIKE ?)";
    $like  = '%' . $search . '%';
    $params = array_merge($params, [$like, $like]);
}

if ($roleFilter !== '' && in_array($roleFilter, $allowedRoles, true)) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}

if ($verificationFilter !== '' && in_array($verificationFilter, $allowedVerifications, true)) {
    $where[] = "verification_status = ?";
    $params[] = $verificationFilter;
}

$whereClause = '';
if (!empty($where)) {
    $whereClause = 'WHERE ' . implode(' AND ', $where);
}

// Total count for pagination
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalUsers = (int) $countStmt->fetchColumn();

// Fetch current page
$sql = "SELECT user_id, full_name, email, role, verification_status,
               profile_picture, citizenship_front, citizenship_back, citizenship_status,
               created_at
        FROM users
        $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$perPage, $offset]));
$users = $stmt->fetchAll();

$pageTitle = 'Manage users';
$pageSubtitle = 'View, filter, and manage all registered users.';
$activeNav = 'users';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?> mb-22">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="stat-grid mb-22">
    <?php
    $countAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $countLandlords = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'landlord'")->fetchColumn();
    $countTenants = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'tenant'")->fetchColumn();
    $countCitPending = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE citizenship_status = 'pending' AND role != 'admin'")->fetchColumn();
    ?>
    <div class="stat-card">
        <div class="stat-label">Admins</div>
        <div class="stat-value"><?php echo $countAdmins; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Landlords</div>
        <div class="stat-value"><?php echo $countLandlords; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tenants</div>
        <div class="stat-value"><?php echo $countTenants; ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">🪪 ID pending review</div>
        <div class="stat-value"><?php echo $countCitPending; ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <h2>All users (<?php echo $totalUsers; ?>)</h2>
        <div class="panel-head-actions">
            <button type="button" class="btn btn-primary" style="padding:8px 16px; font-size:0.82rem;" onclick="document.getElementById('addUserModal').classList.add('is-open')">+ Add user</button>
            <form class="panel-filter" method="get">
                <?php if ($search !== ''): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <label for="role-filter">Role</label>
                <select name="role" id="role-filter" onchange="this.form.submit()">
                    <option value="">All roles</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="landlord" <?php echo $roleFilter === 'landlord' ? 'selected' : ''; ?>>Landlord</option>
                    <option value="tenant" <?php echo $roleFilter === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
                </select>
                <label for="verification-filter">Verification</label>
                <select name="verification" id="verification-filter" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="pending" <?php echo $verificationFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $verificationFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $verificationFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <?php if ($roleFilter !== '' || $verificationFilter !== ''): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/users.php<?php echo $search !== '' ? '?search=' . urlencode($search) : ''; ?>" class="filter-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Search bar -->
    <form class="search-bar panel-section" method="get">
        <?php if ($roleFilter !== ''): ?>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($roleFilter); ?>">
        <?php endif; ?>
        <?php if ($verificationFilter !== ''): ?>
            <input type="hidden" name="verification" value="<?php echo htmlspecialchars($verificationFilter); ?>">
        <?php endif; ?>
        <div class="field">
            <label for="search">Search users</label>
            <input type="text" id="search" name="search" placeholder="Search by name or email..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="<?php echo BASE_URL; ?>/admin/users.php<?php
                $params = [];
                if ($roleFilter !== '') $params['role'] = $roleFilter;
                if ($verificationFilter !== '') $params['verification'] = $verificationFilter;
                echo !empty($params) ? '?' . http_build_query($params) : '';
            ?>" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if ($search !== '' && empty($users)): ?>
        <div class="empty-state">
            <strong>No users match "<?php echo htmlspecialchars($search); ?>"</strong>
            <p>Try a different name or email.</p>
        </div>
    <?php elseif (empty($users) && $search === ''): ?>
        <div class="empty-state">
            <strong>No users found</strong>
            <p>No users match the current filters.</p>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Verification</th>
                        <th>Citizenship</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                              <div class="cell-name">
                                <?php if (!empty($u['profile_picture'])): ?>
                                  <img src="<?php echo BASE_URL . '/' . htmlspecialchars($u['profile_picture']); ?>" alt="" style="width:28px; height:28px; border-radius:50%; object-fit:cover; flex-shrink:0;">
                                <?php else: ?>
                                  <span class="cell-avatar" style="width:28px; height:28px; font-size:0.65rem;"><?php echo strtoupper(substr($u['full_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($u['full_name']); ?>
                              </div>
                            </td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><span class="status-pill" style="background:var(--paper-dark);"><?php echo ucfirst($u['role']); ?></span></td>
                            <td><span class="status-pill status-<?php echo htmlspecialchars($u['verification_status']); ?>"><?php echo ucfirst($u['verification_status']); ?></span></td>
                            <td class="cell-actions">
                                <?php if (!empty($u['citizenship_front']) || !empty($u['citizenship_back'])): ?>
                                    <div style="display:flex; flex-direction:column; gap:6px; align-items:flex-start;">
                                        <span class="status-pill status-<?php echo htmlspecialchars($u['citizenship_status']); ?>"><?php echo ucfirst($u['citizenship_status']); ?></span>
                                        <div style="display:flex; gap:5px; flex-wrap:wrap;">
                                            <button type="button" class="btn-sm btn-sm-ghost" onclick="openCitizenshipModal(<?php echo (int) $u['user_id']; ?>)">View</button>
                                            <?php if ($u['citizenship_status'] !== 'approved'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                                                    <input type="hidden" name="new_status" value="approved">
                                                    <input type="hidden" name="update_citizenship" value="1">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="btn-sm btn-sm-approve">Approve</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($u['citizenship_status'] !== 'rejected'): ?>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                                                    <input type="hidden" name="new_status" value="rejected">
                                                    <input type="hidden" name="update_citizenship" value="1">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="btn-sm btn-sm-danger">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="font-size:0.82rem; color:var(--slate);">Not uploaded</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                            <td class="cell-actions">
                                <?php if ($u['role'] !== 'admin'): ?>
                                    <button type="button" class="btn-sm btn-sm-ghost"
                                            onclick="renameUser(<?php echo (int) $u['user_id']; ?>, <?php echo json_encode($u['full_name']); ?>)">
                                        Rename
                                    </button>
                                    <form method="post" id="rename_<?php echo (int) $u['user_id']; ?>" style="display:none;">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                                        <input type="hidden" name="new_name" id="new_name_<?php echo (int) $u['user_id']; ?>" value="">
                                        <input type="hidden" name="rename_user" value="1">
                                        <?php echo csrf_field(); ?>
                                    </form>
                                    <?php if ($u['role'] === 'landlord' && $u['verification_status'] === 'pending'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                                            <input type="hidden" name="new_status" value="approved">
                                            <input type="hidden" name="toggle_verification" value="1">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" class="btn-sm btn-sm-approve">Approve</button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn-sm btn-sm-danger"
                                            onclick="confirmAction('Delete user <strong><?php echo htmlspecialchars($u['full_name']); ?></strong> (<?php echo htmlspecialchars($u['email']); ?>)? This cannot be undone.', 'delete_<?php echo (int) $u['user_id']; ?>')">
                                        Delete
                                    </button>
                                    <form method="post" id="delete_<?php echo (int) $u['user_id']; ?>" style="display:none;">
                                        <input type="hidden" name="user_id" value="<?php echo (int) $u['user_id']; ?>">
                                        <input type="hidden" name="delete_user" value="1">
                                        <?php echo csrf_field(); ?>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:0.82rem; color:var(--slate);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php render_pagination($totalUsers, $page, BASE_URL . '/admin/users.php', $perPage);
        ?>
    <?php endif; ?>
</div>

<!-- Confirmation modal -->
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <h3>Confirm action</h3>
        <p id="confirmMessage">Are you sure?</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmBtn">Confirm</button>
        </div>
    </div>
</div>

<!-- Add user modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-box" style="max-width:520px;">
        <h3 style="margin-bottom:4px;">Add a new user</h3>
        <p style="font-size:0.85rem; color:var(--slate); margin-bottom:18px;">Create a user account manually.</p>
        <form method="post">
            <div style="display:grid; gap:14px;">
                <div class="form-group" style="margin:0;">
                    <label for="add_full_name">Full name</label>
                    <input type="text" id="add_full_name" name="full_name" placeholder="Full name" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group" style="margin:0;">
                        <label for="add_email">Email</label>
                        <input type="email" id="add_email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="add_phone">Phone</label>
                        <div class="phone-input">
                            <span class="phone-prefix" aria-hidden="true">🇳🇵 +977</span>
                            <input type="tel" id="add_phone" name="phone" placeholder="98XXXXXXXX"
                                   inputmode="numeric" autocomplete="tel-national" required>
                        </div>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                    <div class="form-group" style="margin:0;">
                        <label for="add_password">Password</label>
                        <input type="password" id="add_password" name="password" placeholder="At least 8 chars" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label for="add_role">Role</label>
                        <select id="add_role" name="role" required style="width:100%; padding:11px 13px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--white); color:var(--ink);">
                            <option value="tenant">Tenant</option>
                            <option value="landlord">Landlord</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;">
                    <div class="form-group" style="margin:0; flex:1; min-width:150px;">
                        <label for="add_verification_status">Verification</label>
                        <select id="add_verification_status" name="verification_status" style="width:100%; padding:11px 13px; border:1.5px solid var(--line); border-radius:var(--radius-sm); background:var(--white); color:var(--ink);">
                            <option value="approved">Approved</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-actions" style="margin-top:18px; padding-top:16px; border-top:1px solid var(--line);">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('addUserModal').classList.remove('is-open')">Cancel</button>
                <?php echo csrf_field(); ?>
                <button type="submit" name="add_user" value="1" class="btn btn-primary">Create user</button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmAction(message, formId) {
    document.getElementById('confirmMessage').innerHTML = message;
    var modal = document.getElementById('confirmModal');
    modal.classList.add('is-open');
    document.getElementById('confirmBtn').onclick = function() {
        document.getElementById(formId).submit();
    };
}

function renameUser(userId, currentName) {
    var newName = prompt('Enter the corrected full name (exactly as shown on the citizenship document):', currentName);
    if (newName === null) return;
    newName = newName.trim();
    if (newName.length < 3 || newName.length > 120) {
        alert('Name must be between 3 and 120 characters.');
        return;
    }
    document.getElementById('new_name_' + userId).value = newName;
    document.getElementById('rename_' + userId).submit();
}

function closeModal() {
    document.getElementById('confirmModal').classList.remove('is-open');
    document.getElementById('addUserModal').classList.remove('is-open');
}

document.getElementById('confirmModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.getElementById('addUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeModal(); closeCitizenshipModal(); }
});
</script>

<!-- Citizenship documents modal -->
<div class="modal-overlay" id="citizenshipModal">
    <div class="modal-box" style="max-width:780px;">
        <h3 style="margin-bottom:4px;">Citizenship documents</h3>
        <p id="citizenshipModalName" style="font-size:0.85rem; margin-bottom:18px;"></p>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <div style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--slate-light); margin-bottom:6px;">Front side</div>
                <img id="citFrontImg" src="" alt="Citizenship front side" style="width:100%; max-height:300px; object-fit:contain; border:1px solid var(--line); border-radius:8px; background:var(--paper-soft);">
            </div>
            <div>
                <div style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.06em; color:var(--slate-light); margin-bottom:6px;">Back side</div>
                <img id="citBackImg" src="" alt="Citizenship back side" style="width:100%; max-height:300px; object-fit:contain; border:1px solid var(--line); border-radius:8px; background:var(--paper-soft);">
            </div>
        </div>
        <div class="modal-actions" style="margin-top:18px; padding-top:16px; border-top:1px solid var(--line);">
            <button type="button" class="btn btn-ghost" onclick="closeCitizenshipModal()">Close</button>
        </div>
    </div>
</div>

<script>
var citizenshipDocs = <?php echo json_encode(array_column(array_map(function ($u) {
    return [
        'id'   => (int) $u['user_id'],
        'name' => $u['full_name'],
    ];
}, $users), null, 'id')); ?>;
var citizenshipViewUrl = '<?php echo BASE_URL; ?>/admin/view_citizenship_image.php';

function openCitizenshipModal(id) {
    var doc = citizenshipDocs[id];
    if (!doc) return;
    document.getElementById('citizenshipModalName').textContent = doc.name + ' — citizenship documents';
    document.getElementById('citFrontImg').src = citizenshipViewUrl + '?user_id=' + id + '&side=front';
    document.getElementById('citBackImg').src = citizenshipViewUrl + '?user_id=' + id + '&side=back';
    document.getElementById('citizenshipModal').classList.add('is-open');
}

function closeCitizenshipModal() {
    document.getElementById('citizenshipModal').classList.remove('is-open');
}

document.getElementById('citizenshipModal').addEventListener('click', function (e) {
    if (e.target === this) closeCitizenshipModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
