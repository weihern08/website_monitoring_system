<?php
$current = $currentPage ?? '';
$nav = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => appUrl('admin/index.php'), 'icon' => 'grid'],
    ['id' => 'websites',  'label' => 'Monitors',  'href' => appUrl('admin/websites.php'), 'icon' => 'monitor'],
    ['id' => 'logs',      'label' => 'Logs',      'href' => appUrl('admin/logs.php'), 'icon' => 'list'],
    ['id' => 'alerts',    'label' => 'Alerts',    'href' => appUrl('admin/alerts.php'), 'icon' => 'bell'],
    ['id' => 'settings',  'label' => 'Settings',  'href' => appUrl('admin/settings.php'), 'icon' => 'gear'],
];

function navIcon(string $name): string
{
    switch ($name) {
        case 'grid':
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>';
        case 'monitor':
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>';
        case 'list':
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>';
        case 'bell':
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
    }
}
?>
<aside class="sidebar">
    <a class="brand" href="<?= e(appUrl('admin/index.php')) ?>">
        <span class="brand-mark">
            <span class="pulse-dot"></span>
        </span>
        <span>
            <strong><?= e(APP_NAME) ?></strong>
            <small><?= e(APP_EDITION) ?> · Admin panel</small>
        </span>
    </a>
    <nav>
        <?php foreach ($nav as $item): ?>
            <a class="<?= $current === $item['id'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                <?= navIcon($item['icon']) ?>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <a href="<?= e(appUrl('status.php')) ?>" target="_blank" rel="noopener">Public status</a>
        <span class="pro-badge">PRO</span>
    </div>
</aside>
