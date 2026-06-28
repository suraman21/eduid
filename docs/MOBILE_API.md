# EduID Mobile API — Contract for the Flutter Teacher App

This is the authoritative contract for the Flutter app, derived from the live
backend (`routes/api.php`, `app/Http/Controllers/Api/V1/*`). The web platform is
complete; this document is what the mobile app is built against.

- **Base URL:** `https://<school-host>/api/v1`
- **Auth:** Laravel Sanctum bearer tokens — `Authorization: Bearer <token>`
- **Format:** JSON request + response; `Accept: application/json`
- **Versioning:** path-prefixed (`/v1`)

## Rate limits
| Scope | Limit | On exceed |
|---|---|---|
| `POST /auth/login` | 5 / 15 min per IP+email | `429` |
| All authenticated `/v1/*` | 300 / min per user | `429` |

Respect `Retry-After` on `429`.

## Offline-first model (important)
1. After login, the app caches `/classes` and each class's `/students` roster.
2. The roster includes every student's `qr_token`. A scanned QR payload is
   `"<UID>|<token>"`; to verify **offline**, split on `|` and match `UID`+`token`
   against the cached roster. No network needed.
3. Attendance is captured offline and pushed later via `POST /attendance/sync`,
   which is **idempotent** per `idempotency_key` — safe to retry on flaky links.
4. `POST /attendance/verify-qr` is the optional **online** equivalent of step 2.

The QR payload format `UID|hmac_token` is identical to what is printed on the
PVC card; the token is an HMAC-SHA256 of `UID|academic_year` keyed by a
server-only secret, so it cannot be forged client-side.

---

## `POST /auth/login`  (public, throttled)
Request:
```json
{ "school_code": "AIA", "email": "teacher@school.edu.et", "password": "…", "device_name": "Pixel 7" }
```
Success `200`:
```json
{
  "token": "1|abc…",
  "user":   { "id": 12, "name": "…", "email": "…", "role": "teacher" },
  "school": { "id": 1, "name": "…", "code": "AIA", "calendar_system": "ethiopian", "academic_year": "2018 E.C." }
}
```
Errors: `404` unknown school code · `403` school/account deactivated ·
`401` bad credentials (generic) · `429` locked (5 failed attempts) / throttled.
Store the token in secure storage; send it on every other call.

## `GET /auth/me`  ·  `POST /auth/logout`  (auth)
`me` returns the same `user` + `school` blocks. `logout` revokes the current
token (`200`). Drop the cached token on logout.

## `GET /classes`  (auth)
Classes the user may take attendance for (teachers → assigned only; admins →
all). Cache for the offline class picker.
```json
{ "classes": [ { "id": 5, "name": "Grade 10 - A", "grade": "Grade 10", "section": "A", "student_count": 42 } ] }
```

## `GET /classes/{class}/students`  (auth)
Active roster for one class. `403` if the teacher is not assigned to it.
```json
{
  "class": { "id": 5, "name": "Grade 10 - A", "grade": "Grade 10", "section": "A" },
  "academic_year": "2018 E.C.",
  "students": [
    { "id": 88, "uid": "AIA-2026-00142", "first_name": "Abebe", "last_name": "Girma",
      "full_name": "Abebe Girma", "gender": "male", "status": "active",
      "has_photo": true, "qr_token": "9f3c…" }
  ]
}
```
Cache `uid` + `qr_token` per student for offline QR verification.

## `POST /attendance/verify-qr`  (auth) — optional online check
Request: `{ "qr_data": "AIA-2026-00142|9f3c…" }` (max 300 chars).
Always `200`:
```json
{ "valid": true,  "student": { "id": 88, "uid": "…", "full_name": "…", "status": "active", "class_id": 5, "class_name": "Grade 10 - A", "has_photo": true } }
{ "valid": false, "message": "Invalid or expired QR code." }
```

