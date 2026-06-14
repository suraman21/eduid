<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LeadController extends Controller
{
    /**
     * Handle contact form submission from the public marketing page.
     * Rate-limited to 3 submissions per IP per hour.
     */
    public function store(Request $request)
    {
        // Rate limit: 3 submissions per IP per hour
        $key = 'lead-submit:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $minutes = ceil($seconds / 60);

            return back()
                ->withInput()
                ->with('error', "Too many submissions. Please try again in {$minutes} minutes.");
        }

        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'school_name'    => ['required', 'string', 'max:200'],
            'phone'          => ['required', 'string', 'max:20'],
            'email'          => ['nullable', 'email', 'max:150'],
            'message'        => ['nullable', 'string', 'max:2000'],
            'preferred_plan' => ['nullable', 'in:starter,standard,premium'],
        ]);

        Lead::create($validated);

        RateLimiter::hit($key, 3600); // 1 hour window

        return back()->with('success', 'Thank you! We\'ll contact you within 24 hours.');
    }
}
