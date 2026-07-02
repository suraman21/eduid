# EduID Teacher (Flutter)

Offline-first mobile app for teachers: **attendance (QR + manual P/A/L/E),
grades, schedule, sync** — built against the API contract in
[`../docs/MOBILE_API.md`](../docs/MOBILE_API.md).

## Getting started (one-time setup)

The repo intentionally contains only the Dart source (`lib/`, `pubspec.yaml`) —
platform folders are generated on your machine:

```bash
cd mobile
flutter create . --org com.arkeon --project-name eduid_teacher --platforms android,ios
flutter pub get
```

### Required platform permissions (QR camera)

**Android** — add inside `<manifest>` in `android/app/src/main/AndroidManifest.xml`:
```xml
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.INTERNET" />
```
Also set `minSdkVersion 21` (mobile_scanner requirement) in
`android/app/build.gradle` if it is lower.

**iOS** — add to `ios/Runner/Info.plist`:
```xml
<key>NSCameraUsageDescription</key>
<string>Scan student ID cards to take attendance.</string>
```

### Run

```bash
flutter run           # device/emulator attached
flutter build apk     # release APK for distribution
```

The server defaults to `https://arkeoid.arkeonethiopia.com/api/v1` and can be
changed in **Settings → Server** inside the app.

## Architecture

```
lib/
  core/
    api_client.dart        Dio + Sanctum bearer token + configurable base URL
    db.dart                sqlite: classes, rosters (qr_token), attendance queue
  models/models.dart       API payload models (SchoolClass, Student, PeriodSlot)
  services/
    auth_service.dart      login, session restore, PIN set/unlock (offline-capable)
    data_service.dart      pull sync + cached reads (classes/rosters/schedule/grades)
    attendance_service.dart offline QR verify, session queue, idempotent push sync
  screens/
    login → pin → home → roster (P/A/L/E + scanner) / schedule / grades / settings
```

## The offline-first model (matches the PRD)

1. After login the app pulls classes + rosters (each student's `qr_token`
   comes along) into sqlite.
2. **QR verification is local**: scanned `UID|token` is matched against the
   cached roster — no network needed in the classroom.
3. Attendance sessions are queued locally with a stable **idempotency key**;
   `POST /attendance/sync` makes retries safe on flaky links (`duplicate`
   responses count as success).
4. The **PIN** is registered server-side (bcrypt, 5-attempt lockout) *and*
   salted-hashed locally so quick-unlock works offline (also 5-attempt capped;
   exceeding it forces a full password sign-in).
5. Signing out wipes the local cache (shared-device safety) — the app warns if
   unsynced attendance would be lost.
