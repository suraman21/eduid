<?php

namespace App\Services;

use App\Models\AssessmentConfig;
use App\Models\GradingScale;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\Subject;
use App\Models\Term;

class GradeCalculator
{
    /**
     * Get the weighted total for a student's subject in a term.
     * Returns null if any assessment type grade is missing.
     */
    public function getSubjectTotal(Student $student, Subject $subject, Term $term, School $school): ?float
    {
        // Determine the student's grade level for config lookup
        $gradeLevel = optional($student->schoolClass)->grade_level ?? null;

        // Find assessment config: grade-level specific first, then school-wide default
        $config = AssessmentConfig::where('school_id', $school->id)
            ->where('academic_year', $term->academic_year)
            ->where('grade_level', $gradeLevel)
            ->first();

        if (! $config) {
            $config = AssessmentConfig::where('school_id', $school->id)
                ->where('academic_year', $term->academic_year)
                ->whereNull('grade_level')
                ->first();
        }

        if (! $config) {
            return null;
        }

        $grades = StudentGrade::where('school_id', $school->id)
            ->where('student_id', $student->id)
            ->where('subject_id', $subject->id)
            ->where('term_id', $term->id)
            ->where('status', 'published')
            ->get()
            ->keyBy('assessment_type');

        if (! isset($grades['ca'], $grades['midterm'], $grades['final'])) {
            return null;
        }

        $caScore = (float) $grades['ca']->score / (float) $grades['ca']->max_score * 100;
        $midScore = (float) $grades['midterm']->score / (float) $grades['midterm']->max_score * 100;
        $finalScore = (float) $grades['final']->score / (float) $grades['final']->max_score * 100;

        $total = ($caScore * (float) $config->ca_weight / 100)
            + ($midScore * (float) $config->midterm_weight / 100)
            + ($finalScore * (float) $config->final_weight / 100);

        return round($total, 2);
    }

    /**
     * Get the average of all subject totals for a student in a term.
     * Returns null if any subject total is null.
     */
    public function getTermAverage(Student $student, Term $term, School $school): ?float
    {
        $classSubjects = \App\Models\ClassSubject::where('class_id', $student->class_id)
            ->where('school_id', $school->id)
            ->with('subject')
            ->get();

        if ($classSubjects->isEmpty()) {
            return null;
        }

        $totals = [];
        foreach ($classSubjects as $cs) {
            $total = $this->getSubjectTotal($student, $cs->subject, $term, $school);
            if ($total === null) {
                return null;
            }
            $totals[] = $total;
        }

        return round(array_sum($totals) / count($totals), 2);
    }

    /**
     * Get the weighted yearly average across all terms.
     * Returns null if any term is not yet fully published or weight is missing.
     * Per business rule: never return a partial yearly number.
     */
    public function getYearlyAverage(Student $student, string $academicYear, School $school): ?float
    {
        $terms = Term::where('school_id', $school->id)
            ->where('academic_year', $academicYear)
            ->get();

        if ($terms->isEmpty()) {
            return null;
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($terms as $term) {
            if ($term->weight === null) {
                return null;
            }

            $termAvg = $this->getTermAverage($student, $term, $school);
            if ($termAvg === null) {
                return null;
            }

            $weight = (float) $term->weight;
            $weightedSum += $termAvg * ($weight / 100);
            $totalWeight += $weight;
        }

        if (abs($totalWeight - 100.0) > 0.01) {
            return null; // Weights don't sum to 100
        }

        return round($weightedSum, 2);
    }

    /**
     * Get a student's rank in their class for a given term.
     * Returns ['rank' => int, 'out_of' => int] or null.
     * Ties share rank; next rank skips appropriately.
     */
    public function getClassRank(Student $student, Term $term, School $school): ?array
    {
        $classmates = Student::where('school_id', $school->id)
            ->where('class_id', $student->class_id)
            ->where('status', 'active')
            ->get();

        if ($classmates->isEmpty()) {
            return null;
        }

        $averages = [];
        foreach ($classmates as $classmate) {
            $avg = $this->getTermAverage($classmate, $term, $school);
            if ($avg !== null) {
                $averages[$classmate->id] = $avg;
            }
        }

        if (! isset($averages[$student->id])) {
            return null;
        }

        arsort($averages); // descending
        $outOf = count($averages);

        $rank = 1;
        $prevScore = null;
        $prevRank = 1;
        $position = 0;
        $studentRank = null;

        foreach ($averages as $sid => $score) {
            $position++;
            if ($prevScore !== null && abs($score - $prevScore) > 0.001) {
                $rank = $position;
            }
            if ($sid === $student->id) {
                $studentRank = $rank;
                break;
            }
            $prevScore = $score;
            $prevRank = $rank;
        }

        return $studentRank !== null ? ['rank' => $studentRank, 'out_of' => $outOf] : null;
    }

    /**
     * Get the letter grade label for a score using the school's grading scales.
     */
    public function getLetterGrade(float $score, School $school): string
    {
        $scale = GradingScale::where('school_id', $school->id)
            ->where('min_score', '<=', $score)
            ->where('max_score', '>=', $score)
            ->orderBy('sort_order')
            ->first();

        return $scale ? $scale->label : 'N/A';
    }
}
