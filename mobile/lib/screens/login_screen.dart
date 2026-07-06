import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/api_client.dart';
import '../services/auth_service.dart';
import '../ui/kit.dart';

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
  bool _showPassword = false;
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
    final sessionExpired = context.watch<AuthService>().sessionWasExpired;
    return Scaffold(
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
                  const Center(child: EduIdMark(size: 68)),
                  const SizedBox(height: 16),
                  const Text('Welcome back',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                          fontSize: 25,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink,
                          letterSpacing: -0.5)),
                  const SizedBox(height: 4),
                  const Text('Sign in to your EduID account',
                      textAlign: TextAlign.center,
                      style:
                          TextStyle(color: AppColors.inkMid, fontSize: 13.5)),
                  if (sessionExpired) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.amberSoft,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.info_outline_rounded,
                              size: 18, color: AppColors.amber),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                                'Your session ended. Please sign in again.',
                                style: TextStyle(
                                    color: AppColors.amber, fontSize: 12.5)),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 28),

                  AppCard(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      children: [
                        TextField(
                          controller: _schoolCode,
                          textCapitalization: TextCapitalization.characters,
                          style: const TextStyle(
                              color: AppColors.ink, fontSize: 15),
                          decoration: const InputDecoration(
                            labelText: 'School code',
                            prefixIcon: Icon(Icons.numbers_rounded, size: 20),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _email,
                          keyboardType: TextInputType.emailAddress,
                          autocorrect: false,
                          style: const TextStyle(
                              color: AppColors.ink, fontSize: 15),
                          decoration: const InputDecoration(
                            labelText: 'Email',
                            prefixIcon: Icon(Icons.alternate_email_rounded,
                                size: 20),
                          ),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: _password,
                          obscureText: !_showPassword,
                          onSubmitted: (_) => _submit(),
                          style: const TextStyle(
                              color: AppColors.ink, fontSize: 15),
                          decoration: InputDecoration(
                            labelText: 'Password',
                            prefixIcon:
                                const Icon(Icons.lock_rounded, size: 20),
                            suffixIcon: IconButton(
                              icon: Icon(
                                  _showPassword
                                      ? Icons.visibility_off_rounded
                                      : Icons.visibility_rounded,
                                  size: 20),
                              onPressed: () => setState(
                                  () => _showPassword = !_showPassword),
                            ),
                          ),
                        ),
                        if (_error != null) ...[
                          const SizedBox(height: 14),
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.redSoft,
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(_error!,
                                style: const TextStyle(
                                    color: AppColors.red, fontSize: 12.5)),
                          ),
                        ],
                        const SizedBox(height: 18),
                        PrimaryButton(
                          label: 'Sign in',
                          icon: Icons.arrow_forward_rounded,
                          busy: _busy,
                          onPressed: _submit,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Use the account your school gave you.\nForgot your password? Ask your school administrator.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.inkLo, fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
