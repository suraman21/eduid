import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../models/models.dart';
import '../services/attendance_service.dart';

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

    setState(() => _rejected = 'Invalid card, or the student is not in this class.');
    await Future<void>.delayed(const Duration(milliseconds: 1200));
    if (mounted) setState(() => _rejected = null);
    _handling = false;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('Scan student ID'),
        actions: [
          IconButton(
            icon: const Icon(Icons.flash_on_rounded),
            onPressed: () => _controller.toggleTorch(),
          ),
        ],
      ),
      body: Stack(
        children: [
          MobileScanner(controller: _controller, onDetect: _onDetect),
          // Aiming frame
          Center(
            child: Container(
              width: 240, height: 240,
              decoration: BoxDecoration(
                border: Border.all(
                  color: _rejected == null
                      ? const Color(0xFF6366F1)
                      : const Color(0xFFDC2626),
                  width: 3,
                ),
                borderRadius: BorderRadius.circular(20),
              ),
            ),
          ),
          if (_rejected != null)
            Positioned(
              left: 24, right: 24, bottom: 48,
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFDC2626),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(_rejected!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white)),
              ),
            )
          else
            const Positioned(
              left: 24, right: 24, bottom: 48,
              child: Text(
                'Point the camera at the QR on the student ID card.\nVerification works offline.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
            ),
        ],
      ),
    );
  }
}
