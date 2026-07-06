import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/api_client.dart';
import '../models/models.dart';
import '../services/attendance_service.dart';
import '../ui/kit.dart';

/// Continuous QR attendance scanner — scan a whole line of students without
/// leaving the camera. Verification is FULLY OFFLINE and fast: the scanned
/// "UID|hmac_token" is matched against the cached roster (a single indexed
/// sqlite lookup), so there is no network round-trip on the hot path.
///
/// Feedback matches the design: a full-screen PRESENT (green) confirmation
/// with the student's photo + name (anti-fraud), an ALREADY MARKED (blue)
/// notice for a repeat, and an INVALID (red) notice for a bad card. The photo
/// loads asynchronously and never blocks detection.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({
    super.key,
    required this.classId,
    required this.className,
    this.alreadyScanned = const {},
  });

  final int classId;
  final String className;

  /// Students verified earlier in this session (avoid double counting).
  final Set<int> alreadyScanned;

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

enum _Feedback { present, already, invalid }

class _ScannerScreenState extends State<ScannerScreen>
    with SingleTickerProviderStateMixin {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    formats: const [BarcodeFormat.qrCode], // QR only → less work per frame
  );

  final List<Student> _found = [];
  DateTime _cooldownUntil = DateTime.fromMillisecondsSinceEpoch(0);

  // Current overlay.
  _Feedback? _fb;
  Student? _fbStudent;
  String? _fbMessage;
  String? _fbTime;

  late final AnimationController _scanAnim = AnimationController(
      vsync: this, duration: const Duration(milliseconds: 1800))
    ..repeat(reverse: true);

  String? _token; // bearer token for authorised photo requests

  @override
  void initState() {
    super.initState();
    ApiClient.instance.readToken().then((t) {
      if (mounted) setState(() => _token = t);
    });
  }

  @override
  void dispose() {
    _scanAnim.dispose();
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    // Short cooldown so one card doesn't fire many times per second, but short
    // enough to feel instant when moving card-to-card.
    if (DateTime.now().isBefore(_cooldownUntil)) return;
    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null || raw.isEmpty) return;
    _cooldownUntil = DateTime.now().add(const Duration(milliseconds: 800));

    final Student? student =
        await AttendanceService.instance.verifyQrOffline(raw, widget.classId);
    if (!mounted) return;

    if (student == null) {
      _show(_Feedback.invalid, null, 'Invalid card, or not in this class');
    } else if (widget.alreadyScanned.contains(student.id) ||
        _found.any((s) => s.id == student.id)) {
      _show(_Feedback.already, student, null);
    } else {
      setState(() => _found.add(student));
      _show(_Feedback.present, student, null);
    }
  }

  void _show(_Feedback fb, Student? student, String? message) {
    final now = TimeOfDay.now();
    final stamp =
        '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';
    setState(() {
      _fb = fb;
      _fbStudent = student;
      _fbMessage = message;
      _fbTime = stamp;
    });
    final token = student?.id ?? message;
    Future.delayed(const Duration(milliseconds: 1300), () {
      if (mounted && (_fbStudent?.id ?? _fbMessage) == token) {
        setState(() => _fb = null);
      }
    });
  }

  Color get _fbColor => switch (_fb) {
        _Feedback.present => AppColors.green,
        _Feedback.already => AppColors.blue,
        _Feedback.invalid => AppColors.red,
        null => AppColors.primary,
      };

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),

          // Aiming frame + animated scan line + corner brackets.
          Center(
            child: SizedBox(
              width: 260,
              height: 260,
              child: Stack(
                children: [
                  Positioned.fill(
                    child: CustomPaint(painter: _CornerPainter(_fbColor)),
                  ),
                  if (_fb == null)
                    AnimatedBuilder(
                      animation: _scanAnim,
                      builder: (context, _) => Align(
                        alignment: Alignment(0, (_scanAnim.value * 2) - 1),
                        child: Container(
                          height: 2.5,
                          margin: const EdgeInsets.symmetric(horizontal: 12),
                          decoration: BoxDecoration(
                            color: AppColors.primary,
                            borderRadius: BorderRadius.circular(2),
                            boxShadow: [
                              BoxShadow(
                                  color: AppColors.primary.withOpacity(0.6),
                                  blurRadius: 8,
                                  spreadRadius: 1),
                            ],
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),

          // Full-screen feedback overlay (present / already / invalid).
          if (_fb != null) _feedbackOverlay(),

          // Top bar.
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: Row(
                children: [
                  _RoundButton(
                    icon: Icons.close_rounded,
                    onTap: () => Navigator.of(context).pop(_found),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text('Scan player ID card QR code',
                            style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: Colors.white)),
                        Text(widget.className,
                            style: const TextStyle(
                                fontSize: 12, color: Colors.white70)),
                      ],
                    ),
                  ),
                  _RoundButton(
                    icon: Icons.flash_on_rounded,
                    onTap: () => _controller.toggleTorch(),
                  ),
                ],
              ),
            ),
          ),

          // Bottom: running count + Done.
          Positioned(
            left: 20,
            right: 20,
            bottom: 28,
            child: Column(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.55),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    _found.isEmpty
                        ? 'Point at a QR code'
                        : '${_found.length} scanned',
                    style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12.5,
                        fontWeight: FontWeight.w600),
                  ),
                ),
                const SizedBox(height: 12),
                FilledButton.icon(
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.white,
                    foregroundColor: AppColors.ink,
                    minimumSize: const Size(double.infinity, 52),
                  ),
                  onPressed: () => Navigator.of(context).pop(_found),
                  icon: const Icon(Icons.check_rounded, size: 20),
                  label: Text(_found.isEmpty
                      ? 'Done'
                      : 'Save & Exit — ${_found.length} scanned'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _feedbackOverlay() {
    final color = _fbColor;
    final student = _fbStudent;
    return Positioned.fill(
      child: Container(
        color: color.withOpacity(0.92),
        alignment: Alignment.center,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Icon or student photo.
            if (student != null)
              _StudentAvatar(
                classId: widget.classId,
                student: student,
                token: _token,
                ring: Colors.white,
              )
            else
              Container(
                width: 76,
                height: 76,
                decoration:
                    const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                child: Icon(Icons.close_rounded, color: color, size: 44),
              ),
            const SizedBox(height: 18),
            Text(
              switch (_fb!) {
                _Feedback.present => 'PRESENT',
                _Feedback.already => 'ALREADY MARKED',
                _Feedback.invalid => 'INVALID CARD',
              },
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 2),
            ),
            const SizedBox(height: 8),
            if (student != null) ...[
              Text(student.fullName,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      color: Colors.white,
                      fontSize: 24,
                      fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text(student.uid,
                  style: const TextStyle(
                      color: Colors.white70,
                      fontSize: 14,
                      fontWeight: FontWeight.w600)),
              if (_fb == _Feedback.present && _fbTime != null) ...[
                const SizedBox(height: 4),
                Text(_fbTime!,
                    style: const TextStyle(
                        color: Colors.white70, fontSize: 13)),
              ],
            ] else if (_fbMessage != null)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 40),
                child: Text(_fbMessage!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                        color: Colors.white, fontSize: 15)),
              ),
          ],
        ),
      ),
    );
  }
}

