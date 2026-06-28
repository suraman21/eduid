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

## Error envelope & conventions
- `401` → token missing/expired → route the user back to login.
- `403` → not permitted (e.g. class not assigned, account/school deactivated).
- `422` → validation error (Laravel shape: `{ "message": "...", "errors": { field: [..] } }`).
- `429` → backoff using `Retry-After`.
- `verify-qr` deliberately returns `200` with `valid:false` rather than a 4xx,
  so the scanner UI handles invalid cards inline.

## Not yet in the API (build server-side first if the app needs them)
- Pushing the student photo bytes to the app (only `has_photo` is exposed today;
  add a tokened photo endpoint if the app must render faces offline).
- Staff (teacher) attendance capture, if that becomes an app feature.
