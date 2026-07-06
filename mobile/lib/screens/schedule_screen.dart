import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/kit.dart';
import '../ui/skeleton.dart';

/// My Schedule — one day at a time with a horizontal day selector (the
/// pattern school apps like Untis use): no more endless scrolling when a
/// school runs 8 periods + breaks. Defaults to today; subjects are
/// colour-coded with the colour the school set per subject.
class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  Map<int, List<PeriodSlot>> _week = {};
  bool _loading = true;
  late int _selectedDay;

  static const _dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  static const _dayFull = [
    'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'
  ];

  @override
  void initState() {
    super.initState();
    _selectedDay = DateTime.now().weekday; // 1=Mon … 7=Sun
    _load();
  }

  Future<void> _load() async {
    final week = await DataService.instance.cachedScheduleWeek();
    if (!mounted) return;
    setState(() {
      _week = week;
      _loading = false;
      // If today isn't a school day (e.g. Sunday), land on the first one.
      if (!_week.containsKey(_selectedDay) && _week.isNotEmpty) {
        _selectedDay = _week.keys.reduce((a, b) => a < b ? a : b);
      }
    });
  }

  String _hhmm(String t) => t.length >= 5 ? t.substring(0, 5) : t;

  bool _isNow(PeriodSlot p) {
    if (_selectedDay != DateTime.now().weekday) return false;
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

  /// Subject colour → a usable accent (falls back to the brand green).
  Color _subjectColor(PeriodSlot p) {
    final hex = p.subjectColor;
    if (hex != null && RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(hex)) {
      return Color(int.parse('FF${hex.substring(1)}', radix: 16));
    }
    return AppColors.primary;
  }

  @override
  Widget build(BuildContext context) {
    final days = _week.keys.toList()..sort();
    final slots = _week[_selectedDay] ?? const <PeriodSlot>[];
    final teaching = slots.where((p) => p.className != null || p.isBreak).toList();

    return Scaffold(
      appBar: AppBar(title: const Text('My Schedule')),
      body: _loading
          ? const SkeletonList()
          : _week.isEmpty
              ? const StateMessage(
                  icon: Icons.calendar_today_outlined,
                  title: 'No schedule yet',
                  subtitle:
                      'Your timetable appears here automatically once the school sets it up.',
                )
              : Column(
                  children: [
                    // ── Day selector ────────────────────────────
                    SizedBox(
                      height: 64,
                      child: ListView.separated(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 10),
                        scrollDirection: Axis.horizontal,
                        itemCount: days.length,
                        separatorBuilder: (_, __) => const SizedBox(width: 8),
                        itemBuilder: (_, i) {
                          final d = days[i];
                          final on = d == _selectedDay;
                          final isToday = d == DateTime.now().weekday;
                          return GestureDetector(
                            onTap: () => setState(() => _selectedDay = d),
                            child: AnimatedContainer(
                              duration: const Duration(milliseconds: 160),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 18, vertical: 8),
                              decoration: BoxDecoration(
                                color: on ? AppColors.primary : AppColors.surface,
                                borderRadius: BorderRadius.circular(22),
                                border: Border.all(
                                    color: on
                                        ? AppColors.primary
                                        : AppColors.border),
                              ),
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Text(_dayNames[d - 1],
                                      style: TextStyle(
                                          fontSize: 13.5,
                                          fontWeight: FontWeight.w700,
                                          color: on
                                              ? Colors.white
                                              : AppColors.inkMid)),
                                  if (isToday)
                                    Container(
                                      margin: const EdgeInsets.only(top: 3),
                                      width: 5,
                                      height: 5,
                                      decoration: BoxDecoration(
                                          shape: BoxShape.circle,
                                          color: on
                                              ? Colors.white
                                              : AppColors.primary),
                                    ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    // ── Day's lessons ───────────────────────────
                    Expanded(
                      child: teaching.isEmpty
                          ? StateMessage(
                              icon: Icons.beach_access_outlined,
                              title: 'Nothing on ${_dayFull[_selectedDay - 1]}',
                              subtitle: 'No lessons scheduled for you this day.',
                            )
                          : ListView(
                              padding: const EdgeInsets.fromLTRB(16, 4, 16, 20),
                              children: [
                                for (final p in teaching) _slotTile(p),
                              ],
                            ),
                    ),
                  ],
                ),
    );
  }

  Widget _slotTile(PeriodSlot p) {
    final now = _isNow(p);
    final color = _subjectColor(p);

    if (p.isBreak) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Row(children: [
          const Expanded(child: Divider()),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Text('☕ ${p.label} · ${_hhmm(p.startTime)}–${_hhmm(p.endTime)}',
                style: const TextStyle(
                    fontSize: 12, color: AppColors.inkLo,
                    fontWeight: FontWeight.w600)),
          ),
          const Expanded(child: Divider()),
        ]),
      );
    }

    return AppCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: EdgeInsets.zero,
      radius: 14,
      borderColor: now ? AppColors.primary : AppColors.border,
      child: IntrinsicHeight(
        child: Row(children: [
          // Subject colour bar — instant visual scan of the day.
          Container(
            width: 6,
            decoration: BoxDecoration(
              color: color,
              borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(14),
                  bottomLeft: Radius.circular(14)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            child: SizedBox(
              width: 60,
              child: Text('${_hhmm(p.startTime)}\n${_hhmm(p.endTime)}',
                  style:
                      const TextStyle(fontSize: 11.5, color: AppColors.inkLo)),
            ),
          ),
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(p.subjectName ?? p.label,
                    style: const TextStyle(
                        fontSize: 14.5, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(
                  [p.className, if (p.room != null) 'Room ${p.room}']
                      .whereType<String>()
                      .join(' · '),
                  style:
                      const TextStyle(fontSize: 12, color: AppColors.inkMid),
                ),
              ],
            ),
          ),
          if (now)
            Container(
              margin: const EdgeInsets.only(right: 12),
              padding:
                  const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                  color: AppColors.primarySoft,
                  borderRadius: BorderRadius.circular(8)),
              child: const Text('Now',
                  style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary)),
            ),
        ]),
      ),
    );
  }
}
