<?php
require __DIR__ . '/includes/layout.php';
$u = require_login();
$isAdmin = $u['role'] === 'admin';
$pdo = db();
close_stale_checkins();
$today = vdate('Y-m-d');

$inRooms   = (int)$pdo->query('SELECT COUNT(*) FROM checkins c JOIN users u ON u.id=c.user_id WHERE c.checked_out_at IS NULL AND u.active=1')->fetchColumn();
$roomsUsed = (int)$pdo->query('SELECT COUNT(DISTINCT room_id) FROM checkins WHERE checked_out_at IS NULL')->fetchColumn();
$teachers  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='teacher' AND active=1")->fetchColumn();
$st = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM attendance WHERE att_date = ?'); $st->execute([$today]); $clocked = (int)$st->fetchColumn();

$h = (int)vdate('G');
$greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
$first = explode(' ', trim($u['full_name']))[0];

$myRoom = null; $myPending = 0;
if (!$isAdmin) {
    $myRoom = open_checkin((int)$u['id']);
    $st = $pdo->prepare("SELECT COUNT(*) FROM room_changes WHERE user_id = ? AND status='pending'"); $st->execute([$u['id']]); $myPending = (int)$st->fetchColumn();
}

$att = null; $myClass = ['current' => null, 'next' => null]; $todayList = [];
try {
    $dow = (int)vdate('N');
    $blocks = $isAdmin
        ? to_blocks(sessions_query(' AND ss.day_of_week = ?', [$dow]))
        : array_values(array_filter(teacher_blocks((int)$u['id']), fn($b) => $b['day'] === $dow));
    $ovToday = room_overrides($today, $today);   // classes moved to another room for today
    foreach ($blocks as $b) {
        [$rid, $rname, $moved] = block_room_on($b, $today, $ovToday);
        $todayList[] = ['code' => $b['code'], 'name' => $b['name'], 'color' => $b['color'], 'teacher' => $b['teacher'], 'room' => $rname, 'room_id' => $rid, 'moved' => $moved,
                        's' => $b['start'], 'e' => $b['end'], 'start' => min_label($b['start']), 'end' => min_label($b['end'])];
    }
    if (!$isAdmin) $myClass = class_status((int)$u['id']);
} catch (Throwable $ex) { /* schedule tables are created by setup.php */ }
if (!$isAdmin) $att = attendance_summary((int)$u['id']);

$boot = [
    'rooms' => map_snapshot(), 'my_room_id' => $myRoom ? (int)$myRoom['room_id'] : null,
    'att' => $att, 'my_class' => $myClass, 'today' => $todayList,
];

page_start('Campus Map', 'dashboard');
?>
<section class="welcome">
  <div>
    <h2><?= e($greet) ?>, <?= e($first) ?>.</h2>
    <p><?= $isAdmin ? 'Here is who is where on campus right now.' : ($myRoom ? 'You are checked in to ' . e($myRoom['room_name']) . ' since ' . e(t12($myRoom['checked_in_at'])) . '.' : 'Pick a room on the map to check in.') ?></p>
  </div>
  <div class="welcome-side">
    <span class="welcome-chip" id="welcomeChip" hidden></span>
    <?php if ($isAdmin): ?>
      <a class="btn btn-light" href="<?= e(url('admin/map_editor.php')) ?>"><?= icon('edit', 16) ?> Edit map</a>
    <?php endif; ?>
  </div>
</section>

<section class="stats">
  <?php if ($isAdmin): ?>
    <div class="stat"><span class="stat-n" id="statPeople" data-count="<?= $inRooms ?>"><?= $inRooms ?></span><span class="stat-l">Teachers in rooms</span></div>
    <div class="stat"><span class="stat-n" id="statRooms" data-count="<?= $roomsUsed ?>"><?= $roomsUsed ?></span><span class="stat-l">Rooms in use</span></div>
    <div class="stat"><span class="stat-n"><?= $clocked ?><small>/<?= $teachers ?></small></span><span class="stat-l">Clocked in today</span></div>
    <a class="stat stat-link" href="<?= e(url('admin/room_change_history.php')) ?>"><span class="stat-n"><?= pending_changes_count() ?></span><span class="stat-l">Room changes to review</span></a>
  <?php else: ?>
    <div class="stat"><span class="stat-n stat-text"><?= $myRoom ? e($myRoom['room_name']) : 'Not checked in' ?></span><span class="stat-l">Your room</span></div>
    <a class="stat stat-link" href="<?= e(url('teacher/attendance.php')) ?>"><span class="stat-n stat-text"><?= ($att && $att['state'] !== 'none') ? e($att['time_in']) : 'Not yet' ?></span><span class="stat-l">Time in today</span></a>
    <div class="stat"><span class="stat-n" id="statPeople" data-count="<?= $inRooms ?>"><?= $inRooms ?></span><span class="stat-l">Teachers in rooms</span></div>
    <a class="stat stat-link" href="<?= e(url('teacher/room_change.php')) ?>"><span class="stat-n"><?= $myPending ?></span><span class="stat-l">Pending room requests</span></a>
  <?php endif; ?>
