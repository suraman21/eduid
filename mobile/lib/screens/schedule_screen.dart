import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';

/// "My Schedule" — Today + Week tabs, read from the offline cache
/// (refreshed on every sync). Highlights the period happening right now.
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
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: now ? const Color(0xFFEEF2FF) : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
            color: now ? const Color(0xFF6366F1) : const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 78,
            child: Text(
              '${_hhmm(p.startTime)}\n${_hhmm(p.endTime)}',
              style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
            ),
          ),
          Expanded(
            child: p.isBreak
                ? Text('☕ ${p.label}',
                    style: TextStyle(color: Colors.grey.shade500))
                : p.className == null
                    ? Text('Free — ${p.label}',
                        style: TextStyle(color: Colors.grey.shade500))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${p.subjectName ?? p.label} · ${p.className}',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w700)),
                          if (p.room != null)
                            Text('Room ${p.room}',
                                style: TextStyle(
                                    fontSize: 12, color: Colors.grey.shade600)),
                        ],
                      ),
          ),
          if (now)
            const Padding(
              padding: EdgeInsets.only(left: 6),
              child: Text('NOW',
                  style: TextStyle(
                      color: Color(0xFF4F46E5),
                      fontWeight: FontWeight.w800,
                      fontSize: 11)),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('My Schedule',
              style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          bottom: const TabBar(tabs: [Tab(text: 'Today'), Tab(text: 'Week')]),
        ),
        body: TabBarView(
          children: [
            // Today
            _today.isEmpty
                ? _empty()
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      for (final p in _today) _slotTile(p, highlightNow: true)
                    ],
                  ),
            // Week
            _week.isEmpty
                ? _empty()
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      for (var day = 1; day <= 7; day++)
                        if ((_week[day] ?? [])
                            .any((p) => p.className != null || p.isBreak)) ...[
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8, top: 8),
                            child: Text(_dayNames[day - 1],
                                style: const TextStyle(
                                    fontWeight: FontWeight.w800, fontSize: 15)),
                          ),
                          for (final p in _week[day]!) _slotTile(p),
                        ],
                    ],
                  ),
          ],
        ),
      ),
    );
  }

  Widget _empty() => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Text(
            'No schedule cached yet.\nSync while online, or the school may not have set up its timetable.',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.grey.shade600),
          ),
        ),
      );
}
