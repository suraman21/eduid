import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/kit.dart';

/// Read-only gradebook for one class — weighted totals, letters and ranks
/// come pre-computed from the server. Opens INSTANTLY from the cache, then
/// refreshes from the network in the background (stale-while-revalidate).
class GradesScreen extends StatefulWidget {
  const GradesScreen({super.key, required this.schoolClass});

  final SchoolClass schoolClass;

  @override
  State<GradesScreen> createState() => _GradesScreenState();
}

class _GradesScreenState extends State<GradesScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  bool _sortByRank = true;
  final Set<int> _expanded = {};
  final Set<String> _expandedSubject = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    // 1. Cache first — paints immediately.
    final cached =
        await DataService.instance.gradesCached(widget.schoolClass.id);
    if (mounted && cached != null) {
      setState(() {
        _data = cached;
        _loading = false;
      });
    }
    // 2. Network refresh in the background.
    final fresh = await DataService.instance.grades(widget.schoolClass.id);
    if (!mounted) return;
    setState(() {
      _data = fresh ?? _data;
      _loading = false;
    });
  }

  Color _avgTone(num? avg) {
    if (avg == null) return AppColors.inkLo;
    if (avg >= 80) return AppColors.green;
    if (avg >= 50) return AppColors.blue;
    return AppColors.red;
  }

  @override
  Widget build(BuildContext context) {
    final students = List<Map<String, dynamic>>.from(
        (_data?['students'] as List?)?.cast<Map<String, dynamic>>() ?? []);
    final term = _data?['term'] as Map<String, dynamic>?;
    final weights = _data?['weights'] as Map<String, dynamic>?;

    final avgs = students
        .map((s) => s['term_avg'] as num?)
        .whereType<num>()
        .toList();
    final classAvg = avgs.isEmpty
        ? null
        : (avgs.reduce((a, b) => a + b) / avgs.length);

    final sorted = List<Map<String, dynamic>>.from(students);
    if (!_sortByRank) {
      sorted.sort((a, b) => (a['full_name']?.toString() ?? '')
          .compareTo(b['full_name']?.toString() ?? ''));
    }

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Grades · ${widget.schoolClass.name}',
                style: const TextStyle(
                    fontSize: 16, fontWeight: FontWeight.w800)),
            if (term != null)
              Text('${term['name']} · ${term['academic_year']}',
                  style: const TextStyle(
                      fontSize: 12, color: AppColors.inkLo)),
          ],
        ),
      ),
      body: _loading
          ? const Center(
              child: CircularProgressIndicator(
                  color: AppColors.primary, strokeWidth: 2.6))
          : _data == null
              ? const Center(
                  child: Padding(
                    padding: EdgeInsets.all(32),
                    child: Text(
                      'No grades available offline yet.\nOpen this screen once while online to cache it.',
                      textAlign: TextAlign.center,
                      style:
                          TextStyle(color: AppColors.inkMid, fontSize: 13),
                    ),
                  ),
                )
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 14, 20, 4),
                      child: Row(
                        children: [
                          Expanded(
                            child: StatTile(
                              value: classAvg != null
                                  ? '${classAvg.toStringAsFixed(1)}%'
                                  : '—',
                              label: 'Class average',
                              color: _avgTone(classAvg),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: StatTile(
                              value: '${students.length}',
                              label: 'Students',
                              color: AppColors.blue,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(20, 10, 20, 6),
                      child: Row(
                        children: [
                          const Text('Sort by',
                              style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.inkMid)),
                          const SizedBox(width: 10),
                          _sortChip('Rank', _sortByRank,
                              () => setState(() => _sortByRank = true)),
                          const SizedBox(width: 8),
                          _sortChip('Name (A–Z)', !_sortByRank,
                              () => setState(() => _sortByRank = false)),
                        ],
                      ),
                    ),
                    Expanded(
                      child: ListView.builder(
                        padding: const EdgeInsets.fromLTRB(20, 6, 20, 20),
                        itemCount: sorted.length,
                        itemBuilder: (context, i) {
                          final s = sorted[i];
                          final studentId = s['student_id'];
                          final subjects = (s['subjects'] as List?)
                                  ?.cast<Map<String, dynamic>>() ??
                              [];
                          final avg = s['term_avg'] as num?;
                          final rank = s['rank'];
                          final open = _expanded.contains(i);
                          return AppCard(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.symmetric(
                                horizontal: 14, vertical: 12),
                            radius: 14,
                            onTap: () => setState(() => open
                                ? _expanded.remove(i)
                                : _expanded.add(i)),
                            child: Column(
                              children: [
                                Row(
                                  children: [
                                    // Rank badge
                                    Container(
                                      width: 34,
                                      height: 34,
                                      alignment: Alignment.center,
                                      decoration: BoxDecoration(
                                        borderRadius:
                                            BorderRadius.circular(11),
                                        color: rank != null && rank <= 3
                                            ? AppColors.primary
                                            : AppColors.bg,
                                      ),
                                      child: Text(
                                        rank?.toString() ?? '—',
                                        style: TextStyle(
                                            fontWeight: FontWeight.w800,
                                            fontSize: 13,
                                            color: rank != null && rank <= 3
                                                ? Colors.white
                                                : AppColors.inkMid),
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                              s['full_name']?.toString() ??
                                                  '',
                                              overflow:
                                                  TextOverflow.ellipsis,
                                              style: const TextStyle(
                                                  fontWeight:
                                                      FontWeight.w700,
                                                  color: AppColors.ink,
                                                  fontSize: 14)),
                                          Text(
                                              s['student_uid']
                                                      ?.toString() ??
                                                  '',
                                              style: const TextStyle(
                                                  fontSize: 10.5,
                                                  color: AppColors.inkLo)),
                                        ],
                                      ),
                                    ),
                                    Text(avg != null ? '$avg%' : '—',
                                        style: TextStyle(
                                            fontWeight: FontWeight.w800,
                                            fontSize: 15,
                                            color: _avgTone(avg))),
                                    const SizedBox(width: 4),
                                    Icon(
                                        open
                                            ? Icons
                                                .keyboard_arrow_up_rounded
                                            : Icons
                                                .keyboard_arrow_down_rounded,
                                        size: 20,
                                        color: AppColors.inkLo),
                                  ],
                                ),
                                if (open) ...[
                                  const SizedBox(height: 10),
                                  const Divider(height: 1),
                                  const SizedBox(height: 6),
                                  for (final sub in subjects)
                                    _subjectRow(sub, studentId, weights),
                                ],
                              ],
                            ),
                          );
                        },
                      ),
                    ),
                  ],
                ),
    );
  }

  Widget _sortChip(String label, bool on, VoidCallback onTap) {
    return Material(
      color: on ? AppColors.primarySoft : Colors.transparent,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Text(label,
              style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w700,
                  color: on ? AppColors.primary : AppColors.inkMid)),
        ),
      ),
    );
  }

  /// One subject line inside an expanded student — tap again to reveal the
  /// real CA / Midterm / Final weighted breakdown for that subject.
  Widget _subjectRow(
      Map<String, dynamic> sub, dynamic studentId, Map<String, dynamic>? weights) {
    final key = '$studentId-${sub['subject_id']}';
    final open = _expandedSubject.contains(key);

    Widget scoreLine(String label, Map<String, dynamic>? part, num? weightPct) {
      final pct = part == null
          ? null
          : (part['score'] as num) / (part['max'] as num) * 100;
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 3),
        child: Row(
          children: [
            Expanded(
              child: Text(
                  weightPct != null ? '$label · ${weightPct.round()}%' : label,
                  style: const TextStyle(
                      fontSize: 12, color: AppColors.inkMid)),
            ),
            Text(
              part == null
                  ? 'not graded'
                  : '${part['score']}/${part['max']} (${pct!.toStringAsFixed(0)}%)',
              style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: part == null ? AppColors.inkLo : AppColors.ink),
            ),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Material(
        color: AppColors.bg,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => setState(
              () => open ? _expandedSubject.remove(key) : _expandedSubject.add(key)),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(sub['subject_name']?.toString() ?? '',
                          style: const TextStyle(
                              fontSize: 13, fontWeight: FontWeight.w600)),
                    ),
                    Text(
                      sub['total'] != null ? '${sub['total']}%' : 'incomplete',
                      style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                          color: sub['total'] != null
                              ? AppColors.ink
                              : AppColors.inkLo),
                    ),
                    if (sub['letter'] != null) ...[
                      const SizedBox(width: 8),
                      StatusPill(sub['letter'].toString(),
                          color: AppColors.primary),
                    ],
                    const SizedBox(width: 4),
                    Icon(
                        open
                            ? Icons.keyboard_arrow_up_rounded
                            : Icons.keyboard_arrow_down_rounded,
                        size: 18,
                        color: AppColors.inkLo),
                  ],
                ),
                if (open) ...[
                  const SizedBox(height: 6),
                  scoreLine('Continuous assessment', sub['ca'] as Map<String, dynamic>?,
                      weights?['ca'] as num?),
                  scoreLine('Midterm', sub['midterm'] as Map<String, dynamic>?,
                      weights?['midterm'] as num?),
                  scoreLine('Final', sub['final'] as Map<String, dynamic>?,
                      weights?['final'] as num?),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
