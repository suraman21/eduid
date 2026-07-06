import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api_client.dart';
import 'screens/classes_screen.dart';
import 'screens/grades_tab_screen.dart';
import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
// Photo Session Mode disabled 2026-07 — screen kept but no longer wired.
// import 'screens/photo_sessions_screen.dart';
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

  // A 401 on any data request (token expired or revoked server-side) ends the
  // session and routes back to login — the user is never stranded on a dead
  // token with silently failing background syncs.
  ApiClient.instance.onUnauthorized = auth.handleUnauthorized;

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
    if (role == 'parent') return const ParentShell();
    return RoleNoticeScreen(role: role);
  }
}

/// Teacher: Home · Schedule · Classes · Grades · More — the daily workflow,
/// matching the design's five-tab bar.
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
      body: IndexedStack(index: _tab, children: [
        HomeScreen(
          onOpenSchedule: () => setState(() => _tab = 1),
          onOpenClasses: () => setState(() => _tab = 2),
        ),
        const ScheduleScreen(),
        const ClassesScreen(),
        const GradesTabScreen(),
        const SettingsScreen(),
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
              label: 'Timetable'),
          NavigationDestination(
              icon: Icon(Icons.grid_view_outlined),
              selectedIcon:
                  Icon(Icons.grid_view_rounded, color: AppColors.primary),
              label: 'Classes'),
          NavigationDestination(
              icon: Icon(Icons.insights_outlined),
              selectedIcon:
                  Icon(Icons.insights_rounded, color: AppColors.primary),
              label: 'Grades'),
          NavigationDestination(
              icon: Icon(Icons.menu_rounded),
              selectedIcon: Icon(Icons.menu_rounded, color: AppColors.primary),
              label: 'More'),
        ],
      ),
    );
  }
}

/// School admin: Home · Classes · More. Class rosters and gradebooks are
/// reachable from Classes.
///
/// NOTE: the "Photos" tab (Photo Session Mode) is disabled as of 2026-07 —
/// schools issue ID cards before the first class, so photographing students
/// live wasn't useful. The screens/service stay in the tree but are no longer
/// wired here; re-add PhotoSessionsScreen as a tab (and uncomment the API
/// routes) to restore it.
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
      body: IndexedStack(index: _tab, children: [
        HomeScreen(onOpenClasses: () => setState(() => _tab = 1)),
        const ClassesScreen(),
        const SettingsScreen(),
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
              icon: Icon(Icons.grid_view_outlined),
              selectedIcon:
                  Icon(Icons.grid_view_rounded, color: AppColors.primary),
              label: 'Classes'),
          NavigationDestination(
              icon: Icon(Icons.menu_rounded),
              selectedIcon: Icon(Icons.menu_rounded, color: AppColors.primary),
              label: 'More'),
        ],
      ),
    );
  }
}

/// Parent: Home (my children) · More. A lean two-tab bar keeps the app's
/// navigation consistent without inventing features parents don't have.
class ParentShell extends StatefulWidget {
  const ParentShell({super.key});

  @override
  State<ParentShell> createState() => _ParentShellState();
}

class _ParentShellState extends State<ParentShell> {
  int _tab = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _tab, children: const [
        ParentHomeScreen(),
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
              icon: Icon(Icons.person_outline_rounded),
              selectedIcon:
                  Icon(Icons.person_rounded, color: AppColors.primary),
              label: 'Profile'),
        ],
      ),
    );
  }
}
