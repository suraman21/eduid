# EduID — Manual Test Checklist (Live Server)

Walk this top-to-bottom after any significant deploy. It covers all four
role journeys end-to-end and takes roughly **45–60 minutes** with one test
school. Every step says what to do and exactly what you should see.

> **Golden rule while testing:** use a dedicated TEST school (e.g. code
> `TEST`), never a paying school — the Year Transition step kills real QR
> cards.

---

## 0 · Deploy verification (2 min)

- [ ] Run the standard deploy command, then:
  `/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate:status` → **no row says "Pending"**.
- [ ] Open `https://arkeoid.arkeonethiopia.com/api/v1/health` → `"status": "ok"`, `"pending_migrations": 0`.
- [ ] Load the public landing page — no console errors, styles intact
  (if unstyled, the assets commit from GitHub Actions hasn't landed; re-pull).

---

## 1 · Super Admin journey (10 min)

Sign in at `/admin/login` (email + password + emailed OTP).

- [ ] **2FA**: OTP email arrives; wrong code rejected; correct code lands on the dark dashboard.
- [ ] **Schools**: create/verify the TEST school — set plan **Premium** (unlocks every module for this walkthrough), calendar, accent colour.
- [ ] **Packages** (`/admin/packages`):
  - [ ] Three plan cards show; edit Starter's price → save → reload → **price stuck** (survives deploys now).
  - [ ] Feature matrix: toggle any module off for Starter → cell flips; toggle back.
  - [ ] School packages table: TEST school row shows seats used/max; try setting teacher seats **below current usage** → red error, value unchanged.
- [ ] **Features** (`/admin/features?school_id=…`): matrix shows real ticks per tier (not all dashes); add an override for TEST school, remove it.
- [ ] **Card orders**: open an order (create one as school admin later, then return) → Process by code → Confirm → status moves Pending → Confirmed; sheet-print wizard opens; calibration PDF downloads.
- [ ] **Audit log**: your actions above appear with your user.
- [ ] **Dual session**: while logged in as super admin, log into the school portal in the same browser → both sessions work; sign out of one → the other survives.

## 2 · School Admin journey (15 min)

Sign in at `/login` with school code `TEST`.

- [ ] **Dashboard** shows admin widgets (students, staff, orders, plan pill).
- [ ] **Sidebar** shows: Students, Classes, Subjects, Staff, Attendance, Timetable, Grades, Report Cards, Analytics, Card Orders, Photo Sessions, Import, New Year.
- [ ] **Classes**: create `Grade 1 - A` and `Grade 12 - A`.
- [ ] **Subjects**: create English, Maths, Amharic.
- [ ] **Staff → New**: role Teacher →
  - [ ] **Teaching Assignments** section appears: add `Grade 1 - A × English`, tick `Grade 1 - A` under **Homeroom of**.
  - [ ] Save → profile shows "Grade 1 - A → English · Homeroom".
  - [ ] Edit your own admin account → try switching role to Teacher → **blocked** ("cannot demote your own account").
- [ ] **Students**: add 2–3 students into Grade 1 - A; add a guardian to one.
- [ ] **CSV Import**: download template, import 2 rows → success. Then set the school's `max_students` low from Admin → Packages and re-import → rows **capped with a loud message**; restore seats.
- [ ] **Timetable**: add Period 1 (08:00–08:40) + a Break; fill Monday Grade 1-A with English → teacher auto-suggested; try booking the same teacher into another class same slot → **double-booking blocked** with their name.
- [ ] **Attendance**: record Grade 1 - A morning: one student absent.
- [ ] **Grades**: create a current Term; enter + publish English marks.
- [ ] **Analytics**: rate chart shows today's point; the absent student tops the hotspot list.
- [ ] **Card Orders**: create an order → confirmation code displayed (take it to step 1's fulfilment checks).
- [ ] **Guardian accounts** (student page → guardian):
  - [ ] **Enable app access** → email prompt → success flash shows a **one-time temp password** (copy it for step 4).
  - [ ] **Telegram link** button copies a `t.me/...` link (needs bot env vars; if unset you get a friendly "not configured" notice — that's the expected degraded state).
- [ ] **Profile**: change your name; change password (requires current password).

## 3 · Teacher journey — web + mobile (10 min)

Web: sign in as the teacher you created.

- [ ] **Teacher dashboard** (not the admin one): your class card with homeroom badge, attendance-taken state, today's timetable from step 2.
- [ ] **Sidebar** shows ONLY Dashboard, Attendance, Grades, My Profile — no student search box.
- [ ] Direct-URL probes all give 403: `/school/students`, `/school/staff`, `/school/card-orders`, `/school/students/import`, `/school/timetable`.
- [ ] **Attendance**: class dropdown lists ONLY your class; record afternoon attendance.
- [ ] **Grades → Enter**: works for your English slot; pick a subject slot owned by another teacher → **403**.

Mobile (EduID app, `flutter run --release`):

- [ ] Sign in (school code + email + password) → light theme home, your class listed, stats row.
- [ ] **Scan attendance** quick action → camera opens → scanning is continuous ("Done — N scanned").
- [ ] Mark attendance offline (airplane mode) → save → shows queued → reconnect → badge flips to synced automatically within ~1 min.
- [ ] Set a PIN (password → choose → re-enter); Settings → **Auto-lock → 1 minute**; background the app >1 min → PIN gate on return.
- [ ] Schedule tab shows the timetable you built.

## 4 · Parent journey — mobile (5 min)

- [ ] Sign in on the app with the guardian email + the one-time temp password from step 2 → **My Children** home (not the teacher home).
- [ ] Child card → **Attendance** tab: month summary + the absence you recorded; **Grades** tab: only the PUBLISHED English mark (drafts invisible).
- [ ] Parent web login at `/login` → refused with "sign in through the EduID mobile app".
- [ ] Revoke app access from the guardian card → the app gets signed out on next request.

## 5 · Telegram alerts (5 min — needs bot env vars + cron)

- [ ] Open the guardian's personal Telegram link → tap START → bot replies "✅ You're connected"; guardian card badge becomes **Alerts on**.
- [ ] Record an absence for their child → Telegram message arrives within seconds (inline flush) or ≤5 min (cron).
- [ ] Re-save the same attendance → **no duplicate message**.

## 6 · Year Transition (5 min — TEST school only!)

- [ ] School Admin → **New Year**: suggested year label correct; map Grade 1→2 (Promote), Grade 12 → Graduate; review totals; typing the year unlocks the red button.
- [ ] After running: dashboard greets the new year; students sit in the new classes; graduate is off the active roster; **old QR card no longer scans** in the teacher app; timetable grid is empty for the new year (bell schedule kept); teacher needs re-assignment (staff page shows none).

## 7 · Error & edge sweep (3 min)

- [ ] Visit a URL for another school's resource id → **404**, never data.
- [ ] Let a session idle past lifetime, then submit a form → friendly re-login redirect (no raw 419).
- [ ] Force an error page (e.g. `/school/nonexistent`) → branded error screen with an `EDU-…` support code.

---

**When something fails:** note the URL, the role you were signed in as, and
any `EDU-ERR-XXXXXXXX` code from the error screen — that code pinpoints the
exact log entry in `storage/logs/laravel-<date>.log`.
