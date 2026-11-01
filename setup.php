<?php
// One-time installer. Creates tables, seeds the school map and the first administrator.
// Delete this file after setup.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/bootstrap.php';

$msg = ''; $err = ''; $done = false; $upgraded = false;

// Folder for the map editor's reference image (the web server must be able to write to it).
$upDir = __DIR__ . '/uploads';
if (!is_dir($upDir)) @mkdir($upDir, 0755, true);

// Adds a column to a table that was created by an older version of this app.
function ensure_column(PDO $pdo, string $table, string $col, string $ddl): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([DB_NAME, $table, $col]);
    if ((int)$st->fetchColumn() > 0) return false;
    $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN ' . $ddl);
    return true;
}

// Does this index exist on the table?
function has_index(PDO $pdo, string $table, string $index): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([DB_NAME, $table, $index]);
    return (int)$st->fetchColumn() > 0;
}

try {
    $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `' . DB_NAME . '`');

    $sql = file_get_contents(__DIR__ . '/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*[\r\n]+/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) continue;
        if (preg_match('/^INSERT INTO rooms/i', $stmt)) {
            if ((int)$root->query('SELECT COUNT(*) FROM rooms')->fetchColumn() > 0) continue;
        }
        $root->exec($stmt);
    }
    // Upgrade older installs: test-record flags used by the Experiment page.
    foreach (['checkins', 'attendance', 'room_changes'] as $tbl) {
        if (ensure_column($root, $tbl, 'is_test', 'is_test TINYINT(1) NOT NULL DEFAULT 0')) $upgraded = true;
    }
    // Upgrade: a room change is now tied to one class meeting and the date it applies to.
    foreach ([
        'subject_id'  => 'subject_id INT UNSIGNED NULL',
        'session_id'  => 'session_id INT UNSIGNED NULL',
        'change_date' => 'change_date DATE NULL',
    ] as $col => $ddl) {
        if (ensure_column($root, 'room_changes', $col, $ddl)) $upgraded = true;
    }
    if (!has_index($root, 'room_changes', 'idx_rc_session_date')) {
        $root->exec('ALTER TABLE room_changes ADD INDEX idx_rc_session_date (session_id, change_date)');
    }
    // Upgrade: teachers can time in and out as many times as they like in a day, so the
    // "one record per teacher per day" rule is replaced by a plain index.
    if (!has_index($root, 'attendance', 'idx_user_date')) {
        $root->exec('ALTER TABLE attendance ADD INDEX idx_user_date (user_id, att_date)');
        $upgraded = true;
    }
    if (has_index($root, 'attendance', 'uq_user_date')) {
        $root->exec('ALTER TABLE attendance DROP INDEX uq_user_date');
        $upgraded = true;
    }
    $adminExists = (int)$root->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0;
} catch (Throwable $e) {
    $err = 'Could not reach MySQL: ' . $e->getMessage() . ' — check the values in config.php.';
    $adminExists = true;
}

if (!$err && !$adminExists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['full_name'] ?? '');
    $user = trim($_POST['username'] ?? '');
    $pass = (string)($_POST['password'] ?? '');
    if ($name === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $user)) {
        $err = 'Enter a full name and a username of 3–50 letters, numbers, dots, dashes or underscores.';
    } elseif (strlen($pass) < 8) {
        $err = 'Use a password of at least 8 characters.';
    } else {
        $root->prepare("INSERT INTO users (full_name, username, password_hash, role) VALUES (?,?,?, 'admin')")
             ->execute([$name, $user, password_hash($pass, PASSWORD_DEFAULT)]);
        $done = true; $adminExists = true;
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Setup · <?= e(APP_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>"></head>
<body class="login-body">
<div class="setup">
  <form method="post" class="login-card">
    <div class="login-brand"><span class="brand-mark"><?= icon('map', 20) ?></span><strong>First-time setup</strong></div>
    <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
    <?php if ($done): ?>
      <div class="flash flash-ok">Done. The database is ready and your administrator account exists.</div>
      <p>For safety, <strong>delete setup.php</strong> from the server, then sign in.</p>
      <a class="btn btn-primary btn-block" href="<?= e(url('login.php?role=admin')) ?>">Go to sign in</a>
    <?php elseif ($adminExists && !$err): ?>
      <div class="flash flash-ok">Setup is already complete.<?= $upgraded ? ' Your database was upgraded for the new defect reports, subject room changes and repeatable time in / time out.' : '' ?></div>
      <p>The tables were checked and are up to date. Delete <strong>setup.php</strong> from the server.</p>
      <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>">Go to sign in</a>
    <?php elseif (!$err || strpos($err, 'Enter') === 0 || strpos($err, 'Use a') === 0): ?>
      <p class="muted">Tables and the campus map are created automatically. Now create the first administrator.</p>
      <?= csrf_field() ?>
      <label class="field"><span>Full name</span><input name="full_name" required value="<?= e($_POST['full_name'] ?? '') ?>"></label>
      <label class="field"><span>Username</span><input name="username" required value="<?= e($_POST['username'] ?? '') ?>"></label>
      <label class="field"><span>Password (8+ characters)</span><input type="password" name="password" required minlength="8"></label>
      <button class="btn btn-primary btn-block">Create administrator</button>
    <?php endif; ?>
  </form>
</div>
</body></html>
