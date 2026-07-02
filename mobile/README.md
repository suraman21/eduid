# EduID (Flutter)

The EduID school companion app — one app for every role. Teachers get
offline-first attendance, grades and schedule; parents (accounts created by
the school on a Premium plan) see their children's attendance and published
grades. Offline-first: **attendance (continuous QR scan +
manual P/A/L/E), grades, schedule, automatic sync** — built against the API
contract in [`../docs/MOBILE_API.md`](../docs/MOBILE_API.md).

> **Performance note:** always judge speed with a release build —
> `flutter run --release` (or `flutter build apk --release`). Debug builds
> (`flutter run`) run 5–10× slower and feel laggy on any phone.

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

## Troubleshooting (Windows)

**`cd mobile` says the path doesn't exist** — your checkout is older than the
app; run `git pull origin main` in the repo root first. If you already ran
`flutter create .` in the repo ROOT by accident, delete the scaffold it made
there (android, ios, lib, linux, macos, windows, web, test, build, .dart_tool,
.idea, .metadata, .flutter-plugins-dependencies, pubspec.yaml, pubspec.lock,
eduid*.iml — root copies only) before pulling.

**`[CXX1101] NDK … did not have a source.properties file`** — a half-downloaded
NDK (flaky connection). Delete the folder it names, e.g.
`C:\Users\<you>\AppData\Local\Android\Sdk\ndk\28.2.13676358`, then build
again on a stable connection and Gradle re-downloads it. Alternatively install
"NDK (Side by side)" from Android Studio → SDK Manager → SDK Tools.

**Maven/Gradle `Could not GET/HEAD …repo.maven.apache.org`** — network dropped
mid-build; just re-run `flutter build apk` once connectivity is stable (Gradle
resumes from its cache).

**On Windows use `--platforms android`** (iOS builds need a Mac):
```powershell
flutter create . --org com.arkeon --project-name eduid_teacher --platforms android
```

**Chrome/web target is not supported** — the app uses the camera scanner,
sqflite and secure storage, which are built for Android/iOS. Always test on a
real phone or Android emulator.