## `POST /attendance/sync`  (auth) — bulk offline upload
Request (1–100 sessions; each session 1+ records):
```json
{
  "sessions": [
    {
      "class_id": 5,
      "session_date": "2026-06-28",          // <= today
      "session_type": "morning",              // morning | afternoon | full_day
      "device_id": "pixel-7-abc",            // optional
      "idempotency_key": "uuid-v4-per-session",
      "records": [
        { "student_id": 88, "status": "present", "scanned_at": "2026-06-28T08:12:00Z", "note": null }
        // status: present | absent | late | excused
      ]
    }
  ]
}
```
Response `200` (per-session results so the app can mark each synced):
```json
{ "synced": 1, "skipped": 0, "failed": 0,
  "results": [ { "idempotency_key": "…", "status": "created", "session_id": 31, "records_saved": 1 } ] }
```
`status` per session: `created` · `merged` (a web-recorded session existed) ·
`duplicate` (key already synced — treat as success) · `error` (message included;
e.g. not authorised for the class, or a student not in that class).

**Generate one stable `idempotency_key` per offline session and reuse it on
every retry** — this is what makes re-sending safe.

---

### `GET /classes/{class_id}/students/{student_id}/photo`

Download a student's photo for offline caching. The student must belong to the
given class, be `active`, and have a photo on file.

**Response:** Raw image bytes (`image/jpeg` or `image/png`) with
`Cache-Control: private, max-age=86400`.

**Errors:** `403` class not assigned · `404` student not in class / no photo.

**Offline strategy:** After caching the roster, loop over students where
`has_photo: true` and fetch each photo. Store locally keyed by `student_id`.

---

## PIN Authentication (quick unlock)

### `POST /auth/pin/set`  (auth)
Set or update the user's 4-digit PIN. Requires current password.
```json
{ "pin": "1234", "password": "current-password" }
```
`200` success · `403` wrong password · `422` invalid PIN format.

### `POST /auth/pin/verify`  (auth)
Verify PIN for quick app unlock. 5 failures → lock + token revocation → full
re-auth required.
```json
{ "pin": "1234" }
```
`200` verified · `401` wrong PIN (includes `attempts_remaining`) ·
`422` no PIN configured · `423` locked (token revoked, must re-login).

---

## Grades

### `GET /classes/{class_id}/grades`  (auth)
Gradebook for a class. Optional `?term_id=X` (defaults to current term).
`403` if teacher not assigned to class.
```json
{
  "class": { "id": 5, "name": "Grade 10 - A" },
  "term": { "id": 1, "name": "Term 1", "academic_year": "2018 E.C." },
  "weights": { "ca": 40, "midterm": 20, "final": 40 },
  "students": [
    {
      "student_id": 88, "student_uid": "AIA-12345", "full_name": "Abebe Girma",
      "subjects": [
        {
          "subject_id": 3, "subject_name": "Math",
          "ca": { "score": 35, "max": 40 },
          "midterm": { "score": 18, "max": 20 },
          "final": { "score": 38, "max": 40 },
          "total": 91.0, "letter": "A"
        }
      ],
      "term_avg": 91.0, "rank": 1
    }
  ]
}
```

---

## Schedule / Timetable

### `GET /schedule/today`  (auth)
Today's periods for the teacher. Each period shows the entry (class, subject,
room) if the teacher is assigned, or `null` for free/break periods.
```json
{
  "day_of_week": 1,
  "date": "2026-06-28",
  "schedule": [
    {
      "period_id": 1, "label": "Period 1",
      "start_time": "08:00:00", "end_time": "08:45:00", "is_break": false,
      "entry": { "class_name": "Grade 5 - A", "class_id": 3, "subject_name": "Math", "room": "A101" }
    },
    { "period_id": 2, "label": "Break", "start_time": "08:45:00", "end_time": "09:00:00", "is_break": true, "entry": null }
  ]
}
```

### `GET /schedule/week`  (auth)
Full weekly schedule. Cache for offline display.
```json
{ "week": [ { "day": 1, "periods": [ /* same shape as today */ ] }, /* ... days 1-7 */ ] }
```

---

## Error envelope & conventions
- `401` → token missing/expired → route the user back to login.
- `403` → not permitted (e.g. class not assigned, account/school deactivated).
- `422` → validation error (Laravel shape: `{ "message": "...", "errors": { field: [..] } }`).
- `423` → PIN locked — token revoked, must re-authenticate with password.
- `429` → backoff using `Retry-After`.
- `verify-qr` deliberately returns `200` with `valid:false` rather than a 4xx,
  so the scanner UI handles invalid cards inline.

## Not yet in the API (build server-side first if the app needs them)
- Staff (teacher) attendance capture, if that becomes an app feature.
