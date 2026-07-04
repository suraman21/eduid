<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Persists the welcome-tour UI flags for the current user. The onboarding is a
 * guided tour only — there is no checklist or task tracking, so the only state
 * is whether the tour has been seen / dismissed.
 */
class OnboardingController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'tour_seen' => 'sometimes|boolean',
            'dismissed' => 'sometimes|boolean',
        ]);

        $user  = $request->user();
        $state = $user->onboarding_state ?? [];

        if ($request->has('tour_seen')) {
            $state['tour_seen'] = $request->boolean('tour_seen');
        }
        if ($request->has('dismissed')) {
            $state['dismissed'] = $request->boolean('dismissed');
        }

        $user->forceFill(['onboarding_state' => $state])->save();

        // Redirect back so Inertia re-shares the fresh onboarding props.
        return back(303);
    }
}
