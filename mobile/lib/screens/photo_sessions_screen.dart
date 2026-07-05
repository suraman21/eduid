import 'package:flutter/material.dart';

import '../services/photo_service.dart';
import '../ui/kit.dart';
import '../ui/skeleton.dart';
import 'photo_capture_screen.dart';

/// Photo Session Mode (school admins). Shows the active session created on
/// the web — class, progress, per-student status — and launches the guided
/// camera. Queued offline uploads flush whenever this screen opens.
class PhotoSessionsScreen extends StatefulWidget {
  const PhotoSessionsScreen({super.key});

  @override
  State<PhotoSessionsScreen> createState() => _PhotoSessionsScreenState();
}

class _PhotoSessionsScreenState extends State<PhotoSessionsScreen> {
  Map<String, dynamic>? _session;
  bool _loading = true;
  int _pending = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final flushed = await PhotoService.instance.flushPending();
      final session = await PhotoService.instance.activeSession();
      final pending = await PhotoService.instance.pendingCount();
      if (!mounted) return;
      setState(() {
        _session = session;
        _pending = pending;
        _loading = false;
      });
      if (flushed > 0 && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: Text('$flushed queued photo(s) uploaded.')));
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Photo Sessions')),
      body: RefreshIndicator(
        onRefresh: _load,
        child: _loading
            ? const SkeletonList(rows: 5)
            : _session == null
                ? ListView(children: const [
                    SizedBox(height: 80),
                    StateMessage(
                      icon: Icons.photo_camera_outlined,
                      title: 'No active photo session',
                      subtitle:
                          'Create one from the web panel (Photo Sessions → New), choosing a class or the whole school. It appears here instantly — pull down to refresh.',
                    ),
                  ])
                : _sessionView(_session!),
      ),
    );
  }

  Widget _sessionView(Map<String, dynamic> s) {
    final queue = (s['queue'] as List).cast<Map<String, dynamic>>();
    final captured = s['photos_captured'] ?? 0;
    final total = s['total_students'] ?? queue.length;
    final progress = total > 0 ? captured / total : 0.0;

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        AppCard(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                      color: AppColors.primarySoft,
                      borderRadius: BorderRadius.circular(12)),
                  child: const Icon(Icons.photo_camera_rounded,
                      color: AppColors.primary),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(s['class_name'] ?? 'Whole school',
                          style: const TextStyle(
                              fontWeight: FontWeight.w800, fontSize: 15)),
                      Text('$captured of $total photographed',
                          style: const TextStyle(
                              fontSize: 12.5, color: AppColors.inkMid)),
                    ],
                  ),
                ),
                if (_pending > 0)
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                        color: AppColors.amberSoft,
                        borderRadius: BorderRadius.circular(8)),
                    child: Text('$_pending queued',
                        style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                            color: AppColors.amber)),
                  ),
              ]),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: LinearProgressIndicator(
                  value: progress,
                  minHeight: 8,
                  backgroundColor: AppColors.border,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: 14),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  icon: const Icon(Icons.camera_alt_rounded),
                  label: Text(
                      captured >= total ? 'Session complete' : 'Start capturing'),
                  onPressed: captured >= total
                      ? null
                      : () async {
                          await Navigator.of(context).push(MaterialPageRoute(
                              builder: (_) =>
                                  PhotoCaptureScreen(session: s)));
                          _load();
                        },
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const SectionLabel('Queue'),
        const SizedBox(height: 8),
        for (final q in queue)
          AppCard(
            margin: const EdgeInsets.only(bottom: 8),
            padding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            child: Row(children: [
              Icon(
                q['has_photo'] == true
                    ? Icons.check_circle_rounded
                    : q['is_current'] == true
                        ? Icons.radio_button_checked_rounded
                        : Icons.circle_outlined,
                size: 20,
                color: q['has_photo'] == true
                    ? AppColors.primary
                    : q['is_current'] == true
                        ? AppColors.amber
                        : AppColors.inkLo,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(q['full_name'] ?? '',
                    style: const TextStyle(
                        fontSize: 14, fontWeight: FontWeight.w600)),
              ),
              Text(q['uid'] ?? '',
                  style: const TextStyle(
                      fontSize: 11.5, color: AppColors.inkLo)),
            ]),
          ),
      ],
    );
  }
}
