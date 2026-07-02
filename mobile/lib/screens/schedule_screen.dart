import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/kit.dart';

/// "My Schedule" — Today + Week, from the offline cache (auto-refreshed by the
/// SyncEngine). The period happening right now is highlighted.
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
    return AppCard(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      radius: 12,
      borderColor: now ? AppColors.primary : AppColors.border,
      child: Row(
        children: [
          SizedBox(
            width: 62,
            child: Text(
              '${_hhmm(p.startTime)}\n${_hhmm(p.endTime)}',
              style: const TextStyle(fontSize: 11.5, color: AppColors.inkLo),
            ),
          ),
          Container(
            width: 3,
            height: 34,
            margin: const EdgeInsets.only(right: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(2),
              color: now ? AppColors.primary : AppColors.border,
            ),
          ),
          Expanded(
            child: p.isBreak
                ? const Text('Break',
                    style: TextStyle(color: AppColors.inkLo, fontSize: 13.5))
                : p.className == null
                    ? Text('Free — ${p.label}',
                        style: const TextStyle(
                            color: AppColors.inkLo, fontSize: 13.5))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('${p.subjectName ?? p.label} · ${p.className}',
                              style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.ink,
                                  fontSize: 14)),
                          if (p.room != null)
                            Text('Room ${p.room}',
                                style: const TextStyle(
                                    fontSize: 11.5, color: AppColors.inkLo)),
                        ],
                      ),
          ),
          if (now) const StatusPill('NOW', color: AppColors.primary),
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
          title: const Text('My Schedule'),
          bottom: const TabBar(
            indicatorColor: AppColors.primary,
            labelColor: AppColors.ink,
            unselectedLabelColor: AppColors.inkLo,
            dividerColor: Colors.transparent,
            tabs: [Tab(text: 'Today'), Tab(text: 'Week')],
          ),
        ),
        body: TabBarView(
          children: [
            _today.isEmpty
                ? _empty()
                : ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      for (final p in _today) _slotTile(p, highlightNow: true)
                    ],
                  ),
            _week.isEmpty
                ? _empty()
                : ListView(
                    padding: const EdgeInsets.all(20),
                    children: [
                      for (var day = 1; day <= 7; day++)
                        if ((_week[day] ?? [])
                            .any((p) => p.className != null || p.isBreak)) ...[
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8, top: 10),
                            child: SectionLabel(_dayNames[day - 1]),
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

  Widget _empty() => const Center(
        child: Padding(
          padding: EdgeInsets.all(32),
          child: Text(
            'No schedule yet.\nIt appears automatically once the school sets up its timetable.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.inkMid, fontSize: 13),
          ),
        ),
      );
}
