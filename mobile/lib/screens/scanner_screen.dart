import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../ui/kit.dart';

/// Continuous QR attendance scanner — scan the whole line of students without
/// leaving the camera. Each valid card flashes green and adds to the running
/// count; duplicates and invalid cards flash their own colour. "Done" returns
/// every verified student to the roster in one go.
///
/// Verification is FULLY OFFLINE: the scanned payload ("UID|hmac_token") is
/// matched against the cached roster's uid + qr_token — no network round-trip.
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

class _ScannerScreenState extends State<ScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    formats: const [BarcodeFormat.qrCode],
  );

  final List<Student> _found = [];
  DateTime _cooldownUntil = DateTime.fromMillisecondsSinceEpoch(0);
  String? _flashText;
  Color _flashColor = AppColors.green;

  // Success overlay (green check + name + time), shown briefly on a valid scan.
  String? _successName;
  String? _successTime;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    // Small cooldown so one card doesn't fire many times per second.
    if (DateTime.now().isBefore(_cooldownUntil)) return;
    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null || raw.isEmpty) return;
    _cooldownUntil = DateTime.now().add(const Duration(milliseconds: 1200));

    final Student? student =
        await AttendanceService.instance.verifyQrOffline(raw, widget.classId);
    if (!mounted) return;

    if (student == null) {
      _flash('Invalid card, or not in this class', AppColors.red);
    } else if (widget.alreadyScanned.contains(student.id) ||
        _found.any((s) => s.id == student.id)) {
      _flash('${student.fullName} — already marked', AppColors.blue);
    } else {
      setState(() => _found.add(student));
      _showSuccess(student.fullName);
    }
  }

  /// Full green "Check-in successful!" overlay for a valid, first-time scan —
  /// matches the design's confirmation and gives an unmistakable signal.
  void _showSuccess(String name) {
    final now = TimeOfDay.now();
    final hh = now.hour.toString().padLeft(2, '0');
    final mm = now.minute.toString().padLeft(2, '0');
    setState(() {
      _successName = name;
      _successTime = '$hh:$mm';
      _flashText = null;
    });
    Future.delayed(const Duration(milliseconds: 1300), () {
      if (mounted && _successName == name) {
        setState(() => _successName = null);
      }
    });
  }

  void _flash(String text, Color color) {
    setState(() {
      _flashText = text;
      _flashColor = color;
    });
    Future.delayed(const Duration(milliseconds: 1100), () {
      if (mounted && _flashText == text) setState(() => _flashText = null);
    });
  }

  @override
  Widget build(BuildContext context) {
    final frameColor = _successName != null
        ? AppColors.green
        : (_flashText == null ? AppColors.primary : _flashColor);
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),

          // Dim + success confirmation on a valid scan.
          if (_successName != null)
            Positioned.fill(
              child: Container(
                color: Colors.black.withOpacity(0.55),
                alignment: Alignment.center,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 76,
                      height: 76,
                      decoration: const BoxDecoration(
                          color: AppColors.green, shape: BoxShape.circle),
                      child: const Icon(Icons.check_rounded,
                          color: Colors.white, size: 44),
                    ),
                    const SizedBox(height: 16),
                    const Text('Check-in successful!',
                        style: TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    Text(_successName!,
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 15,
                            fontWeight: FontWeight.w600)),
                    const SizedBox(height: 2),
                    Text(_successTime ?? '',
                        style: const TextStyle(
                            color: Colors.white70, fontSize: 13)),
                  ],
                ),
              ),
            ),

          // Top bar
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
                        const Text('Scan student IDs',
                            style: TextStyle(
                                fontSize: 16,
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

          // Aiming frame
          Center(
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                border: Border.all(color: frameColor, width: 3),
                borderRadius: BorderRadius.circular(22),
              ),
            ),
          ),

          // Scan feedback + running count + done
          Positioned(
            left: 20,
            right: 20,
            bottom: 32,
            child: Column(
              children: [
                if (_flashText != null)
                  Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 12),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _flashColor,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(_flashText!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                            fontSize: 13.5)),
                  )
                else
                  const Padding(
                    padding: EdgeInsets.only(bottom: 12),
                    child: Text(
                      'Point at the QR code — students are marked\npresent automatically, one after another.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white70, fontSize: 12.5),
                    ),
                  ),
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
                      : 'Done — ${_found.length} scanned'),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
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
