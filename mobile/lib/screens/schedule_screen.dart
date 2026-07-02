import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/glass.dart';

/// "My Schedule" — Today + Week, from the offline cache (auto-refreshed by the
/// SyncEngine). The period happening right now glows.
class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  List<PeriodSlot> _today = [];
  Map<int, List<PeriodSlot>> _week = {};

  static const _dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final today = await DataService.instance.cachedScheduleToday();
    final week = await DataService.instance.cachedScheduleWeek();
    if (!mounted) return;
    setState(() {
      _today = today;
      _week = week;
    });
  }

  String _hhmm(String t) => t.length >= 5 ? t.substring(0, 5) : t;

  bool _isNow(PeriodSlot p) {
    final now = TimeOfDay.now();
    final nowM = now.hour * 60 + now.minute;
    int toM(String hhmm) {
      final parts = hhmm.split(':');
      if (parts.length < 2) return -1;
      return (int.tryParse(parts[0]) ?? 0) * 60 + (int.tryParse(parts[1]) ?? 0);
    }

    final start = toM(p.startTime);
    final end = toM(p.endTime);
    return start >= 0 && nowM >= start && nowM < end;
  }

  Widget _slotTile(PeriodSlot p, {bool highlightNow = false}) {
    final now = highlightNow && _isNow(p);
    return GlassCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      radius: 18,
      glow: now ? Palette.cyan : null,
      child: Row(
        children: [
          SizedBox(
            width: 62,
            child: Text(
              '${_hhmm(p.startTime)}\n${_hhmm(p.endTime)}',
              style: const TextStyle(fontSize: 11.5, color: Palette.textLo),
            ),
          ),
          Container(
            width: 3,
            height: 34,
            margin: const EdgeInsets.only(right: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(2),
              gradient: now
                  ? const LinearGradient(
                      colors: [Palette.cyan, Palette.violet],
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter)
                  : null,
              color: now ? null : Colors.white.withOpacity(0.10),
            ),
          ),
          Expanded(
            child: p.isBreak
                ? const Text('Break',
                    style: TextStyle(color: Palette.textLo, fontSize: 13.5))
                : p.className == null
                    ? Text('Free — ${p.label}',
                        style: const TextStyle(
                            color: Palette.textLo, fontSize: 13.5))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${p.subjectName ?? p.label} · ${p.className}',
                              style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: Palette.textHi,
                                  fontSize: 14)),
                          if (p.room != null)
                            Text('Room ${p.room}',
                                style: const TextStyle(
                                    fontSize: 11.5, color: Palette.textLo)),
                        ],
                      ),
          ),
          if (now)
            Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                gradient: Palette.accentGradient,
                borderRadius: BorderRadius.circular(999),
              ),
              child: const Text('NOW',
                  style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 10)),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return AppBackground(
      child: DefaultTabController(
        length: 2,
        child: Scaffold(
          backgroundColor: Colors.transparent,
          body: SafeArea(
            child: Column(
              children: [
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
                  child: Row(
                    children: [
                      GlassIconButton(
                          icon: Icons.arrow_back_rounded,
                          onTap: () => Navigator.of(context).pop()),
                      const SizedBox(width: 12),
                      const Text('My Schedule',
                          style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: Palette.textHi)),
                    ],
                  ),
                ),
                const TabBar(
                  indicatorColor: Palette.violet,
                  labelColor: Palette.textHi,
                  unselectedLabelColor: Palette.textLo,
                  dividerColor: Colors.transparent,
                  tabs: [Tab(text: 'Today'), Tab(text: 'Week')],
                ),
                Expanded(
                  child: TabBarView(
                    children: [
                      _today.isEmpty
                          ? _empty()
                          : ListView(
                              padding: const EdgeInsets.all(20),
                              children: [
                                for (final p in _today)
                                  _slotTile(p, highlightNow: true)
                              ],
                            ),
                      _week.isEmpty
                          ? _empty()
                          : ListView(
                              padding: const EdgeInsets.all(20),
                              children: [
                                for (var day = 1; day <= 7; day++)
                                  if ((_week[day] ?? []).any((p) =>
                                      p.className != null || p.isBreak)) ...[
                                    Padding(
                                      padding: const EdgeInsets.only(
                                          bottom: 8, top: 10),
                                      child: Text(
                                        _dayNames[day - 1].toUpperCase(),
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w800,
                                            fontSize: 11.5,
                                            letterSpacing: 1.6,
                                            color: Palette.textLo),
                                      ),
                                    ),
                                    for (final p in _week[day]!) _slotTile(p),
                                  ],
                              ],
                            ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _empty() => const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Text(
            'No schedule yet.\nIt appears automatically once the school sets up its timetable.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Palette.textLo, fontSize: 13),
          ),
        ),
      );
}
