import 'package:camera/camera.dart';
import 'package:flutter/material.dart';

import '../services/photo_service.dart';
import '../ui/kit.dart';

/// Guided ID-photo camera: live preview with a dashed head-and-shoulders
/// frame so every student is aligned the same way, one-tap shutter,
/// on-device compression, and upload with automatic offline queueing.
/// Walks the session queue student-by-student; skip is one tap.
class PhotoCaptureScreen extends StatefulWidget {
  const PhotoCaptureScreen({super.key, required this.session});

  final Map<String, dynamic> session;

  @override
  State<PhotoCaptureScreen> createState() => _PhotoCaptureScreenState();
}

class _PhotoCaptureScreenState extends State<PhotoCaptureScreen> {
  CameraController? _camera;
  String? _cameraError;
  bool _busy = false;

  late List<Map<String, dynamic>> _queue;
  late int _sessionId;

  @override
  void initState() {
    super.initState();
    _sessionId = widget.session['id'] as int;
    _queue = (widget.session['queue'] as List)
        .cast<Map<String, dynamic>>()
        .where((q) => q['has_photo'] != true)
        .toList();
    _initCamera();
  }

  Future<void> _initCamera() async {
    try {
      final cams = await availableCameras();
      if (cams.isEmpty) {
        setState(() => _cameraError = 'No camera found on this device.');
        return;
      }
      // Back camera photographs students; fall back to whatever exists.
      final back = cams.firstWhere(
        (c) => c.lensDirection == CameraLensDirection.back,
        orElse: () => cams.first,
      );
      final controller = CameraController(
        back,
        ResolutionPreset.high,
        enableAudio: false,
      );
      await controller.initialize();
      if (!mounted) return;
      setState(() => _camera = controller);
    } catch (e) {
      if (!mounted) return;
      setState(() => _cameraError =
          'Camera unavailable. Check the app\'s camera permission in your phone settings.');
    }
  }

  @override
  void dispose() {
    _camera?.dispose();
    super.dispose();
  }

  Map<String, dynamic>? get _current =>
      _queue.isEmpty ? null : _queue.first;

  Future<void> _shoot() async {
    final cam = _camera;
    final student = _current;
    if (cam == null || !cam.value.isInitialized || student == null || _busy) {
      return;
    }

    setState(() => _busy = true);
    try {
      final shot = await cam.takePicture();
      final compressed = await PhotoService.instance.compress(shot.path);

      final result = await PhotoService.instance.upload(
        sessionId: _sessionId,
        studentId: student['id'] as int,
        filePath: compressed,
      );

      if (!mounted) return;
      setState(() {
        _queue.removeAt(0);
        _busy = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(result.uploaded
            ? 'Saved — ${student['full_name']}'
            : 'No internet — photo queued, it will upload automatically.'),
        duration: const Duration(seconds: 1),
      ));

      if (_queue.isEmpty && mounted) Navigator.of(context).pop();
    } catch (_) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Could not take the photo. Please try again.')));
    }
  }

  Future<void> _skip() async {
    final student = _current;
    if (student == null || _busy) return;
    setState(() {
      _queue.removeAt(0);
    });
    try {
      await PhotoService.instance.skip(_sessionId);
    } catch (_) {/* offline skip is local-only; web queue re-syncs */}
    if (_queue.isEmpty && mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final student = _current;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: Text('${_queue.length} remaining',
            style: const TextStyle(color: Colors.white, fontSize: 15)),
      ),
      body: _cameraError != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Text(_cameraError!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white70)),
              ),
            )
          : _camera == null || !_camera!.value.isInitialized
              ? const Center(
                  child: CircularProgressIndicator(color: Colors.white))
              : Column(
                  children: [
                    Expanded(
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          CameraPreview(_camera!),
                          // Dashed head-and-shoulders alignment guide.
                          IgnorePointer(
                            child: CustomPaint(painter: _GuidePainter()),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      color: Colors.black,
                      padding: const EdgeInsets.fromLTRB(20, 14, 20, 24),
                      child: Column(children: [
                        if (student != null)
                          Text(
                            '${student['full_name']}  ·  ${student['uid']}',
                            style: const TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.w700),
                          ),
                        const SizedBox(height: 6),
                        const Text(
                          'Align the face inside the frame, then tap the shutter.',
                          style:
                              TextStyle(color: Colors.white54, fontSize: 12),
                        ),
                        const SizedBox(height: 14),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                          children: [
                            TextButton(
                              onPressed: _busy ? null : _skip,
                              child: const Text('Skip',
                                  style: TextStyle(color: Colors.white70)),
                            ),
                            // Shutter
                            GestureDetector(
                              onTap: _shoot,
                              child: Container(
                                width: 72,
                                height: 72,
                                decoration: BoxDecoration(
                                  shape: BoxShape.circle,
                                  border: Border.all(
                                      color: Colors.white, width: 4),
                                ),
                                padding: const EdgeInsets.all(5),
                                child: Container(
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    color: _busy
                                        ? AppColors.inkLo
                                        : AppColors.primary,
                                  ),
                                  child: _busy
                                      ? const Padding(
                                          padding: EdgeInsets.all(18),
                                          child: CircularProgressIndicator(
                                              color: Colors.white,
                                              strokeWidth: 2.5),
                                        )
                                      : const Icon(Icons.camera_alt_rounded,
                                          color: Colors.white, size: 28),
                                ),
                              ),
                            ),
                            const SizedBox(width: 64), // balance the row
                          ],
                        ),
                      ]),
                    ),
                  ],
                ),
    );
  }
}

/// Dashed portrait guide: an oval for the head and shoulders curve — the
/// same framing convention passport-photo apps use.
class _GuidePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withOpacity(0.85)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2;

    final cx = size.width / 2;
    final headTop = size.height * 0.16;
    final headH = size.height * 0.34;
    final headW = size.width * 0.42;

    // Head oval + shoulders arc, drawn as short dashes.
    final head = Rect.fromCenter(
        center: Offset(cx, headTop + headH / 2), width: headW, height: headH);
    _dashOval(canvas, head, paint);

    final shoulders = Path()
      ..moveTo(cx - size.width * 0.34, size.height * 0.78)
      ..quadraticBezierTo(
          cx, size.height * 0.52, cx + size.width * 0.34, size.height * 0.78);
    _dashPath(canvas, shoulders, paint);
  }

  void _dashOval(Canvas canvas, Rect rect, Paint paint) {
    _dashPath(canvas, Path()..addOval(rect), paint);
  }

  void _dashPath(Canvas canvas, Path source, Paint paint) {
    const dash = 9.0, gap = 7.0;
    for (final metric in source.computeMetrics()) {
      var d = 0.0;
      while (d < metric.length) {
        canvas.drawPath(
            metric.extractPath(d, (d + dash).clamp(0.0, metric.length).toDouble()), paint);
        d += dash + gap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
