<?php
declare(strict_types=1);

require_once __DIR__ . '/_common.php';

require_admin();

$pdo = db();

$stats = [
    'events' => 0,
    'roster' => 0,
    'news'   => 0,
    'users'  => 0,
    'rsp'    => 0,
];

try {
    $stats['events'] = (int)$pdo
        ->query('SELECT COUNT(*) FROM events')
        ->fetchColumn();

    $stats['roster'] = (int)$pdo
        ->query("SELECT COUNT(*) FROM roster WHERE status = 'active' AND source='admin'")
        ->fetchColumn();

    $stats['news'] = (int)$pdo
        ->query('SELECT COUNT(*) FROM news')
        ->fetchColumn();

    $stats['users'] = (int)$pdo
        ->query('SELECT COUNT(*) FROM users')
        ->fetchColumn();

    $stats['rsp'] = (int)$pdo
        ->query('SELECT COUNT(*) FROM dkp_ledger')
        ->fetchColumn();

} catch (Throwable $e) {
    // No dejamos que un fallo estadístico deje el panel completamente en blanco.
}

admin_header('Dashboard', 'dashboard');
?>

<div class="eyebrow">
    NEXUS · ADMINISTRACIÓN
</div>

<h1>
    Panel de administración
</h1>

<p class="muted">
    Gestiona el calendario, el roster y las noticias
    sin tocar el código de la web.
</p>

<div class="admin-grid">

    <div class="admin-card">
        <div class="stat-big">
            <?= $stats['events'] ?>
        </div>

        <p>Eventos</p>

        <a href="/Admin/events.php">
            Gestionar →
        </a>
    </div>


    <div class="admin-card">
        <div class="stat-big">
            <?= $stats['roster'] ?>
        </div>

        <p>Personajes activos</p>

        <a href="/Admin/roster.php">
            Gestionar →
        </a>
    </div>


    <div class="admin-card">
        <div class="stat-big">
            <?= $stats['news'] ?>
        </div>

        <p>Noticias</p>

        <a href="/Admin/news.php">
            Gestionar →
        </a>
    </div>


    <div class="admin-card">
        <div class="stat-big">
            <?= $stats['rsp'] ?>
        </div>
        <p>Movimientos RSP</p>
        <a href="/Admin/rsp.php">Gestionar →</a>
    </div>

    <div class="admin-card">
        <div class="stat-big">
            <?= $stats['users'] ?>
        </div>

        <p>Usuarios admin</p>

        <?php if (($stats['users'] ?? 0) > 0): ?>
            <a href="/Admin/users.php">
                Gestionar →
            </a>
        <?php endif; ?>
    </div>

</div>

<?php
admin_footer();