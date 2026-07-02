import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/auth_service.dart';

enum PinMode { unlock, setup }

/// PIN pad — quick unlock on app open (PinMode.unlock, works offline) or
/// first-time PIN registration from Settings (PinMode.setup, asks for the
/// account password because the server requires re-auth to set a PIN).
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
            ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('PIN set — quick unlock is on.')));
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

    return Scaffold(
      appBar: isUnlock ? null : AppBar(title: const Text('Set quick-unlock PIN')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 360),
              child: Column(
                children: [
                  if (isUnlock) ...[
                    const Icon(Icons.lock_rounded, size: 44, color: Color(0xFF6366F1)),
                    const SizedBox(height: 10),
                    Text('Welcome back, ${auth.user?['name'] ?? ''}',
                        style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
                    Text('Enter your PIN', style: TextStyle(color: Colors.grey.shade600)),
                  ] else ...[
                    Text('Choose a 4-digit PIN for fast, offline unlock.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.grey.shade700)),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _password,
                      obscureText: true,
                      decoration: const InputDecoration(
                          labelText: 'Account password (to confirm)',
                          prefixIcon: Icon(Icons.lock_outline)),
                    ),
                  ],
                  const SizedBox(height: 24),
                  // PIN dots
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: List.generate(4, (i) {
                      final filled = i < _pin.length;
                      return Container(
                        width: 16, height: 16,
                        margin: const EdgeInsets.symmetric(horizontal: 8),
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: filled ? const Color(0xFF6366F1) : Colors.transparent,
                          border: Border.all(color: const Color(0xFF6366F1), width: 2),
                        ),
                      );
                    }),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 14),
                    Text(_error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 13)),
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
                        if (k.isEmpty) return const SizedBox(width: 88, height: 72);
                        return SizedBox(
                          width: 88, height: 72,
                          child: TextButton(
                            onPressed: _busy
                                ? null
                                : () => k == '<'
                                    ? setState(() => _pin =
                                        _pin.isEmpty ? '' : _pin.substring(0, _pin.length - 1))
                                    : _tap(k),
                            child: k == '<'
                                ? const Icon(Icons.backspace_outlined, size: 22)
                                : Text(k,
                                    style: const TextStyle(
                                        fontSize: 24, fontWeight: FontWeight.w600)),
                          ),
                        );
                      }).toList(),
                    ),
                  if (isUnlock) ...[
                    const SizedBox(height: 12),
                    TextButton(
                      onPressed: _busy ? null : () => auth.signOut(),
                      child: const Text('Sign in with password instead'),
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
}
