<?php
/* ---------- Edit these for your server ---------- */
define('DB_HOST', 'localhost');
define('DB_NAME', 'school_checkin');
define('DB_USER', 'root');
define('DB_PASS', '');

define('SCHOOL_NAME', 'Your School Name');   // shown on the login page and sidebar
define('APP_NAME',    'Check-In Portal');
define('TIMEZONE',    'Asia/Manila');
define('LATE_AFTER',  '07:30:00');            // time-in after this is marked "late"

// Leave null to auto-detect the folder URL. Set e.g. '/school-checkin' if links break.
define('BASE_URL_OVERRIDE', null);

/* ---------- Schedule & experiment settings ---------- */
// Days shown on the schedule calendar and allowed for classes: 1 = Monday ... 7 = Sunday.
define('SCHOOL_DAYS', [1, 2, 3, 4, 5, 6, 7]);

// Optional: the hours classes may run, and the break that always stays free (24-hour HH:MM).
// Set CLASS_LUNCH_START and CLASS_LUNCH_END to the same time to turn the free break off.
define('CLASS_DAY_START',   '08:00');
define('CLASS_DAY_END',     '18:00');
define('CLASS_LUNCH_START', '12:00');
define('CLASS_LUNCH_END',   '13:00');

// The Experiment page lets any account run on a pretend clock for testing.
// Set to false before you go live to hide it everywhere.
define('EXPERIMENT_ENABLED', true);
