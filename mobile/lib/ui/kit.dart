import 'dart:math' as math;

import 'package:flutter/material.dart';

/// EduID design system — light, professional, FAST.
///
/// Built for low-end devices: flat solid surfaces, hairline borders, no
/// BackdropFilter/blur, no full-screen gradients, no glow shadows. Every
/// tappable surface is an InkWell so touch feedback is instant (ripple starts
/// on pointer-down, before any async work).
class AppColors {
  // Emerald green — matches the public website brand.
  static const primary = Color(0xFF059669);
  static const primaryDark = Color(0xFF047857);
  static const primarySoft = Color(0xFFECFDF5);

  static const bg = Color(0xFFF6F7FB);
  static const surface = Colors.white;
  static const border = Color(0xFFE5E8F0);

  static const ink = Color(0xFF0F172A);
  static const inkMid = Color(0xFF475569);
  static const inkLo = Color(0xFF94A3B8);

  // Status colours (P/A/L/E, sync state) — a distinct, high-chroma set so
  // status reads at a glance and never gets mistaken for the brand CTA green.
  static const green = Color(0xFF16A34A);
  static const greenSoft = Color(0xFFF0FDF4);
  static const red = Color(0xFFDC2626);
  static const redSoft = Color(0xFFFEF2F2);
  static const amber = Color(0xFFF59E0B);
  static const amberSoft = Color(0xFFFFFBEB);
  static const blue = Color(0xFF2563EB);
  static const blueSoft = Color(0xFFEFF6FF);
}

ThemeData buildAppTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: AppColors.primary);
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: AppColors.bg,
    // InkRipple is cheaper than the shader-based InkSparkle on old GPUs.
    splashFactory: InkRipple.splashFactory,
    appBarTheme: const AppBarTheme(
      backgroundColor: AppColors.bg,
      foregroundColor: AppColors.ink,
      elevation: 0,
      scrolledUnderElevation: 0,
      centerTitle: false,
      titleTextStyle: TextStyle(
          fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.ink),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        minimumSize: const Size(48, 52),
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.primary,
        minimumSize: const Size(48, 52),
        side: const BorderSide(color: AppColors.border),
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: AppColors.surface,
      labelStyle: const TextStyle(color: AppColors.inkLo, fontSize: 14),
      hintStyle: const TextStyle(color: AppColors.inkLo, fontSize: 14),
      prefixIconColor: AppColors.inkLo,
      contentPadding:
          const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.border),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(14),
        borderSide: const BorderSide(color: AppColors.primary, width: 1.6),
      ),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: AppColors.surface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      titleTextStyle: const TextStyle(
          fontSize: 17, fontWeight: FontWeight.w700, color: AppColors.ink),
      contentTextStyle:
          const TextStyle(fontSize: 14, color: AppColors.inkMid),
    ),
    snackBarTheme: SnackBarThemeData(
      backgroundColor: AppColors.ink,
      contentTextStyle: const TextStyle(color: Colors.white, fontSize: 13.5),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
    ),
    dividerTheme:
        const DividerThemeData(color: AppColors.border, thickness: 1),
  );
}

/// Flat white card with a hairline border and an instant Material ripple.
/// Renders in a single cheap layer — safe to use hundreds of times in lists.
class AppCard extends StatelessWidget {
  const AppCard({
    super.key,
    required this.child,
    this.onTap,
    this.padding = const EdgeInsets.all(16),
    this.margin,
    this.radius = 16,
    this.color = AppColors.surface,
    this.borderColor = AppColors.border,
  });

  final Widget child;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final double radius;
  final Color color;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(radius),
      side: BorderSide(color: borderColor),
    );
    final card = Material(
      color: color,
      shape: shape,
      child: InkWell(
        onTap: onTap,
        customBorder: shape,
        child: Padding(padding: padding, child: child),
      ),
    );
    return margin == null ? card : Padding(padding: margin!, child: card);
  }
}

/// Primary CTA — FilledButton with a built-in busy spinner.
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    this.onPressed,
    this.icon,
    this.busy = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool busy;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: busy ? null : onPressed,
      child: busy
          ? const SizedBox(
              width: 20,
              height: 20,
              child:
                  CircularProgressIndicator(strokeWidth: 2.2, color: Colors.white),
            )
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (icon != null) ...[
                  Icon(icon, size: 19),
                  const SizedBox(width: 8),
                ],
                Text(label),
              ],
            ),
    );
  }
}

/// UPPERCASE section label used between card groups.
class SectionLabel extends StatelessWidget {
  const SectionLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(
        text.toUpperCase(),
        style: const TextStyle(
          color: AppColors.inkLo,
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          letterSpacing: 1.4,
        ),
      ),
    );
  }
}

/// Rounded square icon tile used as a leading element in list rows.
class IconTile extends StatelessWidget {
  const IconTile(this.icon,
      {super.key,
      this.color = AppColors.primary,
      this.background,
      this.size = 40});

  final IconData icon;
  final Color color;
  final Color? background;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: background ?? color.withOpacity(0.10),
        borderRadius: BorderRadius.circular(size * 0.3),
      ),
      child: Icon(icon, size: size * 0.48, color: color),
    );
  }
}

/// Small tinted status pill ("synced", "queued", "NOW", …).
class StatusPill extends StatelessWidget {
  const StatusPill(this.text, {super.key, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withOpacity(0.10),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
            fontSize: 10.5, fontWeight: FontWeight.w700, color: color),
      ),
    );
  }
}

