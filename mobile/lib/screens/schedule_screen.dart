import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/kit.dart';
import '../ui/skeleton.dart';

enum _View { weekly, daily, monthly }

/// My Timetable — Weekly · Daily · Monthly, matching the design. The weekly
/// timetable repeats, so any date maps to its weekday's periods; dates are
/// computed from the device clock (the "current date" problem), and a Today
/// button always jumps back. Subjects are colour-coded and icon-tagged.
class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  Map<int, List<PeriodSlot>> _week = {}; // weekday (1=Mon..7=Sun) → periods
  Map<int, int> _studentCounts = {};
  bool _loading = true;

  _View _view = _View.weekly;
  late DateTime _selected; // the focused date

  static const _dayShort = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

  @override
  void initState() {
    super.initState();
    _selected = _today();
    _load();
  }

  DateTime _today() {
    final n = DateTime.now();
    return DateTime(n.year, n.month, n.day);
  }

  Future<void> _load() async {
    final week = await DataService.instance.cachedScheduleWeek();
    final classes = await DataService.instance.cachedClasses();
    if (!mounted) return;
    setState(() {
      _week = week;
      _studentCounts = {for (final c in classes) c.id: c.studentCount};
      _loading = false;
    });
  }

  // ── date helpers ──────────────────────────────────────────────

  DateTime get _monday =>
      _selected.subtract(Duration(days: _selected.weekday - 1));

  List<PeriodSlot> _periodsFor(DateTime date) {
    final all = _week[date.weekday] ?? const <PeriodSlot>[];
    return all.where((p) => p.className != null || p.isBreak).toList();
  }

  bool _hasClasses(DateTime date) =>
      (_week[date.weekday] ?? const <PeriodSlot>[])
          .any((p) => p.className != null);

  bool _isToday(DateTime d) {
    final t = _today();
    return d.year == t.year && d.month == t.month && d.day == t.day;
  }

  bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;

  /// ISO-8601 week number (matches the design's "Week 20").
  int _isoWeek(DateTime date) {
    final thursday = date.add(Duration(days: 4 - (date.weekday)));
    final firstJan = DateTime(thursday.year, 1, 1);
    return 1 + (thursday.difference(firstJan).inDays / 7).floor();
  }

  String _hhmm(String t) => t.length >= 5 ? t.substring(0, 5) : t;

  bool _isNow(PeriodSlot p, DateTime date) {
    if (!_isToday(date)) return false;
    final now = TimeOfDay.now();
    final nowM = now.hour * 60 + now.minute;
    int toM(String hhmm) {
      final parts = hhmm.split(':');
      if (parts.length < 2) return -1;
      return (int.tryParse(parts[0]) ?? 0) * 60 + (int.tryParse(parts[1]) ?? 0);
    }

    final s = toM(p.startTime), e = toM(p.endTime);
    return s >= 0 && nowM >= s && nowM < e;
  }

  Color _subjectColor(PeriodSlot p) {
    final hex = p.subjectColor;
    if (hex != null && RegExp(r'^#[0-9a-fA-F]{6}$').hasMatch(hex)) {
      return Color(int.parse('FF${hex.substring(1)}', radix: 16));
    }
    return AppColors.primary;
  }

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

  // ── build ─────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: _loading
            ? const SkeletonList()
            : Column(
                children: [
                  _header(),
                  _viewSwitcher(),
                  const SizedBox(height: 6),
                  Expanded(
                    child: _week.isEmpty
                        ? const StateMessage(
                            icon: Icons.calendar_today_outlined,
                            title: 'No timetable yet',
                            subtitle:
                                'Your timetable appears here automatically once the school sets it up.',
                          )
                        : switch (_view) {
                            _View.weekly => _weeklyView(),
                            _View.daily => _dailyView(),
                            _View.monthly => _monthlyView(),
                          },
                  ),
                ],
              ),
      ),
    );
  }

  Widget _header() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 12, 16, 8),
      child: Row(
        children: [
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('My Timetable',
                    style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                        letterSpacing: -0.5)),
                SizedBox(height: 2),
                Text('Stay organized, teach better.',
                    style: TextStyle(fontSize: 13, color: AppColors.inkLo)),
              ],
            ),
          ),
          OutlinedButton.icon(
            onPressed: () => setState(() => _selected = _today()),
            icon: const Icon(Icons.today_rounded, size: 17),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(0, 40),
              padding: const EdgeInsets.symmetric(horizontal: 14),
            ),
            label: const Text('Today'),
          ),
        ],
      ),
    );
  }

  Widget _viewSwitcher() {
    Widget seg(_View v, String label, IconData icon) {
      final on = _view == v;
      return Expanded(
        child: Material(
          color: on ? AppColors.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          child: InkWell(
            onTap: () => setState(() => _view = v),
            borderRadius: BorderRadius.circular(12),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 10),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(icon,
                      size: 16,
                      color: on ? Colors.white : AppColors.inkMid),
                  const SizedBox(width: 6),
                  Text(label,
                      style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: on ? Colors.white : AppColors.inkMid)),
                ],
              ),
            ),
          ),
        ),
      );
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(children: [
        seg(_View.weekly, 'Weekly', Icons.calendar_view_week_rounded),
        seg(_View.daily, 'Daily', Icons.view_day_rounded),
        seg(_View.monthly, 'Monthly', Icons.calendar_month_rounded),
      ]),
    );
  }

  // ── Weekly ────────────────────────────────────────────────────

  Widget _weeklyView() {
    final monday = _monday;
    final end = monday.add(const Duration(days: 6));
    final periods = _periodsFor(_selected);

    return Column(
      children: [
        // Week nav
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 6),
          child: Row(
            children: [
              _navArrow(Icons.chevron_left_rounded,
                  () => setState(() => _selected = _selected.subtract(const Duration(days: 7)))),
              Expanded(
                child: Column(
                  children: [
                    Text(
                      '${DateFormat('MMM d').format(monday)} – ${DateFormat('MMM d, y').format(end)}',
                      style: const TextStyle(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w800,
                          color: AppColors.ink),
                    ),
                    Text('Week ${_isoWeek(_selected)}',
                        style: const TextStyle(
                            fontSize: 11.5, color: AppColors.inkLo)),
                  ],
                ),
              ),
              _navArrow(Icons.chevron_right_rounded,
                  () => setState(() => _selected = _selected.add(const Duration(days: 7)))),
            ],
          ),
        ),
        // Day strip (Mon..Sun of the current week)
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              for (int i = 0; i < 7; i++)
                Expanded(child: _weekDayCell(monday.add(Duration(days: i)))),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Expanded(child: _periodList(_selected, periods)),
      ],
    );
  }

  Widget _weekDayCell(DateTime date) {
    final on = _sameDay(date, _selected);
    final isToday = _isToday(date);
    final has = _hasClasses(date);
    return GestureDetector(
      onTap: () => setState(() => _selected = date),
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 2),
        padding: const EdgeInsets.symmetric(vertical: 8),
        decoration: BoxDecoration(
          color: on ? AppColors.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
              color: on
                  ? AppColors.primary
                  : (isToday ? AppColors.primary : Colors.transparent)),
        ),
        child: Column(
          children: [
            Text(_dayShort[date.weekday - 1],
                style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: on ? Colors.white70 : AppColors.inkLo)),
            const SizedBox(height: 4),
            Text('${date.day}',
                style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: on
                        ? Colors.white
                        : (isToday ? AppColors.primary : AppColors.ink))),
            const SizedBox(height: 4),
            Container(
              width: 5,
              height: 5,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: has
                    ? (on ? Colors.white : _dotColor(date))
                    : Colors.transparent,
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// A representative colour for a day's first lesson (the little dot).
  Color _dotColor(DateTime date) {
    final first = (_week[date.weekday] ?? const <PeriodSlot>[])
        .firstWhere((p) => p.className != null,
            orElse: () => const PeriodSlot(
                periodId: 0,
                label: '',
                startTime: '',
                endTime: '',
                isBreak: false));
    return _subjectColor(first);
  }

  // ── Daily ─────────────────────────────────────────────────────

  Widget _dailyView() {
    final periods = _periodsFor(_selected);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
          child: Row(
            children: [
              _navArrow(Icons.chevron_left_rounded,
                  () => setState(() => _selected = _selected.subtract(const Duration(days: 1)))),
              Expanded(
                child: Text(
                  DateFormat('EEEE, MMMM d, y').format(_selected),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink),
                ),
              ),
              _navArrow(Icons.chevron_right_rounded,
                  () => setState(() => _selected = _selected.add(const Duration(days: 1)))),
            ],
          ),
        ),
        Expanded(child: _periodList(_selected, periods)),
        if (!_isToday(_selected))
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: () => setState(() => _selected = _today()),
                icon: const Icon(Icons.today_rounded, size: 18),
                label: const Text('Back to Today'),
              ),
            ),
          ),
      ],
    );
  }

  // ── Monthly ───────────────────────────────────────────────────

  Widget _monthlyView() {
    final first = DateTime(_selected.year, _selected.month, 1);
    // Calendar starts on Sunday: Sun=0 … Sat=6.
    final lead = first.weekday % 7;
    final gridStart = first.subtract(Duration(days: lead));
    final periods = _periodsFor(_selected);

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
      children: [
        // Month nav
        Row(
          children: [
            _navArrow(
                Icons.chevron_left_rounded,
                () => setState(() => _selected =
                    DateTime(_selected.year, _selected.month - 1, 1))),
            Expanded(
              child: Text(DateFormat('MMMM y').format(_selected),
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: AppColors.ink)),
            ),
            _navArrow(
                Icons.chevron_right_rounded,
                () => setState(() => _selected =
                    DateTime(_selected.year, _selected.month + 1, 1))),
          ],
        ),
        const SizedBox(height: 8),
        // Weekday header
        Row(
          children: [
            for (final d in const ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])
              Expanded(
                child: Center(
                  child: Text(d,
                      style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: AppColors.inkLo)),
                ),
              ),
          ],
        ),
        const SizedBox(height: 4),
        // 6 weeks grid
        for (int w = 0; w < 6; w++)
          Row(
            children: [
              for (int d = 0; d < 7; d++)
                Expanded(
                    child: _monthCell(gridStart.add(Duration(days: w * 7 + d)))),
            ],
          ),
        const SizedBox(height: 16),
        // Selected day's classes
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(DateFormat('MMMM d, y').format(_selected),
                style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: AppColors.ink)),
            Text(
                '${periods.where((p) => p.className != null).length} classes',
                style: const TextStyle(fontSize: 12, color: AppColors.inkLo)),
          ],
        ),
        const SizedBox(height: 8),
        if (periods.where((p) => p.className != null).isEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 20),
            child: Text('No classes this day.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.inkLo, fontSize: 13)),
          )
        else
          for (final p in periods) _periodCard(p, _selected),
      ],
    );
  }

  Widget _monthCell(DateTime date) {
    final inMonth = date.month == _selected.month;
    final on = _sameDay(date, _selected);
    final isToday = _isToday(date);
    final has = _hasClasses(date) && inMonth;
    return GestureDetector(
      onTap: () => setState(() => _selected = date),
      child: Container(
        height: 44,
        margin: const EdgeInsets.all(2),
        decoration: BoxDecoration(
          color: on ? AppColors.primary : Colors.transparent,
          shape: BoxShape.circle,
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('${date.day}',
                style: TextStyle(
                    fontSize: 13,
                    fontWeight:
                        isToday ? FontWeight.w800 : FontWeight.w600,
                    color: on
                        ? Colors.white
                        : inMonth
                            ? (isToday ? AppColors.primary : AppColors.ink)
                            : AppColors.inkLo.withOpacity(0.5))),
            const SizedBox(height: 2),
            Container(
              width: 4,
              height: 4,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: has
                    ? (on ? Colors.white : _dotColor(date))
                    : Colors.transparent,
              ),
            ),
          ],
        ),
      ),
    );
  }

  // ── shared period rendering ───────────────────────────────────

  Widget _periodList(DateTime date, List<PeriodSlot> periods) {
    if (periods.where((p) => !p.isBreak).isEmpty) {
      return StateMessage(
        icon: Icons.beach_access_outlined,
        title: 'Nothing on ${DateFormat('EEEE').format(date)}',
        subtitle: 'No lessons scheduled for you this day.',
      );
    }
    return RefreshIndicator(
      color: AppColors.primary,
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 20),
        children: [for (final p in periods) _periodCard(p, date)],
      ),
    );
  }

  /// One timetable row: stacked time on the left, then a tinted card with a
  /// circular subject icon, subject name, class, and room — the design's card.
  Widget _periodCard(PeriodSlot p, DateTime date) {
    if (p.isBreak) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            SizedBox(
              width: 48,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(_hhmm(p.startTime),
                      style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: AppColors.inkMid)),
                  Text(_hhmm(p.endTime),
                      style: const TextStyle(
                          fontSize: 11, color: AppColors.inkLo)),
                ],
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(
                      color: AppColors.border,
                      style: BorderStyle.solid),
                  color: AppColors.bg,
                ),
                child: const Row(
                  children: [
                    Icon(Icons.local_cafe_rounded,
                        size: 18, color: AppColors.inkLo),
                    SizedBox(width: 8),
                    Text('Break Time',
                        style: TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w600,
                            color: AppColors.inkMid)),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
    }

    final color = _subjectColor(p);
    final now = _isNow(p, date);
    final count = p.classId != null ? _studentCounts[p.classId] : null;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          SizedBox(
            width: 48,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_hhmm(p.startTime),
                    style: const TextStyle(
                        fontSize: 12.5,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink)),
                Text(_hhmm(p.endTime),
                    style: const TextStyle(
                        fontSize: 11, color: AppColors.inkLo)),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: color.withOpacity(0.10),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                    color: now ? color : color.withOpacity(0.18),
                    width: now ? 1.6 : 1),
              ),
              child: Row(
                children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration:
                        BoxDecoration(color: color, shape: BoxShape.circle),
                    child: Icon(_subjectIcon(p.subjectName),
                        color: Colors.white, size: 20),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Flexible(
                              child: Text(p.subjectName ?? p.label,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                      fontSize: 14.5,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.ink)),
                            ),
                            if (now) ...[
                              const SizedBox(width: 6),
                              const StatusPill('Now', color: AppColors.primary),
                            ],
                          ],
                        ),
                        const SizedBox(height: 2),
                        Text(
                          [
                            p.className,
                            if (count != null) '$count students',
                          ].whereType<String>().join(' · '),
                          style: const TextStyle(
                              fontSize: 12, color: AppColors.inkMid),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      if (p.room != null)
                        Text('Room ${p.room}',
                            style: TextStyle(
                                fontSize: 11.5,
                                fontWeight: FontWeight.w700,
                                color: color)),
                      const SizedBox(height: 2),
                      const Icon(Icons.chevron_right_rounded,
                          size: 18, color: AppColors.inkLo),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _navArrow(IconData icon, VoidCallback onTap) {
    return Material(
      color: AppColors.surface,
      shape: const CircleBorder(side: BorderSide(color: AppColors.border)),
      child: InkWell(
        onTap: onTap,
        customBorder: const CircleBorder(),
        child: SizedBox(
          width: 38,
          height: 38,
          child: Icon(icon, size: 22, color: AppColors.inkMid),
        ),
      ),
    );
  }
}