</section>

<section class="map-layout">
  <div class="card map-card">
    <div class="card-head">
      <div class="head-title"><h2>School map</h2><span class="live"><i></i>Live <small id="mapTime"></small></span></div>
      <div class="map-tools">
        <label class="search"><?= icon('search', 16) ?><input type="search" id="findInput" placeholder="Find a teacher or room" autocomplete="off"></label>
        <div class="zoom">
          <button class="icon-btn" id="zoomIn" aria-label="Zoom in">+</button>
          <button class="icon-btn" id="zoomOut" aria-label="Zoom out">−</button>
          <button class="icon-btn" id="zoomReset" aria-label="Reset view"><?= icon('undo', 16) ?></button>
        </div>
      </div>
    </div>
    <?php if (!$isAdmin): ?>
    <div class="dock" id="dock">
      <div class="dock-row dock-att" id="attBox" data-state="<?= e($att['state'] ?? 'none') ?>">
        <span class="dock-ico"><?= icon('clock', 20) ?></span>
        <div class="dock-text"><strong id="attTitle">Not timed in yet</strong><small id="attSub">Time in to start your day.</small></div>
        <div class="dock-btns">
          <div class="btn-swap" id="attSwap">
            <button type="button" class="btn btn-primary btn-attend" id="attIn"><?= icon('check', 16) ?> <span id="attInLbl">Time in</span></button>
            <button type="button" class="btn btn-outline below" id="attOut" aria-hidden="true" tabindex="-1">Time out</button>
          </div>
        </div>
      </div>
      <div class="dock-row dock-class" id="classBox" hidden>
        <span class="dock-ico dc"><?= icon('book', 20) ?></span>
        <div class="dock-text"><strong id="clsTitle"></strong><small id="clsSub"></small></div>
        <div class="dock-btns" id="clsAct"></div>
      </div>
    </div>
    <?php endif; ?>
    <div class="map-wrap" id="mapWrap">
      <svg id="campus-map" viewBox="0 0 <?= MAP_W ?> <?= MAP_H ?>" role="img" aria-label="School floor plan with live room occupancy">
        <defs>
          <pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M20 0H0V20" fill="none" class="grid-line"/></pattern>
        </defs>
        <rect class="map-bg" width="<?= MAP_W ?>" height="<?= MAP_H ?>"/>
        <rect width="<?= MAP_W ?>" height="<?= MAP_H ?>" fill="url(#grid)"/>
        <g id="rooms-layer"></g>
      </svg>
      <div class="map-tip" id="tip" hidden></div>
    </div>
    <div class="legend">
      <span><i class="lg lg-live"></i>Someone inside</span>
      <span><i class="lg lg-classroom"></i>Classroom</span>
      <span><i class="lg lg-lab"></i>Laboratory</span>
      <span><i class="lg lg-office"></i>Office</span>
      <span><i class="lg lg-facility"></i>Facility</span>
      <span><i class="lg lg-class"></i>Class in session</span>
      <span><i class="lg lg-defect"></i>Open defect report</span>
      <?php if (!$isAdmin): ?><span><i class="lg lg-mine"></i>Your room</span><?php endif; ?>
    </div>
  </div>

  <aside class="side-col">
    <div class="card" id="roomPanel">
      <div class="panel-empty"><?= icon('map', 28) ?><p>Select a room on the map to see who is inside.</p></div>
    </div>
    <div class="card">
      <div class="card-head"><h2>Who is where</h2><span class="count-pill" id="peopleCount">0</span></div>
      <ul class="people" id="peopleList"></ul>
    </div>
    <div class="card">
      <div class="card-head"><h2><?= $isAdmin ? 'Classes today' : 'My classes today' ?></h2><span class="count-pill" id="todayCount">0</span></div>
      <ul class="classes" id="todayList"></ul>
    </div>
  </aside>
</section>

<script>window.MAP_BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<?php page_end(['map.js', 'dashboard.js']); ?>
