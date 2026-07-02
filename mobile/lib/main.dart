import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api_client.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/pin_screen.dart';
import 'screens/role_notice_screen.dart';
import 'services/auth_service.dart';
import 'services/sync_engine.dart';
import 'ui/kit.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await ApiClient.instance.init();

  final auth = AuthService();
  await auth.restore();

  // Automatic sync: runs whenever the user is signed in and unlocked; never a
  // manual chore. Reacts live to sign-in / unlock / sign-out.
  void driveSync() {
    if (auth.signedIn && !auth.locked) {
      SyncEngine.instance.start();
    } else {
      SyncEngine.instance.stop();
    }
  }

  auth.addListener(driveSync);
  driveSync();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider.value(value: SyncEngine.instance),
      ],
      child: EduIdApp(auth: auth),
    ),
  );
}

class EduIdApp extends StatefulWidget {
  const EduIdApp({super.key, required this.auth});

  final AuthService auth;

  @override
  State<EduIdApp> createState() => _EduIdAppState();
}

/// Watches the app lifecycle so the PIN gate re-arms after the app has been
/// in the background longer than the user's auto-lock timeout (Settings →
/// Security). Same pattern as banking apps.
class _EduIdAppState extends State<EduIdApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.paused:
      case AppLifecycleState.hidden:
        widget.auth.appPaused();
      case AppLifecycleState.resumed:
        widget.auth.appResumed();
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'EduID',
      debugShowCheckedModeBanner: false,
      theme: buildAppTheme(),
      home: const _Gate(),
    );
  }
}

/// Routes by auth state and role:
///   signed out → login · PIN pending → unlock ·
///   teacher / school admin → home · other roles → coming-soon notice.
class _Gate extends StatelessWidget {
  const _Gate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    if (!auth.signedIn) return const LoginScreen();
    if (auth.locked) return const PinScreen(mode: PinMode.unlock);

    final role = auth.role;
    if (role == 'teacher' || role == 'school_admin') return const HomeScreen();
    return RoleNoticeScreen(role: role);
  }
}
