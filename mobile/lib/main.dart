import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api_client.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/photo_sessions_screen.dart';
import 'screens/schedule_screen.dart';
import 'screens/settings_screen.dart';
import 'screens/parent_home_screen.dart';
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

  // Automatic sync: runs whenever a TEACHER/ADMIN is signed in and unlocked.
  // Parents don't sync class rosters — their screens fetch on demand with a
  // local cache (the teacher endpoints would 403 a parent token anyway).
  void driveSync() {
    final teacherish = auth.role == 'teacher' || auth.role == 'school_admin';
    if (auth.signedIn && !auth.locked && teacherish) {
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
      home: const SplashGate(),
    );
  }
}

/// Lightweight branded splash: one implicit scale+fade (~800 ms), no
/// packages, no images — negligible cost on low-end devices. Shown once per
/// cold start, then hands over to the auth gate.
class SplashGate extends StatefulWidget {
  const SplashGate({super.key});

  @override
  State<SplashGate> createState() => _SplashGateState();
}

class _SplashGateState extends State<SplashGate> {
  bool _shown = false; // logo pop-in
  bool _done = false;  // splash finished

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) setState(() => _shown = true);
    });
    Future.delayed(const Duration(milliseconds: 950), () {
      if (mounted) setState(() => _done = true);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_done) return const _Gate();

    return Scaffold(
      backgroundColor: AppColors.primary,
      body: Center(
        child: AnimatedScale(
          scale: _shown ? 1 : 0.7,
          duration: const Duration(milliseconds: 450),
          curve: Curves.easeOutBack,
          child: AnimatedOpacity(
            opacity: _shown ? 1 : 0,
            duration: const Duration(milliseconds: 350),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const EduIdMark(
                    size: 88, background: Colors.white, glyphColor: AppColors.primary),
                const SizedBox(height: 20),
                const Text('EduID',
                    style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                        letterSpacing: 0.2)),
                const SizedBox(height: 4),
                const Text('Your identity. Your access.',
                    style: TextStyle(fontSize: 13.5, color: Colors.white70)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Routes by auth state and role:
///   signed out → login · PIN pending → unlock ·
///   teacher → teacher shell · school admin → admin shell ·
///   parent → parent home · other roles → coming-soon notice.
class _Gate extends StatelessWidget {
  const _Gate();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    if (!auth.signedIn) return const LoginScreen();
    if (auth.locked) return const PinScreen(mode: PinMode.unlock);

    final role = auth.role;
    if (role == 'teacher') return const TeacherShell();
    if (role == 'school_admin') return const AdminShell();
    if (role == 'parent') return const ParentHomeScreen();
    return RoleNoticeScreen(role: role);
  }
}

/// Teacher: their daily work — classes & scanning, schedule, settings.
class TeacherShell extends StatefulWidget {
  const TeacherShell({super.key});

  @override
  State<TeacherShell> createState() => _TeacherShellState();
}

class _TeacherShellState extends State<TeacherShell> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _tab, children: const [
        HomeScreen(),
        ScheduleScreen(),
        SettingsScreen(),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        backgroundColor: AppColors.surface,
        indicatorColor: AppColors.primarySoft,
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.home_outlined),
              selectedIcon: Icon(Icons.home_rounded, color: AppColors.primary),
              label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.calendar_month_outlined),
              selectedIcon:
                  Icon(Icons.calendar_month_rounded, color: AppColors.primary),
              label: 'Schedule'),
          NavigationDestination(
              icon: Icon(Icons.settings_outlined),
              selectedIcon:
                  Icon(Icons.settings_rounded, color: AppColors.primary),
              label: 'Settings'),
        ],
      ),
    );
  }
}

/// School admin: oversight + operations — home, Photo Session Mode,
/// schedule, settings. Different job, different shell.
class AdminShell extends StatefulWidget {
  const AdminShell({super.key});

  @override
  State<AdminShell> createState() => _AdminShellState();
}

class _AdminShellState extends State<AdminShell> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _tab, children: const [
        HomeScreen(),
        PhotoSessionsScreen(),
        ScheduleScreen(),
        SettingsScreen(),
      ]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        backgroundColor: AppColors.surface,
        indicatorColor: AppColors.primarySoft,
        destinations: const [
          NavigationDestination(
              icon: Icon(Icons.space_dashboard_outlined),
              selectedIcon:
                  Icon(Icons.space_dashboard_rounded, color: AppColors.primary),
              label: 'Home'),
          NavigationDestination(
              icon: Icon(Icons.photo_camera_outlined),
              selectedIcon:
                  Icon(Icons.photo_camera_rounded, color: AppColors.primary),
              label: 'Photos'),
          NavigationDestination(
              icon: Icon(Icons.calendar_month_outlined),
              selectedIcon:
                  Icon(Icons.calendar_month_rounded, color: AppColors.primary),
              label: 'Schedule'),
          NavigationDestination(
              icon: Icon(Icons.settings_outlined),
              selectedIcon:
                  Icon(Icons.settings_rounded, color: AppColors.primary),
              label: 'Settings'),
        ],
      ),
    );
  }
}
