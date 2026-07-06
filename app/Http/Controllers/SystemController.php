<?php

namespace App\Http\Controllers;

use App\Models\CardOrder;
use App\Support\SchemaHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * System / public entry points. These were closure routes; moving them into a
 * controller lets `php artisan route:cache` run (closures can't be cached),
 * which removes route-file parsing from every request — a real win on shared
 * hosting where the app otherwise re-registers all routes per request.
 */
class SystemController extends Controller
{
    /** Marketing home — authenticated visitors go straight to their panel. */
    public function home()
    {
        if (Auth::guard('web')->check()) {
            return redirect('/school/dashboard');
        }
        if (Auth::guard('admin')->check()) {
            return redirect('/admin/dashboard');
        }

        return Inertia::render('Welcome', [
            'success' => session('success'),
            'error'   => session('error'),
            'plans'   => \App\Http\Controllers\PublicController::plans(),
            'cardAnnualPrice' => CardOrder::PRICING['annual_renewal'],
        ]);
    }

    /** /admin entry — bounce to dashboard or the admin login. */
    public function adminEntry()
    {
        if (Auth::guard('admin')->check()) {
            return redirect('/admin/dashboard');
        }

        return redirect()->route('admin.login');
    }

    /**
     * Client-side error sink: the React error boundary posts unexpected UI
     * crashes here so the details land in the SERVER log (never shown to the
     * user or the browser console).
     */
    public function clientError(Request $request)
    {
        $data = $request->validate([
            'ref'     => 'required|string|max:40',
            'message' => 'nullable|string|max:1000',
            'stack'   => 'nullable|string|max:5000',
            'url'     => 'nullable|string|max:500',
        ]);

        Log::error("[{$data['ref']}] Client UI error: ".($data['message'] ?? 'unknown'), [
            'stack'   => $data['stack'] ?? null,
            'url'     => $data['url'] ?? $request->headers->get('referer'),
            'user_id' => auth()->id(),
            'ip'      => $request->ip(),
            'agent'   => $request->userAgent(),
        ]);

        return response()->noContent();
    }

    /** Health check for uptime monitors (was an API closure route). */
    public function health()
    {
        try {
            DB::connection()->getPdo();
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        $storageStatus = is_writable(storage_path('app')) ? 'ok' : 'error';
        $pending = SchemaHealth::pendingMigrations();

        return response()->json([
            'status'  => ($dbStatus === 'ok' && $storageStatus === 'ok' && $pending === 0) ? 'ok' : 'degraded',
            'db'      => $dbStatus,
            'storage' => $storageStatus,
            'pending_migrations' => $pending,
            'version' => '1.0.0',
            'time'    => now()->toIso8601String(),
        ]);
    }
}
