<?php
/**
 * includes/functions.php
 * -----------------------------------------------------------------
 * Shared utility functions for the application.
 * Include once via require_once after db.php.
 * -----------------------------------------------------------------
 */

/**
 * Handle profile picture upload.
 * Accepts a $_FILES entry, validates, and saves to uploads/profiles/.
 * Returns the relative path on success, or an error string on failure.
 */
function handle_profile_picture_upload(array $file): string
{
    // Validate upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ''; // No file uploaded
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes, true)) {
        return 'Only JPG, PNG, GIF, and WebP images are allowed.';
    }

    if ($file['size'] > $maxSize) {
        return 'Image must be under 2MB.';
    }

    // Create directory if needed
    $uploadDir = __DIR__ . '/../uploads/profiles/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('profile_') . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return 'Failed to save the uploaded image. Please try again.';
    }

    return 'uploads/profiles/' . $filename;
}

/**
 * Extract the local 10-digit part of a phone number, accepting both the
 * bare 98XXXXXXXX form and the full +977-XXXXXXXXXX form.
 */
function local_phone_digits(string $phone): string
{
    $digits = preg_replace('/\D/', '', $phone);
    // Strip the 977 country code if the caller already included it
    if (strlen($digits) === 13 && str_starts_with($digits, '977')) {
        $digits = substr($digits, 3);
    }
    return $digits;
}

/**
 * Normalize a phone number to Nepal's standard format (+977-XXXXXXXXXX).
 * Accepts local 10-digit numbers (e.g. 9812345678) as well as numbers
 * already carrying the country code (+9779812345678, +977-9812345678,
 * 977-9812345678). Returns the normalized string, or the original input
 * untouched if it can't be parsed.
 */
function normalize_nepal_phone(string $phone): string
{
    $digits = local_phone_digits($phone);
    return (strlen($digits) === 10) ? '+977-' . $digits : $phone;
}

/**
 * Validate a phone number against Nepal's standard (+977-XXXXXXXXXX).
 * Requires exactly 10 digits beginning with 9 (Nepali mobile numbers).
 */
function is_valid_nepal_phone(string $phone): bool
{
    return preg_match('/^9\d{9}$/', local_phone_digits($phone)) === 1;
}

/**
 * Strip the +977 country prefix for display inside a phone input that
 * already shows the country code as a fixed prefix chip.
 */
function display_nepal_phone(string $phone): string
{
    return preg_replace('/^\+?977[\s-]*/', '', $phone);
}

/**
 * Handle a citizenship document upload (front or back side).
 * Accepts a $_FILES entry, validates, and saves to uploads/citizenships/.
 * Returns the relative path on success, or an error string on failure.
 * Callers must check for UPLOAD_ERR_NO_FILE themselves for required fields.
 */
function handle_citizenship_upload(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'The file could not be uploaded. Please try again.';
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowedTypes, true)) {
        return 'Only JPG, PNG, and WebP images are allowed.';
    }

    if ($file['size'] > $maxSize) {
        return 'Image must be under 2MB.';
    }

    // Create directory if needed
    $uploadDir = __DIR__ . '/../uploads/citizenships/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Generate a unique filename (trust the validated MIME type, not user input)
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $ext = 'jpg';
    }
    $filename = uniqid('citizenship_') . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return 'Failed to save the uploaded image. Please try again.';
    }

    return 'uploads/citizenships/' . $filename;
}

/**
 * Render a small "Verified ID" badge based on a user's citizenship status.
 * Returns an empty string (no badge) when there is no citizenship on file
 * (e.g. accounts created before this feature).
 */
function render_citizenship_badge(?string $status): string
{
    switch ($status) {
        case 'approved':
            return '<span class="citizenship-badge citizenship-badge--approved" title="Citizenship verified by an admin">🪪 Verified ID</span>';
        case 'pending':
            return '<span class="citizenship-badge citizenship-badge--pending" title="Citizenship awaiting admin review">🪪 ID pending</span>';
        case 'rejected':
            return '<span class="citizenship-badge citizenship-badge--rejected" title="Citizenship was not approved">🪪 ID rejected</span>';
        default:
            return '';
    }
}

/**
 * Check that a password meets the strong-password policy:
 * at least 8 characters, containing an uppercase letter, a lowercase
 * letter, a number, and a symbol.
 */
function is_strong_password(string $password): bool
{
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $password) === 1;
}

/**
 * Render a user avatar — shows the profile picture if available,
 * otherwise falls back to the initial letter badge.
 */
function render_avatar(string $profilePicture, string $fullName, int $size = 36, string $extraClasses = ''): string
{
    if ($profilePicture) {
        $src = BASE_URL . '/' . htmlspecialchars($profilePicture);
        $sizeStyle = "width:{$size}px; height:{$size}px;";
        return '<img src="' . $src . '" alt="" class="avatar-img" style="' . $sizeStyle . ' border-radius:50%; object-fit:cover; flex-shrink:0;' . $extraClasses . '">';
    }

    $initial = strtoupper(substr($fullName, 0, 1));
    $fontSize = $size <= 30 ? '0.7rem' : ($size <= 40 ? '0.85rem' : '1.2rem');
    return '<div class="avatar" style="width:' . $size . 'px; height:' . $size . 'px; font-size:' . $fontSize . '; background:var(--brick); color:var(--white); display:flex; align-items:center; justify-content:center; flex-shrink:0;">' . htmlspecialchars($initial) . '</div>';
}

