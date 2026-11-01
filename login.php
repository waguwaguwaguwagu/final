<?php
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) redirect('dashboard.php');

$error = '';
$role  = ($_POST['role'] ?? $_GET['role'] ?? 'teacher') === 'admin' ? 'admin' : 'teacher';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $pass     = (string)($_POST['password'] ?? '');

    $fails = $_SESSION['fails'] ?? 0;
    $since = $_SESSION['fail_since'] ?? time();
    if (time() - $since > 300) { $fails = 0; $since = time(); }

    if ($fails >= 5) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE username = ?');
        $st->execute([$username]);
        $u = $st->fetch();
        if (!$u || !password_verify($pass, $u['password_hash'])) {
            $error = 'Incorrect username or password.';
            $_SESSION['fails'] = $fails + 1;
            $_SESSION['fail_since'] = $since;
        } elseif (!$u['active']) {
            $error = 'This account is deactivated. Ask an administrator to reactivate it.';
        } elseif ($u['role'] !== $role) {
            $error = 'This is ' . ($u['role'] === 'admin' ? 'an administrator' : 'a teacher') . ' account. Choose the ' . ucfirst($u['role']) . ' tab to sign in.';
        } else {
            session_regenerate_id(true);
            unset($_SESSION['fails'], $_SESSION['fail_since']);
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['splash'] = 1;   // play the welcome animation once on the next page
            db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([now(), $u['id']]);
            redirect('dashboard.php');
        }
    }
}

// Decorative floor plan for the left panel (shapes only, no names or people).
$shapes = [];
$needsSetup = false;
try {
    $shapes = db()->query('SELECT x, y, w, h, kind FROM rooms WHERE deleted_at IS NULL')->fetchAll();
    $needsSetup = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() === 0;
} catch (Throwable $e) { $needsSetup = true; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(APP_NAME) ?></title>
<meta name="theme-color" content="#0c1a3d">
<?= favicon_tag() ?>
<script>try { if (sessionStorage.getItem('splashSeen')) document.documentElement.className += ' no-splash'; } catch (e) {}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="login-body">
<?= splash_html('Getting things ready') ?>
<div class="login">
  <section class="login-art" aria-hidden="true">
    <?php
    // The dots are drawn and moved by assets/js/login.js: they walk through the empty space
    // between the rooms and step into a room, which lights up while someone is inside.
    $mapData = array_map(fn($sh) => [
        'x' => round((float)$sh['x'], 1), 'y' => round((float)$sh['y'], 1),
        'w' => round((float)$sh['w'], 1), 'h' => round((float)$sh['h'], 1),
        'passage' => $sh['kind'] === 'passage',
    ], $shapes);
    ?>
    <svg id="artMap" viewBox="0 0 <?= MAP_W ?> <?= MAP_H ?>" preserveAspectRatio="xMidYMid meet">
      <g id="artRooms">
      <?php foreach ($shapes as $i => $s): ?>
        <rect class="art-room<?= $s['kind'] === 'passage' ? ' art-passage' : '' ?>" x="<?= (float)$s['x'] ?>" y="<?= (float)$s['y'] ?>" width="<?= (float)$s['w'] ?>" height="<?= (float)$s['h'] ?>" rx="4"/>
      <?php endforeach; ?>
      </g>
      <g id="artDots"></g>
    </svg>
    <script>window.LOGIN_MAP = <?= json_encode(['w' => MAP_W, 'h' => MAP_H, 'rooms' => $mapData], JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
    <div class="login-art-copy">
      <h2>See where everyone is, right now.</h2>
      <p>Live room check-ins on the <?= e(SCHOOL_NAME) ?> campus map.</p>
    </div>
  </section>

  <section class="login-panel">
    <form method="post" class="login-card" autocomplete="on">
      <div class="login-brand"><span class="brand-mark"><?= icon('map', 20) ?></span><strong><?= e(APP_NAME) ?></strong></div>
      <h1>Sign in</h1>
      <p class="muted"><?= e(SCHOOL_NAME) ?></p>

      <div class="seg seg-slide" id="roleSeg" role="radiogroup" data-pos="<?= $role === 'admin' ? '1' : '0' ?>">
        <i class="seg-thumb" aria-hidden="true"></i>
        <label class="seg-opt"><input type="radio" name="role" value="teacher" <?= $role === 'teacher' ? 'checked' : '' ?>><span>Teacher</span></label>
        <label class="seg-opt"><input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>><span>Administrator</span></label>
      </div>
      <div class="role-swap" aria-live="polite"><span class="role-text" id="roleText"><?= $role === 'admin' ? 'Signing in as an administrator' : 'Signing in as a teacher' ?></span></div>

      <?php if ($error): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
      <?= csrf_field() ?>
      <label class="field"><span>Username</span>
        <input type="text" name="username" value="<?= e($username) ?>" required autofocus autocomplete="username">
      </label>
      <label class="field"><span>Password</span>
        <input type="password" name="password" required autocomplete="current-password">
      </label>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
      <?php if ($needsSetup): ?>
        <p class="muted small center">First time here? <a href="<?= e(url('setup.php')) ?>">Run setup</a> to create the database and the first administrator.</p>
      <?php endif; ?>
    </form>
  </section>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/login.js')) ?>"></script>
<script>try { sessionStorage.setItem('splashSeen', '1'); } catch (e) {}</script>
</body>
</html>
