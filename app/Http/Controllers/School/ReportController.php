<?php

namespace App\Http\Controllers\School;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;

/**
 * Reports — enrolment, retention, results and students at risk.
 *
 * Read-only over existing records; nothing here writes. Two views of the same
 * data: an on-screen report a head teacher can read, and a CSV a bursar can
 * paste into a meeting.
 */
class ReportController extends BaseSchoolController
{
    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request)
    {
        $this->requireAdmin();

        $schoolId = $this->schoolId();
        $year     = $request->query('year');

        return Inertia::render('School/Reports/Index', [
            'enrolment' => $this->reports->enrolment($schoolId),
            'retention' => $this->reports->retention($schoolId),
            'academic'  => $this->reports->academic($schoolId, $year),
            'risk'      => $this->reports->atRisk($schoolId),
            'filters'   => ['year' => $year],
            'currency_year' => $this->currentSchool()->academic_year,
            'is_admin'  => auth()->user()?->isSchoolAdmin() ?? false,
        ]);
    }

    /** CSV export of one report — what actually gets put in front of a board. */
    public function export(Request $request, string $report)
    {
        $this->requireAdmin();

        $schoolId = $this->schoolId();
        $rows     = [];
        $filename = "report-{$report}.csv";

        switch ($report) {
            case 'enrolment':
                $data = $this->reports->enrolment($schoolId);
                $rows[] = ['Academic year', 'Students', 'Entries', 'Exits', 'Change', 'Change %'];
                foreach ($data['years'] as $y) {
                    $rows[] = [
                        $y['academic_year'], $y['students'], $y['entries'],
                        $y['exits'], $y['change'] ?? '', $y['change_pct'] ?? '',
                    ];
                }
                $rows[] = [];
                $rows[] = ['Class', 'Academic year', 'Students', 'Capacity', 'Fill %'];
                foreach ($data['by_class'] as $c) {
                    $rows[] = [$c['label'], $c['academic_year'], $c['students'], $c['capacity'] ?? '', $c['fill_pct'] ?? ''];
                }
                break;

            case 'retention':
                $data = $this->reports->retention($schoolId);
                $rows[] = ['Cohort year', 'Joined', 'Still enrolled', 'Graduated', 'Transferred', 'Withdrawn', 'Other', 'Retention %'];
                foreach ($data['cohorts'] as $c) {
                    $rows[] = [
                        $c['academic_year'], $c['joined'], $c['still_enrolled'], $c['graduated'],
                        $c['transferred'], $c['withdrawn'], $c['other'], $c['retention_pct'],
                    ];
                }
                break;

            case 'results':
                $data = $this->reports->academic($schoolId, $request->query('year'));
                $rows[] = ['Subject', 'Academic year', 'Marked', 'Average', 'Highest', 'Lowest', 'Pass rate %'];
                foreach ($data['subjects'] as $s) {
                    $rows[] = [
                        $s['subject'], $s['academic_year'], $s['marked'], $s['average'],
                        $s['highest'], $s['lowest'], $s['pass_rate'],
                    ];
                }
                break;

            case 'at-risk':
                $data = $this->reports->atRisk($schoolId);
                $rows[] = ['Student', 'Student ID', 'Class', 'Attendance %', 'Absences', 'Average', 'Failing subjects', 'Reason'];
                foreach ($data['students'] as $s) {
                    $rows[] = [
                        $s['name'], $s['uid'], $s['class'] ?? '', $s['attendance'] ?? '',
                        $s['absences'], $s['average'] ?? '', $s['failing'], implode('; ', $s['reasons']),
                    ];
                }
                break;

            default:
                abort(404);
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function ($cell) {
                $cell = (string) $cell;
                return str_contains($cell, ',') || str_contains($cell, '"')
                    ? '"' . str_replace('"', '""', $cell) . '"'
                    : $cell;
            }, $row)) . "\n";
        }

        return Response::make($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
