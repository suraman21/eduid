import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/kit.dart';
import '../ui/skeleton.dart';

/// My Schedule — one day at a time with a dated week strip (the pattern school
/// apps like Untis use): tap a day, page between weeks with the arrows. The
/// weekly timetable repeats, so the dates change but the periods stay in sync.
/// Subjects are colour-coded and icon-tagged; each shows its class + roster
/// size so a teacher knows the room at a glance.
class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  Map<int, List<PeriodSlot>> _week = {};
  Map<int, int> _studentCounts = {};
  bool _loading = true;
  late int _selectedDay; // 1=Mon … 7=Sun
  int _weekOffset = 0; // 0 = this week, -1 = last, +1 = next

  static const _dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  @override
  void initState() {
    super.initState();
    _selectedDay = DateTime.now().weekday;
    _load();
  }

  Future<void> _load() async {
    final week = await DataService.instance.cachedScheduleWeek();
    final classes = await DataService.instance.cachedClasses();
    if (!mounted) return;
    setState(() {
      _week = week;
      _studentCounts = {for (final c in classes) c.id: c.studentCount};
      _loading = false;
      if (!_week.containsKey(_selectedDay) && _week.isNotEmpty) {
        _selectedDay = _week.keys.reduce((a, b) => a < b ? a : b);
      }
    });
  }

  /// The Monday of the currently displayed week.
  DateTime get _weekMonday {
    final now = DateTime.now();
    final monday = DateTime(now.year, now.month, now.day)
        .subtract(Duration(days: now.weekday - 1));
    return monday.add(Duration(days: 7 * _weekOffset));
  }

  DateTime _dateFor(int day) => _weekMonday.add(Duration(days: day - 1));

  bool _isToday(int day) {
    final d = _dateFor(day);
    final now = DateTime.now();
    return d.year == now.year && d.month == now.month && d.day == now.day;
  }

  String _hhmm(String t) => t.length >= 5 ? t.substring(0, 5) : t;

  bool _isNow(PeriodSlot p) {
    if (!_isToday(_selectedDay)) return false;
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

  Color _subjectColor(PeriodSlot p) {
    final hex = p.subjectColor;
    if (hex != null && RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(hex)) {
      return Color(int.parse('FF${hex.substring(1)}', radix: 16));
    }
    return AppColors.primary;
  }

  /// Best-effort subject → icon mapping so the day reads at a glance (matches
  /// the design's per-subject glyphs). Falls back to a book.
  IconData _subjectIcon(String? name) {
    final n = (name ?? '').toLowerCase();
    bool has(List<String> k) => k.any(n.contains);
    if (has(['math', 'algebra', 'geometry', 'calcul', 'arithmetic'])) {
      return Icons.calculate_rounded;
    }
    if (has(['physic', 'science', 'chem', 'biolog', 'lab'])) {
      return Icons.science_rounded;
    }
    if (has(['english', 'amharic', 'language', 'literat', 'writing'])) {
      return Icons.menu_book_rounded;
    }
    if (has(['history', 'civic', 'social', 'moral'])) {
      return Icons.account_balance_rounded;
    }
    if (has(['geograph', 'environment'])) return Icons.public_rounded;
    if (has(['physical', 'sport', 'gym', ' pe', 'p.e', 'athlet'])) {
      return Icons.directions_run_rounded;
    }
    if (has(['art', 'draw', 'paint', 'design'])) return Icons.palette_rounded;
    if (has(['music', 'song'])) return Icons.music_note_rounded;
    if (has(['computer', 'ict', 'technolog', 'coding', 'informat'])) {
      return Icons.computer_rounded;
    }
    if (has(['health', 'nutrit'])) return Icons.favorite_rounded;
    return Icons.menu_book_rounded;
  }

  @override
  Widget build(BuildContext context) {
    final days = _week.keys.toList()..sort();
    final slots = _week[_selectedDay] ?? const <PeriodSlot>[];
    final teaching =
        slots.where((p) => p.className != null || p.isBreak).toList();

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
                    // ── Week strip with prev/next arrows ──
                    Padding(
                      padding: const EdgeInsets.fromLTRB(8, 8, 8, 4),
                      child: Row(
                        children: [
                          IconButton(
                            icon: const Icon(Icons.chevron_left_rounded),
                            color: AppColors.inkMid,
                            onPressed: () =>
                                setState(() => _weekOffset -= 1),
                          ),
                          Expanded(
                            child: SizedBox(
                              height: 62,
                              child: ListView.separated(
                                padding:
                                    const EdgeInsets.symmetric(horizontal: 2),
                                scrollDirection: Axis.horizontal,
                                itemCount: days.length,
                                separatorBuilder: (_, __) =>
                                    const SizedBox(width: 8),
                                itemBuilder: (_, i) =>
                                    _dayChip(days[i]),
                              ),
                            ),
                          ),
                          IconButton(
                            icon: const Icon(Icons.chevron_right_rounded),
                            color: AppColors.inkMid,
                            onPressed: () =>
                                setState(() => _weekOffset += 1),
                          ),
                        ],
                      ),
                    ),
                    // ── Date heading ──
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 4, 20, 8),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          DateFormat('EEEE, MMMM d, y')
                              .format(_dateFor(_selectedDay)),
                          style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: AppColors.ink),
                        ),
                      ),
                    ),
                    // ── Day's lessons ──
                    Expanded(
                      child: teaching.isEmpty
                          ? StateMessage(
                              icon: Icons.beach_access_outlined,
                              title:
                                  'Nothing on ${DateFormat('EEEE').format(_dateFor(_selectedDay))}',
                              subtitle:
                                  'No lessons scheduled for you this day.',
                            )
                          : ListView(
                              padding:
                                  const EdgeInsets.fromLTRB(16, 4, 16, 20),
                              children: [
                                for (final p in teaching) _slotTile(p),
                              ],
                            ),
                    ),
                  ],
                ),
    );
  }

  Widget _dayChip(int day) {
    final on = day == _selectedDay;
    final isToday = _isToday(day);
    return GestureDetector(
      onTap: () => setState(() => _selectedDay = day),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 52,
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: on ? AppColors.primary : AppColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: on ? AppColors.primary : AppColors.border),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(_dayNames[day - 1],
                style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: on ? Colors.white70 : AppColors.inkLo)),
            const SizedBox(height: 3),
            Text('${_dateFor(day).day}',
                style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: on
                        ? Colors.white
                        : (isToday ? AppColors.primary : AppColors.ink))),
          ],
        ),
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
            child: Text(
                '☕ ${p.label} · ${_hhmm(p.startTime)}–${_hhmm(p.endTime)}',
                style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.inkLo,
                    fontWeight: FontWeight.w600)),
          ),
          const Expanded(child: Divider()),
        ]),
      );
    }

    final count = p.classId != null ? _studentCounts[p.classId] : null;

    return AppCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: EdgeInsets.zero,
      radius: 14,
      borderColor: now ? AppColors.primary : AppColors.border,
      child: IntrinsicHeight(
        child: Row(children: [
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
              width: 54,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(_hhmm(p.startTime),
                      style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: AppColors.ink)),
                  Text(_hhmm(p.endTime),
                      style: const TextStyle(
                          fontSize: 11.5, color: AppColors.inkLo)),
                ],
              ),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(p.subjectName ?? p.label,
                      style: const TextStyle(
                          fontSize: 14.5, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 2),
                  Text(
                    [
                      p.className,
                      if (p.room != null) 'Room ${p.room}',
                      if (count != null) '$count students',
                    ].whereType<String>().join(' · '),
                    style: const TextStyle(
                        fontSize: 12, color: AppColors.inkMid),
                  ),
                ],
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.only(right: 12, left: 6),
            child: now
                ? const StatusPill('Now', color: AppColors.primary)
                : Icon(_subjectIcon(p.subjectName),
                    color: color, size: 22),
          ),
        ]),
      ),
    );
  }
}
