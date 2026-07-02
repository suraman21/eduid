import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/auth_service.dart';
import '../ui/glass.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _schoolCode = TextEditingController();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _schoolCode.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_schoolCode.text.trim().isEmpty ||
        !_email.text.contains('@') ||
        _password.text.isEmpty) {
      setState(() => _error = 'Fill in school code, email and password.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthService>().login(
            schoolCode: _schoolCode.text,
            email: _email.text,
            password: _password.text,
          );
      // The SyncEngine starts automatically on sign-in and pulls everything.
    } catch (e) {
      setState(() => _error = ApiClient.messageFrom(e));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AppBackground(
      child: Scaffold(
        backgroundColor: Colors.transparent,
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 420),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Logo mark
                    Center(
                      child: Container(
                        width: 68,
                        height: 68,
                        decoration: BoxDecoration(
                          gradient: Palette.accentGradient,
                          borderRadius: BorderRadius.circular(22),
                          boxShadow: [
                            BoxShadow(
                                color: Palette.violet.withOpacity(0.55),
                                blurRadius: 30),
                          ],
                        ),
                        child: const Icon(Icons.school_rounded,
                            size: 34, color: Colors.white),
                      ),
                    ),
                    const SizedBox(height: 18),
                    const Text('EduID Teacher',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                            fontSize: 26,
                            fontWeight: FontWeight.w800,
                            color: Palette.textHi,
                            letterSpacing: -0.5)),
                    const SizedBox(height: 4),
                    const Text('Attendance · Grades · Schedule — even offline',
                        textAlign: TextAlign.center,
                        style:
                            TextStyle(color: Palette.textLo, fontSize: 13)),
                    const SizedBox(height: 30),

                    GlassCard(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        children: [
                          GlassField(
                            controller: _schoolCode,
                            label: 'School code',
                            icon: Icons.tag_rounded,
                            capitalization: TextCapitalization.characters,
                          ),
                          const SizedBox(height: 12),
                          GlassField(
                            controller: _email,
                            label: 'Email',
                            icon: Icons.mail_rounded,
                            keyboardType: TextInputType.emailAddress,
                          ),
                          const SizedBox(height: 12),
                          GlassField(
                            controller: _password,
                            label: 'Password',
                            icon: Icons.lock_rounded,
                            obscure: true,
                            onSubmitted: (_) => _submit(),
                          ),
                          if (_error != null) ...[
                            const SizedBox(height: 14),
                            Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: Palette.rose.withOpacity(0.12),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                    color: Palette.rose.withOpacity(0.35)),
                              ),
                              child: Text(_error!,
                                  style: const TextStyle(
                                      color: Palette.rose, fontSize: 12.5)),
                            ),
                          ],
                          const SizedBox(height: 18),
                          GradientButton(
                            label: 'Sign in',
                            icon: Icons.arrow_forward_rounded,
                            busy: _busy,
                            onPressed: _submit,
                          ),
                        ],
                      ),
                    ),
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