/**
 * Render a role icon badge with color-coded SVG icon.
 * Shows an icon + role name with appropriate color for admin/landlord/tenant.
 *
 * @param string $role  The role name ('admin', 'landlord', 'tenant')
 * @param bool   $showLabel  Whether to show the role name text next to the icon
 * @return string  HTML for the role badge
 */
function render_role_badge(string $role, bool $showLabel = true): string
{
    $role = strtolower($role);
    
    // Define icon SVGs, colors, and labels per role
    $roleData = [
        'admin' => [
            'label' => 'Admin',
            'color' => '#3B82F6',
            'bg'    => 'rgba(59, 130, 246, 0.12)',
            'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
        ],
        'landlord' => [
            'label' => 'Landlord',
            'color' => '#B8492E',
            'bg'    => 'rgba(184, 73, 46, 0.12)',
            'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5L12 3l9 7.5"/><path d="M5 9v9a1 1 0 0 0 1 1h4v-5h4v5h4a1 1 0 0 0 1-1V9"/></svg>',
        ],
        'tenant' => [
            'label' => 'Tenant',
            'color' => '#4B7A5B',
            'bg'    => 'rgba(75, 122, 91, 0.12)',
            'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        ],
    ];
    
    $data = $roleData[$role] ?? $roleData['tenant'];
    $icon = $data['icon'];
    $label = $data['label'];
    $color = $data['color'];
    $bg = $data['bg'];
    
    $html = '<span class="role-badge" style="display:inline-flex; align-items:center; gap:5px; font-size:0.78rem; font-weight:600; color:' . $color . ';">';
    $html .= '<span class="role-badge-icon" style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:5px; background:' . $bg . '; color:' . $color . ';">' . $icon . '</span>';
    if ($showLabel) {
        $html .= '<span class="role-badge-label">' . $label . '</span>';
    }
    $html .= '</span>';
    
    return $html;
}

/**
 * Whether the bookings table has the newer `cancellation_reason` column.
 * Older databases (created before this feature) won't have it until the
 * ALTER TABLE migration is run, so callers must fall back gracefully.
 */
function bookings_has_cancel_reason(PDO $pdo): bool
{
    static $hasColumn = null;
    if ($hasColumn === null) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'cancellation_reason'");
            $hasColumn = (bool) $stmt->fetch();
        } catch (Exception $e) {
            $hasColumn = false;
        }
    }
    return $hasColumn;
}

function render_pagination(int $totalItems, int $currentPage, string $baseUrl, int $perPage = 20): void
{
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));

    if ($totalPages <= 1) {
        return; // No pagination needed
    }

    // Build a URL helper that preserves existing query params and swaps 'page'
    $queryParams = $_GET;
    unset($queryParams['page']);

    // Use a closure to avoid "cannot redeclare function" errors
    $pageUrl = function (string $base, array $params, int $page) use ($baseUrl): string {
        $p = $params;
        if ($page > 1) {
            $p['page'] = $page;
        }
        $qs = http_build_query($p);
        return $base . ($qs ? '?' . $qs : '');
    };

    // Determine which page numbers to show (max 7 visible)
    $startPage = max(1, $currentPage - 3);
    $endPage   = min($totalPages, $currentPage + 3);
    if ($endPage - $startPage < 6) {
        if ($startPage === 1) {
            $endPage = min($totalPages, $startPage + 6);
        } else {
            $startPage = max(1, $endPage - 6);
        }
    }

    echo '<nav class="pagination" aria-label="Pagination">';
    echo '<span class="pagination-info">Page ' . $currentPage . ' of ' . $totalPages . ' (' . $totalItems . ' records)</span>';
    echo '<div class="pagination-links">';

    // Previous
    if ($currentPage > 1) {
        echo '<a href="' . htmlspecialchars($pageUrl($baseUrl, $queryParams, $currentPage - 1)) . '" class="pagination-link pagination-prev" aria-label="Previous page">&larr; Previous</a>';
    } else {
        echo '<span class="pagination-link pagination-prev is-disabled">&larr; Previous</span>';
    }

    // Page numbers
    if ($startPage > 1) {
        echo '<a href="' . htmlspecialchars($pageUrl($baseUrl, $queryParams, 1)) . '" class="pagination-link">1</a>';
        if ($startPage > 2) {
            echo '<span class="pagination-ellipsis">&hellip;</span>';
        }
    }
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i === $currentPage) {
            echo '<span class="pagination-link is-current" aria-current="page">' . $i . '</span>';
        } else {
            echo '<a href="' . htmlspecialchars($pageUrl($baseUrl, $queryParams, $i)) . '" class="pagination-link">' . $i . '</a>';
        }
    }
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            echo '<span class="pagination-ellipsis">&hellip;</span>';
        }
        echo '<a href="' . htmlspecialchars($pageUrl($baseUrl, $queryParams, $totalPages)) . '" class="pagination-link">' . $totalPages . '</a>';
    }

    // Next
    if ($currentPage < $totalPages) {
        echo '<a href="' . htmlspecialchars($pageUrl($baseUrl, $queryParams, $currentPage + 1)) . '" class="pagination-link pagination-next" aria-label="Next page">Next &rarr;</a>';
    } else {
        echo '<span class="pagination-link pagination-next is-disabled">Next &rarr;</span>';
    }

    echo '</div>';
    echo '</nav>';
}