/// The EduID brand mark: an open book with a rising figure, drawn with a
/// CustomPainter (no image asset, no package — sharp at any size, negligible
/// cost). Used on the splash screen and the login card.
class EduIdMark extends StatelessWidget {
  const EduIdMark({super.key, this.size = 64, this.background, this.glyphColor});

  final double size;

  /// Tile background. Defaults to filled brand green (splash/login usage).
  final Color? background;

  /// Glyph colour. Defaults to white against the default green background.
  final Color? glyphColor;

  @override
  Widget build(BuildContext context) {
    final bg = background ?? AppColors.primary;
    final glyph = glyphColor ?? Colors.white;
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(size * 0.28),
      ),
      child: CustomPaint(painter: _EduIdMarkPainter(glyph)),
    );
  }
}

class _EduIdMarkPainter extends CustomPainter {
  _EduIdMarkPainter(this.color);
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width, h = size.height;
    final fill = Paint()..color = color;
    final stroke = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = w * 0.045
      ..strokeCap = StrokeCap.round;

    // Head — a small circle centred above the spine.
    canvas.drawCircle(Offset(w * 0.5, h * 0.32), w * 0.085, fill);

    // Two open-book pages, each a gentle outward curve from the spine.
    final spineTop = Offset(w * 0.5, h * 0.46);
    final spineBottom = Offset(w * 0.5, h * 0.78);

    final leftPage = Path()
      ..moveTo(spineTop.dx, spineTop.dy)
      ..quadraticBezierTo(w * 0.20, h * 0.55, w * 0.20, h * 0.42)
      ..lineTo(w * 0.20, h * 0.42);
    final leftOuter = Path()
      ..moveTo(w * 0.20, h * 0.42)
      ..quadraticBezierTo(w * 0.30, h * 0.58, spineBottom.dx, spineBottom.dy);
    canvas.drawPath(leftPage, stroke);
    canvas.drawPath(leftOuter, stroke);

    final rightPage = Path()
      ..moveTo(spineTop.dx, spineTop.dy)
      ..quadraticBezierTo(w * 0.80, h * 0.55, w * 0.80, h * 0.42);
    final rightOuter = Path()
      ..moveTo(w * 0.80, h * 0.42)
      ..quadraticBezierTo(w * 0.70, h * 0.58, spineBottom.dx, spineBottom.dy);
    canvas.drawPath(rightPage, stroke);
    canvas.drawPath(rightOuter, stroke);

    // Spine — the "rising figure" stem linking head to book.
    canvas.drawLine(
      Offset(w * 0.5, h * 0.40),
      spineBottom,
      stroke..strokeWidth = w * 0.05,
    );
  }

  @override
  bool shouldRepaint(covariant _EduIdMarkPainter oldDelegate) =>
      oldDelegate.color != color;
}

/// Circular progress ring with a value/label pair centred inside — used for
/// "N of M done" states (Photo Sessions). One CustomPaint, no packages.
class ProgressRing extends StatelessWidget {
  const ProgressRing({
    super.key,
    required this.value,
    this.size = 76,
    this.strokeWidth = 8,
    this.color = AppColors.primary,
    this.track = AppColors.border,
    this.centerText,
    this.centerSubtext,
  });

  /// 0.0–1.0.
  final double value;
  final double size;
  final double strokeWidth;
  final Color color;
  final Color track;
  final String? centerText;
  final String? centerSubtext;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          CustomPaint(
            size: Size(size, size),
            painter: _RingPainter(
                value: value.clamp(0.0, 1.0).toDouble(),
                color: color,
                track: track,
                strokeWidth: strokeWidth),
          ),
          if (centerText != null)
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(centerText!,
                    style: TextStyle(
                        fontSize: size * 0.23,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink)),
                if (centerSubtext != null)
                  Text(centerSubtext!,
                      style: TextStyle(
                          fontSize: size * 0.11, color: AppColors.inkLo)),
              ],
            ),
        ],
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  _RingPainter(
      {required this.value,
      required this.color,
      required this.track,
      required this.strokeWidth});

  final double value;
  final Color color;
  final Color track;
  final double strokeWidth;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Rect.fromLTWH(strokeWidth / 2, strokeWidth / 2,
        size.width - strokeWidth, size.height - strokeWidth);
    final base = Paint()
      ..color = track
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth;
    canvas.drawArc(rect, 0, math.pi * 2, false, base);

    final fg = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = strokeWidth;
    const start = -math.pi / 2; // start at 12 o'clock
    canvas.drawArc(rect, start, math.pi * 2 * value, false, fg);
  }

  @override
  bool shouldRepaint(covariant _RingPainter oldDelegate) =>
      oldDelegate.value != value ||
      oldDelegate.color != color ||
      oldDelegate.track != track;
}

/// Shared stat card: big value + small label, optional leading icon. Used on
/// Home (teacher + parent) and Grades summaries so every stat row in the app
/// shares one look.
class StatTile extends StatelessWidget {
  const StatTile({
    super.key,
    required this.value,
    required this.label,
    this.icon,
    this.color = AppColors.primary,
  });

  final String value;
  final String label;
  final IconData? icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      child: Column(
        children: [
          if (icon != null) ...[
            Icon(icon, color: color, size: 19),
            const SizedBox(height: 6),
          ],
          Text(value,
              style: TextStyle(
                  color: icon == null ? color : AppColors.ink,
                  fontWeight: FontWeight.w800,
                  fontSize: 18)),
          const SizedBox(height: 1),
          Text(label,
              textAlign: TextAlign.center,
              style: const TextStyle(color: AppColors.inkLo, fontSize: 11)),
        ],
      ),
    );
  }
}
