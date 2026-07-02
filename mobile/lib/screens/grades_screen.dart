import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';

/// Read-only gradebook for one class (GET /classes/{id}/grades) — weighted
/// totals, letters and ranks come pre-computed from the server. Fetched live
/// when online; falls back to the last cached copy offline.
class GradesScreen extends StatefulWidget {
  const GradesScreen({super.key, required this.schoolClass});

  final SchoolClass schoolClass;

  @override
  State<GradesScreen> createState() => _GradesScreenState();
}

class _GradesScreenState extends State<GradesScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final data = await DataService.instance.grades(widget.schoolClass.id);
    if (!mounted) return;
    setState(() {
      _data = data;
      _loading = false;
    });
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
            Text('Grades — ${widget.schoolClass.name}',
                style:
                    const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            if (term != null)
              Text('${term['name']} · ${term['academic_year']}',
                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600)),
          ],
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _data == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(32),
                    child: Text(
                      'No grades available offline yet.\nOpen this screen once while online to cache it.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.grey.shade600),
                    ),
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: students.length,
                  itemBuilder: (context, i) {
                    final s = students[i];
                    final subjects =
                        (s['subjects'] as List?)?.cast<Map<String, dynamic>>() ??
                            [];
                    final avg = s['term_avg'];
                    final rank = s['rank'];
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: ExpansionTile(
                        shape: const Border(),
                        title: Text(s['full_name']?.toString() ?? '',
                            style:
                                const TextStyle(fontWeight: FontWeight.w700)),
                        subtitle: Text(s['student_uid']?.toString() ?? '',
                            style: TextStyle(
                                fontSize: 11, color: Colors.grey.shade500)),
                        trailing: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Text(avg != null ? '$avg%' : '—',
                                style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    color: Color(0xFF4F46E5))),
                            if (rank != null)
                              Text('Rank $rank',
                                  style: TextStyle(
                                      fontSize: 11,
                                      color: Colors.grey.shade500)),
                          ],
                        ),
                        children: [
                          for (final sub in subjects)
                            ListTile(
                              dense: true,
                              title: Text(sub['subject_name']?.toString() ?? ''),
                              trailing: Text(
                                sub['total'] != null
                                    ? '${sub['total']}  ${sub['letter'] ?? ''}'
                                    : 'incomplete',
                                style: const TextStyle(
                                    fontWeight: FontWeight.w700),
                              ),
                            ),
                        ],
                      ),
                    );
                  },
                ),
    );
  }
}
