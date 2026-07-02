import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';
import '../ui/glass.dart';

/// Camera QR scanner. Verification is FULLY OFFLINE: the scanned payload
/// ("UID|hmac_token") is matched against the cached roster's uid + qr_token —
/// no network round-trip, exactly as the PRD's offline-first model requires.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key, required this.classId});

  final int classId;

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final MobileScannerController _controller = MobileScannerController(
    detectionSpeed: DetectionSpeed.normal,
    formats: const [BarcodeFormat.qrCode],
  );
  bool _handling = false;
  String? _rejected;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_handling) return;
    final raw = capture.barcodes.firstOrNull?.rawValue;
    if (raw == null || raw.isEmpty) return;
    _handling = true;

    final Student? student =
        await AttendanceService.instance.verifyQrOffline(raw, widget.classId);

    if (!mounted) return;
    if (student != null) {
      Navigator.of(context).pop(student);
      return;
    }

    setState(
        () => _rejected = 'Invalid card, or the student is not in this class.');
    await Future<void>.delayed(const Duration(milliseconds: 1200));
    if (mounted) setState(() => _rejected = null);
    _handling = false;
  }

  Widget _glassPill({required Widget child, Color? tint}) => ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 14, sigmaY: 14),
          child: Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: (tint ?? Colors.white).withOpacity(0.14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(
                  color: (tint ?? Colors.white).withOpacity(0.25)),
            ),
            child: child,
          ),
        ),
      );

  @override
  Widget build(BuildContext context) {
    final frameColor = _rejected == null ? Palette.cyan : Palette.rose;
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),

          // Top bar
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
              child: Row(
                children: [
                  GlassIconButton(
                      icon: Icons.arrow_back_rounded,
                      color: Colors.white,
                      onTap: () => Navigator.of(context).pop()),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text('Scan student ID',
                        style: TextStyle(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            color: Colors.white)),
                  ),
                  GlassIconButton(
                      icon: Icons.flash_on_rounded,
                      color: Palette.amber,
                      tooltip: 'Torch',
                      onTap: () => _controller.toggleTorch()),
                ],
              ),
            ),
          ),

          // Glowing aiming frame
          Center(
            child: Container(
              width: 250,
              height: 250,
              decoration: BoxDecoration(
                border: Border.all(color: frameColor, width: 2.6),
                borderRadius: BorderRadius.circular(24),
                boxShadow: [
                  BoxShadow(
                      color: frameColor.withOpacity(0.4), blurRadius: 30),
                ],
              ),
            ),
          ),

          // Bottom hint / rejection
          Positioned(
            left: 24,
            right: 24,
            bottom: 44,
            child: _rejected != null
                ? _glassPill(
                    tint: Palette.rose,
                    child: Text(_rejected!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w600,
                            fontSize: 13)),
                  )
                : _glassPill(
                    child: const Text(
                      'Point the camera at the QR on the student ID card.\nVerification works offline.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white70, fontSize: 12.5),
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}
