import 'package:flutter/material.dart';

import '../core/api_client.dart';
import '../services/parent_service.dart';
import '../ui/kit.dart';

/// One child: attendance (this-month summary + recent days) and published
/// grades (term → subject → assessments).
class ParentChildScreen extends StatefulWidget {
  const ParentChildScreen({
    super.key,
    required this.studentId,
    required this.studentName,
    this.className,
  });

  final int studentId;
  final String studentName;
  final String? className;

  @override
  State<ParentChildScreen> createState() => _ParentChildScreenState();
}

class _ParentChildScreenState extends State<ParentChildScreen> {
  Map<String, dynamic>? _attendance;
  Map<String, dynamic>? _grades;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final att = await ParentService.instance.attendance(widget.studentId);
      final gr = await ParentService.instance.grades(widget.studentId);
      if (!mounted) return;
      setState(() {
        _attendance = att;
        _grades = gr;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = ApiClient.messageFrom(e));
    }
  }

  Color _tone(String status) => switch (status) {
        'present' => AppColors.green,
        'absent' => AppColors.red,
        'late' => AppColors.amber,
        _ => AppColors.blue,
      };

  @override
  Widget build(BuildContext context) {
    final summary =
        (_attendance?['month_summary'] as Map?)?.cast<String, dynamic>();
    final records =
        (_attendance?['records'] as List?)?.cast<Map<String, dynamic>>() ?? [];
    final terms =
        (_grades?['terms'] as List?)?.cast<Map<String, dynamic>>() ?? [];

    num asNum(dynamic v) =>
        v is num ? v : (v is String ? (num.tryParse(v) ?? 0) : 0);
    final present = asNum(summary?['present']);
    final totalDays = summary == null
        ? 0
        : ['present', 'absent', 'late', 'excused']
            .fold<num>(0, (sum, k) => sum + asNum(summary[k]));
    final presentRate = totalDays > 0 ? present / totalDays * 100 : null;

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(widget.studentName,
                  style: const TextStyle(
                      fontSize: 16, fontWeight: FontWeight.w800)),
              if (widget.className != null)
                Text(widget.className!,
                    style: const TextStyle(
                        fontSize: 12, color: AppColors.inkLo)),
            ],
          ),
          bottom: const TabBar(
            indicatorColor: AppColors.primary,
            labelColor: AppColors.ink,
            unselectedLabelColor: AppColors.inkLo,
            dividerColor: Colors.transparent,
            tabs: [Tab(text: 'Attendance'), Tab(text: 'Grades')],
          ),
        ),
        body: _error != null && _attendance == null
            ? Center(
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(_error!,
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                              color: AppColors.inkMid, fontSize: 13)),
                      const SizedBox(height: 12),
                      OutlinedButton(
                          onPressed: _load, child: const Text('Try again')),
                    ],
                  ),
                ),
              )
            : _attendance == null
                ? const Center(
                    child: CircularProgressIndicator(
                        color: AppColors.primary, strokeWidth: 2.6))
                : TabBarView(
                    children: [
                      // ── Attendance ──
                      ListView(
                        padding: const EdgeInsets.all(20),
                        children: [
                          const SectionLabel('This month'),
                          Row(
                            children: [
                              for (final e in const [
                                ('present', 'Present'),
                                ('absent', 'Absent'),
                                ('late', 'Late'),
                                ('excused', 'Excused'),
                              ]) ...[
                                Expanded(
                                  child: StatTile(
                                    value: '${summary?[e.$1] ?? 0}',
                                    label: e.$2,
                                    color: _tone(e.$1),
                                  ),
                                ),
                                if (e.$1 != 'excused') const SizedBox(width: 8),
                              ],
                            ],
                          ),
                          if (presentRate != null) ...[
                            const SizedBox(height: 10),
                            AppCard(
                              color: presentRate >= 90
                                  ? AppColors.greenSoft
                                  : presentRate < 75
                                      ? AppColors.redSoft
                                      : AppColors.amberSoft,
                              borderColor: Colors.transparent,
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 14, vertical: 12),
                              child: Row(
                                children: [
                                  Icon(
                                    presentRate >= 90
                                        ? Icons.star_rounded
                                        : presentRate < 75
                                            ? Icons.error_outline_rounded
                                            : Icons.info_outline_rounded,
                                    size: 18,
                                    color: presentRate >= 90
                                        ? AppColors.green
                                        : presentRate < 75
                                            ? AppColors.red
                                            : AppColors.amber,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      presentRate >= 90
                                          ? '${widget.studentName.split(' ').first} was present ${presentRate.toStringAsFixed(0)}% of days this month — great attendance.'
                                          : presentRate < 75
                                              ? '${widget.studentName.split(' ').first} was present ${presentRate.toStringAsFixed(0)}% of days this month — attendance has dipped.'
                                              : '${widget.studentName.split(' ').first} was present ${presentRate.toStringAsFixed(0)}% of days this month.',
                                      style: const TextStyle(
                                          fontSize: 12.5,
                                          color: AppColors.inkMid,
                                          fontWeight: FontWeight.w600),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          const SizedBox(height: 20),
                          const SectionLabel('Recent days'),
                          if (records.isEmpty)
                            const AppCard(
                              padding: EdgeInsets.all(24),
                              child: Text('No attendance recorded yet.',
                                  textAlign: TextAlign.center,
                                  style: TextStyle(
                                      color: AppColors.inkLo, fontSize: 13)),
                            ),
                          for (final r in records)
                            AppCard(
                              margin: const EdgeInsets.only(bottom: 8),
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 14, vertical: 11),
                              radius: 14,
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(r['date']?.toString() ?? '',
                                            style: const TextStyle(
                                                fontWeight: FontWeight.w600,
                                                color: AppColors.ink,
                                                fontSize: 13.5)),
                                        Text(
                                            [
                                              r['type'],
                                              if (r['note'] != null) r['note'],
                                            ].join(' · '),
                                            style: const TextStyle(
                                                fontSize: 11,
                                                color: AppColors.inkLo)),
                                      ],
                                    ),
                                  ),
                                  StatusPill(
                                    (r['status']?.toString() ?? '')
                                        .toUpperCase(),
                                    color:
                                        _tone(r['status']?.toString() ?? ''),
                                  ),
                                ],
                              ),
                            ),
                        ],
                      ),

                      // ── Grades ──
                      ListView(
                        padding: const EdgeInsets.all(20),
                        children: [
                          if (terms.isEmpty)
                            const AppCard(
                              padding: EdgeInsets.all(24),
                              child: Text(
                                'No published grades yet.\nGrades appear here after the school publishes them.',
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                    color: AppColors.inkLo, fontSize: 13),
                              ),
                            ),
                          for (final term in terms) ...[
                            SectionLabel(
                                '${term['term']} · ${term['academic_year'] ?? ''}'),
                            for (final sub in (term['subjects'] as List)
                                .cast<Map<String, dynamic>>())
                              AppCard(
                                margin: const EdgeInsets.only(bottom: 8),
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 12),
                                radius: 14,
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.start,
                                  children: [
                                    Row(
                                      children: [
                                        Expanded(
                                          child: Text(
                                              sub['subject']?.toString() ?? '',
                                              style: const TextStyle(
                                                  fontWeight: FontWeight.w700,
                                                  color: AppColors.ink,
                                                  fontSize: 14)),
                                        ),
                                        if (sub['total_pct'] != null)
                                          Text('${sub['total_pct']}%',
                                              style: const TextStyle(
                                                  fontWeight: FontWeight.w800,
                                                  color: AppColors.primary,
                                                  fontSize: 15)),
                                      ],
                                    ),
                                    const SizedBox(height: 6),
                                    Wrap(
                                      spacing: 6,
                                      runSpacing: 6,
                                      children: [
                                        for (final a in (sub['assessments']
                                                as List)
                                            .cast<Map<String, dynamic>>())
                                          Container(
                                            padding:
                                                const EdgeInsets.symmetric(
                                                    horizontal: 8,
                                                    vertical: 3),
                                            decoration: BoxDecoration(
                                              color: AppColors.bg,
                                              borderRadius:
                                                  BorderRadius.circular(8),
                                            ),
                                            child: Text(
                                              '${a['type']}: ${a['score']}/${a['max_score']}',
                                              style: const TextStyle(
                                                  fontSize: 11,
                                                  color: AppColors.inkMid,
                                                  fontWeight:
                                                      FontWeight.w600),
                                            ),
                                          ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                            const SizedBox(height: 8),
                          ],
                        ],
                      ),
                    ],
                  ),
      ),
    );
  }
}