/// Student photo for the confirmation overlay, with a white ring. Loads the
/// authorised photo asynchronously and falls back to initials instantly — the
/// fallback shows first, so this never delays the scan feedback.
class _StudentAvatar extends StatelessWidget {
  const _StudentAvatar({
    required this.classId,
    required this.student,
    required this.token,
    required this.ring,
  });

  final int classId;
  final Student student;
  final String? token;
  final Color ring;

  String get _initials {
    final parts = student.fullName.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '?';
    final a = parts.first[0];
    final b = parts.length > 1 ? parts.last[0] : '';
    return '$a$b'.toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      color: Colors.white24,
      alignment: Alignment.center,
      child: Text(_initials,
          style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              fontWeight: FontWeight.w800)),
    );

    Widget inner = fallback;
    if (student.hasPhoto && token != null) {
      final url =
          '${ApiClient.instance.baseUrl}/classes/$classId/students/${student.id}/photo';
      inner = Image.network(
        url,
        headers: {'Authorization': 'Bearer $token'},
        fit: BoxFit.cover,
        width: 84,
        height: 84,
        // Keep initials visible until the photo is decoded; never block.
        frameBuilder: (context, child, frame, wasSync) =>
            frame == null ? fallback : child,
        errorBuilder: (context, _, __) => fallback,
      );
    }

    return Container(
      width: 90,
      height: 90,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: ring, width: 3),
      ),
      child: ClipOval(child: SizedBox(width: 84, height: 84, child: inner)),
    );
  }
}

/// Four green L-shaped corner brackets around the aiming frame.
class _CornerPainter extends CustomPainter {
  _CornerPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final p = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4
      ..strokeCap = StrokeCap.round;
    const len = 28.0;
    final w = size.width, h = size.height;
    // top-left
    canvas.drawLine(const Offset(0, 0), const Offset(len, 0), p);
    canvas.drawLine(const Offset(0, 0), const Offset(0, len), p);
    // top-right
    canvas.drawLine(Offset(w, 0), Offset(w - len, 0), p);
    canvas.drawLine(Offset(w, 0), Offset(w, len), p);
    // bottom-left
    canvas.drawLine(Offset(0, h), Offset(len, h), p);
    canvas.drawLine(Offset(0, h), Offset(0, h - len), p);
    // bottom-right
    canvas.drawLine(Offset(w, h), Offset(w - len, h), p);
    canvas.drawLine(Offset(w, h), Offset(w, h - len), p);
  }

  @override
  bool shouldRepaint(covariant _CornerPainter old) => old.color != color;
}

class _RoundButton extends StatelessWidget {
  const _RoundButton({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white24,
      shape: const CircleBorder(),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 42,
          height: 42,
          child: Icon(icon, size: 20, color: Colors.white),
        ),
      ),
    );
  }
}
