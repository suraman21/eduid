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
  final Set<int> _expanded = {};

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
    final students =
        (_data?['students'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final term = _data?['term'] as Map<String, dynamic>?;

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
              : ListView.builder(
                  padding: const EdgeInsets.all(20),
                  itemCount: students.length,
                  itemBuilder: (context, i) {
                    final s = students[i];
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
                      radius: 12,
                      onTap: () => setState(() =>
                          open ? _expanded.remove(i) : _expanded.add(i)),
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
                                  borderRadius: BorderRadius.circular(10),
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
                                    Text(s['full_name']?.toString() ?? '',
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.ink,
                                            fontSize: 14)),
                                    Text(
                                        s['student_uid']?.toString() ?? '',
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
                                      ? Icons.keyboard_arrow_up_rounded
                                      : Icons.keyboard_arrow_down_rounded,
                                  size: 20,
                                  color: AppColors.inkLo),
                            ],
                          ),
                          if (open) ...[
                            const SizedBox(height: 10),
                            const Divider(height: 1),
                            const SizedBox(height: 6),
                            for (final sub in subjects)
                              Padding(
                                padding:
                                    const EdgeInsets.symmetric(vertical: 5),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                          sub['subject_name']?.toString() ??
                                              '',
                                          style: const TextStyle(
                                              fontSize: 13,
                                              color: AppColors.inkMid)),
                                    ),
                                    Text(
                                      sub['total'] != null
                                          ? '${sub['total']}'
                                          : 'incomplete',
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
                                  ],
                                ),
                              ),
                          ],
                        ],
                      ),
                    );
                  },
                ),
    );
  }
}
