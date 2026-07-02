import 'package:flutter/material.dart';

import '../models/models.dart';
import '../services/data_service.dart';
import '../ui/glass.dart';

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
  final Set<int> _expanded = {};

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

  Color _avgTone(num? avg) {
    if (avg == null) return Palette.textLo;
    if (avg >= 80) return Palette.emerald;
    if (avg >= 60) return Palette.cyan;
    if (avg >= 50) return Palette.amber;
    return Palette.rose;
  }

  @override
  Widget build(BuildContext context) {
    final students =
        (_data?['students'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final term = _data?['term'] as Map<String, dynamic>?;

    return AppBackground(
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
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Grades · ${widget.schoolClass.name}',
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  fontSize: 17,
                                  fontWeight: FontWeight.w800,
                                  color: Palette.textHi)),
                          if (term != null)
                            Text(
                                '${term['name']} · ${term['academic_year']}',
                                style: const TextStyle(
                                    fontSize: 12, color: Palette.textLo)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: _loading
                    ? const Center(
                        child: CircularProgressIndicator(
                            color: Palette.violet, strokeWidth: 2.6))
                    : _data == null
                        ? const Center(
                            child: Padding(
                              padding: EdgeInsets.all(32),
                              child: Text(
                                'No grades available offline yet.\nOpen this screen once while online to cache it.',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                    color: Palette.textLo, fontSize: 13),
                              ),
                            ),
                          )
                        : ListView.builder(
                            padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
                            itemCount: students.length,
                            itemBuilder: (context, i) {
                              final s = students[i];
                              final subjects = (s['subjects'] as List?)
                                      ?.cast<Map<String, dynamic>>() ??
                                  [];
                              final avg = s['term_avg'] as num?;
                              final rank = s['rank'];
                              final open = _expanded.contains(i);
                              final tone = _avgTone(avg);
                              return GlassCard(
                                margin: const EdgeInsets.only(bottom: 8),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 12),
                                radius: 18,
                                glow: open ? Palette.violet : null,
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
                                            borderRadius:
                                                BorderRadius.circular(11),
                                            gradient: rank != null && rank <= 3
                                                ? Palette.accentGradient
                                                : null,
                                            color: rank != null && rank <= 3
                                                ? null
                                                : Colors.white
                                                    .withOpacity(0.06),
                                            border: Border.all(
                                                color: Colors.white
                                                    .withOpacity(0.1)),
                                          ),
                                          child: Text(
                                            rank?.toString() ?? '—',
                                            style: TextStyle(
                                                fontWeight: FontWeight.w800,
                                                fontSize: 13,
                                                color: rank != null && rank <= 3
                                                    ? Colors.white
                                                    : Palette.textMid),
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
                                                      color: Palette.textHi,
                                                      fontSize: 14)),
                                              Text(
                                                  s['student_uid']
                                                          ?.toString() ??
                                                      '',
                                                  style: const TextStyle(
                                                      fontSize: 10.5,
                                                      color: Palette.textLo)),
                                            ],
                                          ),
                                        ),
                                        Text(avg != null ? '$avg%' : '—',
                                            style: TextStyle(
                                                fontWeight: FontWeight.w800,
                                                fontSize: 15,
                                                color: tone)),
                                        const SizedBox(width: 6),
                                        Icon(
                                            open
                                                ? Icons
                                                    .keyboard_arrow_up_rounded
                                                : Icons
                                                    .keyboard_arrow_down_rounded,
                                            size: 20,
                                            color: Palette.textLo),
                                      ],
                                    ),
                                    if (open) ...[
                                      const SizedBox(height: 10),
                                      Container(
                                          height: 1,
                                          color:
                                              Colors.white.withOpacity(0.08)),
                                      const SizedBox(height: 6),
                                      for (final sub in subjects)
                                        Padding(
                                          padding: const EdgeInsets.symmetric(
                                              vertical: 5),
                                          child: Row(
                                            children: [
                                              Expanded(
                                                child: Text(
                                                    sub['subject_name']
                                                            ?.toString() ??
                                                        '',
                                                    style: const TextStyle(
                                                        fontSize: 13,
                                                        color:
                                                            Palette.textMid)),
                                              ),
                                              Text(
                                                sub['total'] != null
                                                    ? '${sub['total']}'
                                                    : 'incomplete',
                                                style: TextStyle(
                                                    fontWeight:
                                                        FontWeight.w700,
                                                    fontSize: 13,
                                                    color: sub['total'] != null
                                                        ? Palette.textHi
                                                        : Palette.textLo),
                                              ),
                                              if (sub['letter'] != null) ...[
                                                const SizedBox(width: 8),
                                                Container(
                                                  padding: const EdgeInsets
                                                      .symmetric(
                                                      horizontal: 8,
                                                      vertical: 2),
                                                  decoration: BoxDecoration(
                                                    color: Palette.violet
                                                        .withOpacity(0.18),
                                                    borderRadius:
                                                        BorderRadius.circular(
                                                            8),
                                                    border: Border.all(
                                                        color: Palette.violet
                                                            .withOpacity(
                                                                0.35)),
                                                  ),
                                                  child: Text(
                                                      sub['letter']
                                                          .toString(),
                                                      style: const TextStyle(
                                                          fontSize: 11,
                                                          fontWeight:
                                                              FontWeight.w800,
                                                          color: Palette
                                                              .textHi)),
                                                ),
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
              ),
            ],
          ),
        ),
      ),
    );
  }
}
