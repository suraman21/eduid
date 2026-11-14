<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give each school a default grading scale if it has none.
 *
 * Transcripts need a scale to turn a weighted total into a letter and a GPA
 * point. Without one every column would read "—". The bands below are the
 * widely used Ethiopian convention (A 90+, B 80+, C 70+, D 60+, F below 60).
 *
 * This only ever inserts when a school has NO scale at all, so a school that
 * has configured its own marking is never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('grading_scales') || !Schema::hasTable('schools')) {
            return;
        }

        $defaults = [
            ['A',  90.00, 100.00, 4.00, 'Excellent',   1],
            ['B',  80.00,  89.99, 3.00, 'Very good',   2],
            ['C',  70.00,  79.99, 2.00, 'Good',        3],
            ['D',  60.00,  69.99, 1.00, 'Satisfactory',4],
            ['F',   0.00,  59.99, 0.00, 'Fail',        5],
        ];

        $now = now();

        foreach (DB::table('schools')->pluck('id') as $schoolId) {
            $has = DB::table('grading_scales')->where('school_id', $schoolId)->exists();

            if ($has) {
                continue;
            }

            foreach ($defaults as [$label, $min, $max, $point, $desc, $order]) {
                DB::table('grading_scales')->insert([
                    'school_id'   => $schoolId,
                    'label'       => $label,
                    'min_score'   => $min,
                    'max_score'   => $max,
                    'gpa_point'   => $point,
                    'description' => $desc,
                    'sort_order'  => $order,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Removing marking bands someone may have relied on would be
        // destructive; leave them in place if this migration is rolled back.
    }
};
