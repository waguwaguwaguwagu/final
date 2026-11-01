# Check-In Portal

PHP 8+ and MySQL/MariaDB. No framework or build step.

## Install (new)
1. Copy this folder into your web root (for example `htdocs/school-checkin`).
2. Edit `config.php` with your MySQL host, user, password, school name and timezone.
3. Open `http://localhost/school-checkin/setup.php`. It creates the database, tables and the campus map,
   then asks you to create the first administrator. (Or import `schema.sql` yourself.)
4. Delete `setup.php`, then sign in at `login.php`.

## Upgrade (you already have the previous version)
1. Back up your database.
2. Copy these files over the old ones. **Keep your own `config.php`** (the new settings in it are optional;
   see "Settings" below).
3. Open `setup.php` once more. It leaves your accounts and history alone. It adds the defect report table, ties room
   changes to subjects and dates, and lets a teacher time in and out more than once a day (it replaces the old
   one-record-per-day rule on the attendance table).
4. Make sure the `uploads/` folder exists and the web server can write to it (the map editor saves your reference image there).
5. Delete `setup.php` again.
6. Optional: copy the new lines from `config.php` (`SCHOOL_DAYS` now lists all seven days, plus `CLASS_DAY_START` and friends).

## Using it
- **Teachers**: Campus Map (check in/out of rooms, and **Time in / Time out** right on the map), My Attendance,
  Room Check-In, Room Change Request, **Report a Defect**, My Schedule, Schedule Request, Experiment.
- **Admins**: Campus Map, Subject Schedule, Schedule Requests, Attendance / Check-In / Room Change history
  (filters + CSV export), **Defect Reports**, Accounts (create teachers, reset passwords, deactivate), Map Editor, Experiment.
- **Menu button** (top left): on a computer it hides or shows the side menu and remembers your choice; on a phone or
  narrow window it opens the menu as a drawer (Esc or a tap outside closes it).
- **Occupied rooms** glow **magenta** on the map. No room type uses that colour. Your own room keeps its gold outline.
- Map Editor: drag rooms, drag a corner to resize, rename in the side panel, add or remove rooms, then **Save map**.
  Everyone sees the new layout within about 5 seconds.
- **Reference image** (Map Editor): upload a photo or scan to trace from (or drop one onto the map). It is shrunk in your
  browser first, so phone photos are fine. Use Rotate, Size, Left / right and Up / down to line it up. It is saved for all
  admins and never shown on the live map. **Use the sketch** removes it, and the original hand-drawn sketch is the
  placeholder whenever no picture has been uploaded.

## Time in / Time out
Teachers can time in and out as many times as they like in a day (arriving, leaving for an errand, coming back). Each pair
is one record. Only the first time in of the day can be marked late. On the map, the button you just used slides up and
away and the other one slides up in its place. Timing out also checks you out of your room.

## Defect reports (facility and equipment)
- A teacher reports a problem from **Report a Defect** (or **Report a defect** in a room's panel on the map): the room,
  whether it is a **facility** (ceiling, door, aircon...) or **equipment** (projector, computer...), what is affected, how
  serious (low, medium, high, urgent) and a description. A duplicate of something still open in that room is refused politely.
- Rooms with open reports show a small warning triangle on the map (red for high or urgent).
- Admins see them most serious first in **Defect Reports**: **Start work**, **Mark fixed**, **Dismiss**, each with an optional
  note the teacher can read. History has filters, **Reopen** and CSV export. Teachers can withdraw a report that is still new.

## Room changes are tied to subjects
A **Room Change Request** is for one of the teacher's classes on one date: choose the class, the date it meets, and the new
room, plus a reason. It changes only that day; the timetable is not touched (use Schedule Request to move a class for good).
The system checks the new room is free during the class when you send, and again when an admin approves. Once approved,
the map, the class lists and the teacher's dashboard show the class in the new room on that date, and a teacher who is
checked in to the old room during that class is moved over automatically. **My Schedule** has a *Room change* button on every meeting.

## Subject schedule rules
- The calendar shows **Monday to Sunday**. Classes run **8:00 AM to 6:00 PM** and **12:00 PM to 1:00 PM is always free**
  (both can be changed in `config.php`).
- There is no major / minor type any more. A subject meets **one or more times a week** (up to 8), and **every meeting has its
  own day, start time, end time and room**. Times go in steps of 5 minutes, and a class lasts at least 15 minutes, so 45-minute,
  1.5-hour or 3-hour classes are all fine. The **length** buttons (45 min, 1 hr, 1½ hr, 2 hrs, 3 hrs, 4 hrs) just fill in the end time.
- A teacher, a room, or a section (for example "Grade 10 - Rizal") cannot be in two classes at the same time, and two meetings
  of one subject cannot overlap. The forms check this live and explain the clash before you save.
- Admins add subjects in **Subject Schedule** (code, name, teacher, section, then the meetings). Click any class on the timetable to
  edit it; you can change times and add or remove meetings at any time. Every subject gets its own colour automatically.
- Teachers ask to move one class in **Schedule Request** (new day, start time, end time and/or room, plus a reason; the end time
  follows the start so the class keeps its length, and can be adjusted). Admins approve or deny in **Schedule Requests**. The
  timetable is re-checked at the moment of approval, so an approval can never create a clash.
- On the map, a room with a class in session shows an amber marker, the room panel lists that day's classes there,
  and a teacher whose class is on gets a one-tap "Check in to Room X" prompt.

## Experiment (test clock)
Every account (teacher or admin) has an **Experiment** page that gives *that account* a pretend clock: set any date and time,
keep it running or freeze it, nudge it, or jump to useful moments (just before or after the late cutoff, lunch, after hours,
or 5 minutes before your next class). The whole app for that account follows the pretend time: attendance late/on-time, class
"now / up next", the map's in-session markers, and the timetable's "now" line. A purple banner shows while it is on.

- It only affects the account that turned it on, and it ends when that account signs out.
- Anything created while it is on (check-ins, attendance, room changes, defect reports, schedule requests) is tagged **test** and can be
  cleared in one click on the Experiment page. Clearing also puts back any class times that were changed by a test approval.
  Admins clear everyone's test records, teachers only their own.
- Set `EXPERIMENT_ENABLED` to `false` in `config.php` before going live to remove it everywhere.

## Settings in config.php
- `SCHOOL_DAYS` - days shown on the calendar and allowed for classes (1 = Monday ... 7 = Sunday). Default Monday to Sunday.
- `CLASS_DAY_START`, `CLASS_DAY_END` - the hours classes may run (24-hour `HH:MM`, default `08:00` to `18:00`).
- `CLASS_LUNCH_START`, `CLASS_LUNCH_END` - the break that always stays free (default `12:00` to `13:00`; make them equal to turn it off).
- `EXPERIMENT_ENABLED` - `true` or `false`.
- `LATE_AFTER` (unchanged) - time-ins after this are marked late. It is still 7:30 AM from the earlier version.
