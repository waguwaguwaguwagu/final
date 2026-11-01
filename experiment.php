<?php
require __DIR__ . '/includes/layout.php';
$u = require_login();
$uid = (int)$u['id'];
$isAdmin = $u['role'] === 'admin';

if (!EXPERIMENT_ENABLED) {
    flash('The experiment tools are turned off in config.php.', 'err');
    redirect('dashboard.php');
}

/* Quick jumps: a time of day, kept on whatever date the clock shows now. */
$lateTs  = strtotime('2000-01-01 ' . LATE_AFTER);
$presets = [
    'before_late' => ['Just before the late cutoff', date('H:i', $lateTs - 300)],
    'after_late'  => ['Just after the late cutoff',  date('H:i', $lateTs + 300)],
    'open'        => ['Classes begin',               '08:00'],
    'midmorning'  => ['Mid-morning',                 '10:15'],
    'lunch'       => ['Lunch break',                 '12:15'],
    'afternoon'   => ['Afternoon classes start',     '13:00'],
    'closing'     => ['Almost closing time',         '17:45'],
    'after'       => ['After hours',                 '18:30'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $act = $_POST['action'] ?? '';
    $x = experiment_state();
    $frozenNow = $x ? !empty($x['frozen']) : false;

    if ($act === 'set') {
        $date = (string)($_POST['date'] ?? '');
        $time = (string)($_POST['time'] ?? '');
        $freeze = ($_POST['mode'] ?? 'run') === 'freeze';
        $ts = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d{2}:\d{2}$/', $time)) ? strtotime($date . ' ' . $time . ':00') : false;
        if ($ts === false) flash('Enter a valid date and time.', 'err');
        else { set_experiment($ts, $freeze); flash('The clock now reads ' . date('D, M j, g:i A', $ts) . ($freeze ? ' and is frozen.' : ' and keeps running.')); }
    } elseif ($act === 'nudge') {
        $mins = max(-10080, min(10080, (int)($_POST['minutes'] ?? 0)));
        set_experiment(vtime() + $mins * 60, $frozenNow);
        flash('The clock now reads ' . vdate('D, M j, g:i A') . '.');
    } elseif ($act === 'preset' && isset($presets[$_POST['key'] ?? ''])) {
        [$label, $hhmm] = $presets[$_POST['key']];
        $ts = strtotime(vdate('Y-m-d') . ' ' . $hhmm . ':00');
        set_experiment($ts, $frozenNow);
        flash('Jumped to ' . strtolower($label) . ': ' . date('g:i A', $ts) . '.');
    } elseif ($act === 'jump_class' && !$isAdmin) {
        $cs = class_status($uid);
        if ($cs['next']) {
            $ts = (int)($cs['next']['starts_at'] / 1000) - 300;
            set_experiment($ts, $frozenNow);
            flash('Jumped to 5 minutes before ' . ($cs['next']['code'] !== '' ? $cs['next']['code'] : $cs['next']['name']) . '.');
        } else {
            flash('You have no upcoming class to jump to.', 'err');
        }
    } elseif ($act === 'freeze' || $act === 'run') {
        set_experiment(vtime(), $act === 'freeze');
        flash($act === 'freeze' ? 'The clock is frozen.' : 'The clock is running again.');
    } elseif ($act === 'reset') {
        clear_experiment();
        $msg = 'Back to real time.';
        if (!empty($_POST['cleanup'])) {
            $n = clear_test_records($u);
            $msg .= ' Removed ' . array_sum(array_slice($n, 0, 5)) . ' test record(s).';
        }
        flash($msg);
    } elseif ($act === 'cleanup') {
        $n = clear_test_records($u);
        flash('Removed ' . array_sum(array_slice($n, 0, 5)) . ' test record(s)' . ($n['reverted'] ? ' and undid ' . $n['reverted'] . ' schedule change(s)' : '') . '.');
    }
    redirect('experiment.php');
}

/* ---------- what the system sees right now ---------- */
$x = experiment_state();
$vt = vtime();
$dow = (int)date('N', $vt);
$mins = (int)date('G', $vt) * 60 + (int)date('i', $vt);
$schoolDay = isset(sched_days()[$dow]);
$inHours = $schoolDay && $mins >= DAY_START_MIN && $mins < DAY_END_MIN;
$inLunch = $schoolDay && $mins >= LUNCH_START_MIN && $mins < LUNCH_END_MIN;
$wouldBeLate = date('H:i:s', $vt) > LATE_AFTER;

$cs = ['current' => null, 'next' => null];
$inSession = 0;
try {
    if (!$isAdmin) $cs = class_status($uid);
    $inSession = count(array_filter(to_blocks(sessions_query(' AND ss.day_of_week = ?', [$dow])), fn($b) => $mins >= $b['start'] && $mins < $b['end']));
} catch (Throwable $ex) { /* schedule tables are created by setup.php */ }

$cnt = 0;
$own = $isAdmin ? '' : ' AND user_id = ' . $uid;
try {
    foreach (['checkins', 'attendance', 'room_changes', 'schedule_requests', 'defect_reports'] as $t) {
        $cnt += (int)db()->query('SELECT COUNT(*) FROM `' . $t . '` WHERE is_test = 1' . $own)->fetchColumn();
    }
} catch (Throwable $ex) { /* is_test columns are added by setup.php */ }

page_start('Experiment', 'experiment', 'Give this account a pretend clock to test late arrivals, class times, lunch and more. Each account has its own clock and it resets when you sign out.');
?>
<section class="xlab">
  <div class="card xclock-card <?= $x ? 'on' : '' ?>">
    <div class="xclock-top">
      <span class="xstate"><i></i><?= $x ? (!empty($x['frozen']) ? 'Clock frozen' : 'Experiment clock running') : 'Using real time' ?></span>
    </div>
    <svg class="ana" viewBox="0 0 100 100" id="anaClock" aria-hidden="true">
      <circle class="ana-face" cx="50" cy="50" r="46"/>
      <?php for ($i = 0; $i < 12; $i++): $a = deg2rad($i * 30); ?>
        <line class="ana-tick" x1="<?= round(50 + 38 * sin($a), 2) ?>" y1="<?= round(50 - 38 * cos($a), 2) ?>" x2="<?= round(50 + 43 * sin($a), 2) ?>" y2="<?= round(50 - 43 * cos($a), 2) ?>"/>
      <?php endfor; ?>
      <line class="ana-h" id="anaH" x1="50" y1="50" x2="50" y2="29"/>
      <line class="ana-m" id="anaM" x1="50" y1="50" x2="50" y2="18"/>
      <line class="ana-s" id="anaS" x1="50" y1="57" x2="50" y2="14"/>
      <circle class="ana-c" cx="50" cy="50" r="3"/>
    </svg>
    <div class="xclock-time" id="bigClock"><?= e(vdate('g:i:s A')) ?></div>
    <div class="xclock-date"><?= e(vdate('l, F j, Y')) ?></div>
    <div class="xclock-actions">
      <?php if ($x): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="<?= !empty($x['frozen']) ? 'run' : 'freeze' ?>">
          <button class="btn btn-light"><?= icon(!empty($x['frozen']) ? 'play' : 'pause', 16) ?> <?= !empty($x['frozen']) ? 'Let it run' : 'Freeze' ?></button></form>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reset">
          <button class="btn btn-light"><?= icon('undo', 16) ?> Back to real time</button></form>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Set the clock</h2></div>
    <form method="post" class="form">
      <?= csrf_field() ?><input type="hidden" name="action" value="set">
      <div class="grid-2">
        <label class="field"><span>Date</span><input type="date" name="date" value="<?= e(vdate('Y-m-d')) ?>" required></label>
        <label class="field"><span>Time</span><input type="time" name="time" value="<?= e(vdate('H:i')) ?>" required></label>
      </div>
      <div class="field"><span>Clock behavior</span>
        <div class="seg">
          <label class="seg-opt"><input type="radio" name="mode" value="run" <?= (!$x || empty($x['frozen'])) ? 'checked' : '' ?>><span>Keep running</span></label>
          <label class="seg-opt"><input type="radio" name="mode" value="freeze" <?= ($x && !empty($x['frozen'])) ? 'checked' : '' ?>><span>Freeze here</span></label>
        </div>
      </div>
      <button class="btn btn-primary"><?= icon('flask', 16) ?> Start experiment</button>
    </form>

    <div class="xsec">
      <h3>Nudge</h3>
      <form method="post" class="chips-row"><?= csrf_field() ?><input type="hidden" name="action" value="nudge">
        <?php foreach ([[-1440, '−1 day'], [-60, '−1 hr'], [-15, '−15 min'], [15, '+15 min'], [60, '+1 hr'], [1440, '+1 day']] as [$m, $lbl]): ?>
          <button class="btn btn-sm btn-outline" name="minutes" value="<?= $m ?>"><?= e($lbl) ?></button>
        <?php endforeach; ?>
      </form>
    </div>

    <div class="xsec">
      <h3>Jump to a time today</h3>
      <form method="post" class="chips-row"><?= csrf_field() ?><input type="hidden" name="action" value="preset">
        <?php foreach ($presets as $k => [$lbl, $hhmm]): ?>
          <button class="btn btn-sm btn-outline" name="key" value="<?= e($k) ?>"><?= e($lbl) ?> <small class="muted"><?= e(date('g:i A', strtotime($hhmm))) ?></small></button>
        <?php endforeach; ?>
      </form>
      <?php if (!$isAdmin): ?>
        <form method="post" class="xjump"><?= csrf_field() ?><input type="hidden" name="action" value="jump_class">
          <button class="btn btn-sm btn-primary" <?= $cs['next'] ? '' : 'disabled' ?>><?= icon('book', 16) ?> Jump to 5 minutes before my next class</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="two-col">
  <div class="card">
    <div class="card-head"><h2>What the system sees</h2></div>
    <ul class="diag">
      <li class="<?= $schoolDay ? 'yes' : 'no' ?>"><i></i><span>School day</span><b><?= e(date('l', $vt)) ?> is <?= $schoolDay ? 'a school day' : 'not a school day' ?></b></li>
      <li class="<?= $inHours ? 'yes' : 'no' ?>"><i></i><span>Working hours (<?= e(min_label(DAY_START_MIN)) ?> to <?= e(min_label(DAY_END_MIN)) ?>)</span><b><?= $inHours ? 'Inside' : 'Outside' ?></b></li>
      <li class="<?= $inLunch ? 'warn' : 'yes' ?>"><i></i><span>Lunch break (<?= e(min_label(LUNCH_START_MIN)) ?> to <?= e(min_label(LUNCH_END_MIN)) ?>)</span><b><?= $inLunch ? 'On lunch now' : 'Not lunch' ?></b></li>
      <li class="<?= $wouldBeLate ? 'warn' : 'yes' ?>"><i></i><span>Time in right now would be</span><b><?= $wouldBeLate ? 'Late' : 'On time' ?> <small>(cutoff <?= e(date('g:i A', $lateTs)) ?>)</small></b></li>
      <?php if ($isAdmin): ?>
        <li class="<?= $inSession ? 'yes' : 'no' ?>"><i></i><span>Classes in session</span><b><?= (int)$inSession ?></b></li>
      <?php else: ?>
        <li class="<?= $cs['current'] ? 'yes' : 'no' ?>"><i></i><span>Your class right now</span><b><?= $cs['current'] ? e(($cs['current']['code'] !== '' ? $cs['current']['code'] . ' · ' : '') . $cs['current']['name']) . ' until ' . e($cs['current']['end']) : 'None' ?></b></li>
        <li class="<?= $cs['next'] ? 'yes' : 'no' ?>"><i></i><span>Your next class</span><b><?= $cs['next'] ? e(($cs['next']['code'] !== '' ? $cs['next']['code'] . ' · ' : '') . $cs['next']['name']) . ', ' . e(substr($cs['next']['day'], 0, 3) . ' ' . $cs['next']['start']) : 'None scheduled' ?></b></li>
      <?php endif; ?>
    </ul>
  </div>

  <div class="card">
    <div class="card-head"><h2>Test records</h2><span class="count-pill"><?= (int)$cnt ?></span></div>
    <p class="pad-text">Check-ins, attendance, room changes, defect reports and schedule requests made while the experiment clock is on are tagged <span class="pill pill-test">test</span> so they never get mixed up with real records.</p>
    <p class="pad-text">Clearing removes them and puts back any class times that were changed by a test approval.<?= $isAdmin ? ' As an administrator this clears test records for everyone.' : ' This clears only your own.' ?></p>
    <form method="post" class="btn-row" onsubmit="return confirm('Remove all test records?');">
      <?= csrf_field() ?><input type="hidden" name="action" value="cleanup">
      <button class="btn btn-danger-o" <?= $cnt ? '' : 'disabled' ?>><?= icon('trash', 16) ?> Clear test records</button>
    </form>
  </div>
</section>
<?php page_end(); ?>
