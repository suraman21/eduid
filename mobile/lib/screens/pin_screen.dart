import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/auth_service.dart';
import '../ui/kit.dart';

enum PinMode { unlock, setup }

/// PIN screens, done the way passcode flows work on iOS/Android:
///
/// UNLOCK — enter 4 digits, auto-submits on the last digit (works offline).
/// SETUP  — three explicit steps with a progress label:
///   1. confirm your account password
///   2. choose a new 4-digit PIN
///   3. re-enter the PIN to confirm (mismatch → clear + back to step 2)
class PinScreen extends StatefulWidget {
  const PinScreen({super.key, required this.mode});

  final PinMode mode;

  @override
  State<PinScreen> createState() => _PinScreenState();
}

class _PinScreenState extends State<PinScreen> {
  // Setup steps: 0 = password, 1 = choose PIN, 2 = confirm PIN.
  int _step = 0;
  String _pin = '';
  String _firstPin = '';
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  bool get _isUnlock => widget.mode == PinMode.unlock;

  @override
  void dispose() {
    _password.dispose();
    super.dispose();
  }

  Future<void> _unlockWith(String pin) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final err = await context.read<AuthService>().unlock(pin);
    if (!mounted) return;
    setState(() {
      _busy = false;
      _pin = '';
      _error = err;
    });
  }

  Future<void> _saveNewPin() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthService>().setPin(_firstPin, _password.text);
      if (!mounted) return;
      Navigator.of(context).pop();
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('PIN set — quick unlock is now on.')));
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = ApiClient.messageFrom(e);
        // A wrong password comes back from the server here — restart there.
        _step = 0;
        _pin = '';
        _firstPin = '';
      });
    }
  }

  void _onDigit(String d) {
    if (_busy || _pin.length >= 4) return;
    setState(() => _pin += d);
    if (_pin.length < 4) return;

    if (_isUnlock) {
      _unlockWith(_pin);
      return;
    }
    if (_step == 1) {
      // Chose a PIN — now confirm it.
      setState(() {
        _firstPin = _pin;
        _pin = '';
        _step = 2;
        _error = null;
      });
    } else if (_step == 2) {
      if (_pin == _firstPin) {
        _saveNewPin();
      } else {
        setState(() {
          _error = "PINs didn't match — please choose again.";
          _pin = '';
          _firstPin = '';
          _step = 1;
        });
      }
    }
  }

  void _onBackspace() {
    if (_busy || _pin.isEmpty) return;
    setState(() => _pin = _pin.substring(0, _pin.length - 1));
  }

  String get _title {
    if (_isUnlock) return 'Enter your PIN';
    return switch (_step) {
      0 => 'Confirm your password',
      1 => 'Choose a new PIN',
      _ => 'Re-enter your PIN',
    };
  }

  String get _subtitle {
    if (_isUnlock) return 'Quick unlock — works offline too';
    return switch (_step) {
      0 => 'For your security, confirm your account password first.',
      1 => 'Pick 4 digits you will remember.',
      _ => 'Type the same 4 digits again to confirm.',
    };
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();

    return Scaffold(
      appBar: _isUnlock
          ? null
          : AppBar(
              title: const Text('Quick-unlock PIN'),
              actions: [
                Center(
                  child: Padding(
                    padding: const EdgeInsets.only(right: 16),
                    child: Text('Step ${_step + 1} of 3',
                        style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                            color: AppColors.inkLo)),
                  ),
                ),
              ],
            ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 380),
              child: Column(
                children: [
                  IconTile(
                    _isUnlock ? Icons.lock_rounded : Icons.pin_rounded,
                    color: AppColors.primary,
                    size: 56,
                  ),
                  const SizedBox(height: 14),
                  Text(
                    _isUnlock
                        ? 'Welcome back, ${auth.user?['name'] ?? ''}'
                        : _title,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                        fontSize: 19,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink),
                  ),
                  const SizedBox(height: 4),
                  Text(_subtitle,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                          color: AppColors.inkMid, fontSize: 13)),
                  const SizedBox(height: 24),

                  if (_error != null) ...[
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.redSoft,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(_error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                              color: AppColors.red, fontSize: 12.5)),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // ── Step 0 (setup): password confirmation ──
                  if (!_isUnlock && _step == 0) ...[
                    TextField(
                      controller: _password,
                      obscureText: true,
                      onSubmitted: (_) => _confirmPassword(),
                      style:
                          const TextStyle(color: AppColors.ink, fontSize: 15),
                      decoration: const InputDecoration(
                        labelText: 'Account password',
                        prefixIcon: Icon(Icons.lock_outline_rounded, size: 20),
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: PrimaryButton(
                        label: 'Continue',
                        icon: Icons.arrow_forward_rounded,
                        onPressed: _confirmPassword,
                      ),
                    ),
                  ] else ...[
                    // ── PIN dots ──
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: List.generate(4, (i) {
                        final filled = i < _pin.length;
                        return Container(
                          width: 14,
                          height: 14,
                          margin: const EdgeInsets.symmetric(horizontal: 9),
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: filled
                                ? AppColors.primary
                                : Colors.transparent,
                            border: Border.all(
                                color: filled
                                    ? AppColors.primary
                                    : AppColors.inkLo,
                                width: 1.6),
                          ),
                        );
                      }),
                    ),
                    const SizedBox(height: 8),
                    SizedBox(
                      height: 22,
                      child: _busy
                          ? const Center(
                              child: SizedBox(
                                width: 16,
                                height: 16,
                                child: CircularProgressIndicator(
                                    strokeWidth: 2, color: AppColors.primary),
                              ),
                            )
                          : null,
                    ),
                    const SizedBox(height: 8),
                    _Keypad(
                        enabled: !_busy,
                        onDigit: _onDigit,
                        onBackspace: _onBackspace),
                  ],

                  if (_isUnlock) ...[
                    const SizedBox(height: 16),
                    TextButton(
                      onPressed: _busy
                          ? null
                          : () => context.read<AuthService>().signOut(),
                      child: const Text('Sign in with password instead',
                          style: TextStyle(
                              color: AppColors.inkMid, fontSize: 13)),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  void _confirmPassword() {
    if (_password.text.isEmpty) {
      setState(() => _error = 'Enter your account password to continue.');
      return;
    }
    setState(() {
      _error = null;
      _step = 1;
      _pin = '';
      _firstPin = '';
    });
  }
}

/// Number pad with real Material ripples — feedback starts on pointer-down.
class _Keypad extends StatelessWidget {
  const _Keypad(
      {required this.onDigit,
      required this.onBackspace,
      required this.enabled});

  final ValueChanged<String> onDigit;
  final VoidCallback onBackspace;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    Widget key(String k) {
      if (k.isEmpty) return const SizedBox(width: 84, height: 66);
      final isBack = k == '<';
      return Padding(
        padding: const EdgeInsets.all(4),
        child: Material(
          color: AppColors.surface,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: const BorderSide(color: AppColors.border),
          ),
          child: InkWell(
            onTap: !enabled ? null : (isBack ? onBackspace : () => onDigit(k)),
            borderRadius: BorderRadius.circular(14),
            child: SizedBox(
              width: 76,
              height: 58,
              child: Center(
                child: isBack
                    ? const Icon(Icons.backspace_outlined,
                        size: 20, color: AppColors.inkMid)
                    : Text(k,
                        style: const TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink)),
              ),
            ),
          ),
        ),
      );
    }

    return Column(
      children: [
        for (final row in const [
          ['1', '2', '3'],
          ['4', '5', '6'],
          ['7', '8', '9'],
          ['', '0', '<'],
        ])
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [for (final k in row) key(k)],
          ),
      ],
    );
  }
}
