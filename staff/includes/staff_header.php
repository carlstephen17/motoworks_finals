<header class="main-header">
    <h2><?= htmlspecialchars($staffPageTitle ?? 'Staff Dashboard', ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="admin-user-info">
        <span>Hello, Staff</span>
        <button class="staff-sidebar-toggle" type="button" aria-controls="staff-sidebar" aria-expanded="false" aria-label="Open navigation menu" data-sidebar-toggle>
            <i class="fas fa-bars" aria-hidden="true"></i>
        </button>
    </div>
</header>
