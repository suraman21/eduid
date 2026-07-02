import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/auth_service.dart';
import '../ui/glass.dart';

enum PinMode { unlock, setup }

/// Frosted PIN pad — quick unlock (works offline) or first-time setup.
class PinScreen extends StatefulWidget {
  const PinScreen({super.key, required this.mode});

  final PinMode mode;

  @override
  State<PinScreen> createState() => _PinScreenState();
}

class _PinScreenState extends State<PinScreen> {
  String _pin = '';
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _complete() async {
    final auth = context.read<AuthService>();
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      if (widget.mode == PinMode.unlock) {
        final err = await auth.unlock(_pin);
        if (err != null) setState(() => _error = err);
      } else {
        if (_password.text.isEmpty) {
          setState(() => _error = 'Enter your account password to confirm.');
        } else {
          await auth.setPin(_pin, _password.text);
          if (mounted) {
            Navigator.of(context).pop();
            ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                content: Text('PIN set — quick unlock is on.')));
            return;
          }
        }
      }
    } catch (e) {
      setState(() => _error = ApiClient.messageFrom(e));
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
          _pin = '';
        });
      }
    }
  }

  void _tap(String digit) {
    if (_busy || _pin.length >= 4) return;
    setState(() => _pin += digit);
    if (_pin.length == 4) _complete();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final isUnlock = widget.mode == PinMode.unlock;

    return AppBackground(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 380),
                child: Column(
                  children: [
                    if (!isUnlock)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: GlassIconButton(
                            icon: Icons.arrow_back_rounded,
                            onTap: () => Navigator.of(context).pop()),
                      ),
                    Container(
                      width: 58,
                      height: 58,
                      decoration: BoxDecoration(
                        gradient: Palette.accentGradient,
                        borderRadius: BorderRadius.circular(19),
                        boxShadow: [
                          BoxShadow(
                              color: Palette.violet.withOpacity(0.5),
                              blurRadius: 24),
                        ],
                      ),
                      child: Icon(
                          isUnlock
                              ? Icons.lock_rounded
                              : Icons.pin_rounded,
                          size: 26,
                          color: Colors.white),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      isUnlock
                          ? 'Welcome back, ${auth.user?['name'] ?? ''}'
                          : 'Set quick-unlock PIN',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w800,
                          color: Palette.textHi),
                    ),
                    Text(
                      isUnlock
                          ? 'Enter your PIN — works offline too'
                          : 'Choose 4 digits for fast, offline unlock',
                      style:
                          const TextStyle(color: Palette.textLo, fontSize: 13),
                    ),
                    if (!isUnlock) ...[
                      const SizedBox(height: 16),
                      GlassField(
                        controller: _password,
                        label: 'Account password (to confirm)',
                        icon: Icons.lock_outline_rounded,
                        obscure: true,
                      ),
                    ],
                    const SizedBox(height: 26),
                    // PIN dots
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(4, (i) {
                        final filled = i < _pin.length;
                        return AnimatedContainer(
                          duration: const Duration(milliseconds: 140),
                          width: 15,
                          height: 15,
                          margin: const EdgeInsets.symmetric(horizontal: 9),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: filled ? Palette.violet : Colors.transparent,
                            border: Border.all(
                                color: filled
                                    ? Palette.violet
                                    : Palette.textLo,
                                width: 1.6),
                            boxShadow: filled
                                ? [
                                    BoxShadow(
                                        color:
                                            Palette.violet.withOpacity(0.6),
                                        blurRadius: 10)
                                  ]
                                : null,
                          ),
                        );
                      }),
                    ),
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Text(_error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                              color: Palette.rose, fontSize: 12.5)),
                    ],
                    const SizedBox(height: 24),
                    // Keypad
                    for (final row in const [
                      ['1', '2', '3'],
                      ['4', '5', '6'],
                      ['7', '8', '9'],
                      ['', '0', '<'],
                    ])
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: row.map((k) {
                          if (k.isEmpty) {
                            return const SizedBox(width: 84, height: 68);
                          }
                          return Padding(
                            padding: const EdgeInsets.all(5),
                            child: GestureDetector(
                              onTap: _busy
                                  ? null
                                  : () => k == '<'
                                      ? setState(() => _pin = _pin.isEmpty
                                          ? ''
                                          : _pin.substring(
                                              0, _pin.length - 1))
                                      : _tap(k),
                              child: Container(
                                width: 74,
                                height: 58,
                                alignment: Alignment.center,
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.06),
                                  borderRadius: BorderRadius.circular(16),
                                  border: Border.all(
                                      color:
                                          Colors.white.withOpacity(0.10)),
                                ),
                                child: k == '<'
                                    ? const Icon(Icons.backspace_rounded,
                                        size: 20, color: Palette.textMid)
                                    : Text(k,
                                        style: const TextStyle(
                                            fontSize: 22,
                                            fontWeight: FontWeight.w700,
                                            color: Palette.textHi)),
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    if (isUnlock) ...[
                      const SizedBox(height: 14),
                      TextButton(
                        onPressed: _busy ? null : () => auth.signOut(),
                        child: const Text('Sign in with password instead',
                            style: TextStyle(
                                color: Palette.textLo, fontSize: 13)),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
