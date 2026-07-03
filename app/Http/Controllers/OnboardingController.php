<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Persists the onboarding UI flags for the current user. Task completion
 * itself is never stored here — it is derived live from real data in
 * HandleInertiaRequests — so the checklist is always truthful.
 */
class OnboardingController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'tour_seen' => 'sometimes|boolean',
            'dismissed' => 'sometimes|boolean',
            'finished'  => 'sometimes|boolean',
            'reset'     => 'sometimes|boolean',
        ]);

        $user  = $request->user();
        $state = $user->onboarding_state ?? [];

        if ($request->boolean('reset')) {
            // "Restart the tour" — clears every flag so it plays fresh.
            $state = ['tour_seen' => false, 'dismissed' => false, 'finished_at' => null];
        } else {
            if ($request->has('tour_seen')) {
                $state['tour_seen'] = $request->boolean('tour_seen');
            }
            if ($request->has('dismissed')) {
                $state['dismissed'] = $request->boolean('dismissed');
            }
            // finished_at is written once, on first 100% — so we never
            // re-fire the celebration if data later dips below complete.
            if ($request->boolean('finished') && empty($state['finished_at'])) {
                $state['finished_at'] = now()->toIso8601String();
            }
        }

        $user->forceFill(['onboarding_state' => $state])->save();

        // Redirect back so Inertia re-shares the fresh onboarding props.
        return back(303);
    }
}
