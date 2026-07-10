import 'dart:async';

import 'package:flutter/foundation.dart';

import 'attendance_service.dart';
import 'data_service.dart';

enum SyncStatus { idle, syncing, offline }

/// Automatic background sync — the user never has to press a button.
///
/// Triggers: app open / sign-in, every 3 minutes while the app is alive, and
/// immediately after attendance is saved (push-only, so it's instant). All
/// failures are silent — the badge just shows the offline/pending state and
/// the next tick retries.
class SyncEngine extends ChangeNotifier {
  SyncEngine._();
  static final SyncEngine instance = SyncEngine._();

  static const _interval = Duration(minutes: 3);

  Timer? _timer;
  bool _running = false;

  SyncStatus status = SyncStatus.idle;
  DateTime? lastSync;
  int pending = 0;

  /// True while the FIRST successful pull is still outstanding and we are not
  /// in a known-offline state — i.e. the cache may still be about to fill.
  /// Cache-backed screens use this to keep showing a skeleton instead of a
  /// premature "nothing here" empty state right after launch.
  bool get initialSyncPending =>
      lastSync == null && status != SyncStatus.offline;

  void start() {
    _timer?.cancel();
    _timer = Timer.periodic(_interval, (_) => sync());
    sync(); // immediate first pass
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
    status = SyncStatus.idle;
    lastSync = null;
    pending = 0;
    notifyListeners();
  }

  /// Push queued attendance shortly after it is saved (no full pull — instant).
  void pushSoon() {
    Timer(const Duration(seconds: 1), () => sync(pull: false));
  }

  Future<void> sync({bool pull = true}) async {
    if (_running) return;
    _running = true;
    status = SyncStatus.syncing;
    notifyListeners();

    try {
      await AttendanceService.instance.sync();
      if (pull) {
        await DataService.instance.refreshAll();
      }
      lastSync = DateTime.now();
      status = SyncStatus.idle;
    } catch (_) {
      // Offline or server unreachable — the next tick retries automatically.
      status = SyncStatus.offline;
    }

    pending = await AttendanceService.instance.pendingCount();
    _running = false;
    notifyListeners();
  }
}
