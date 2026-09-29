<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the contributor area.
 *
 * Checks the contributor's own status rather than merely the role: a
 * suspended contributor keeps the role assignment but must lose access
 * immediately, without an admin having to remember to also strip roles.
 */
class EnsureContributorApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $contributor = $request->user()?->contributor;

        if (! $contributor) {
            return redirect()
                ->route('contributor.apply')
                ->with('status', 'Apply to become a contributor to access this area.');
        }

        if ($contributor->status !== 'approved') {
            return redirect()
                ->route('contributor.apply.status')
                ->with('status', match ($contributor->status) {
                    'suspended' => 'Your contributor account is currently suspended.',
                    'rejected'  => 'Your contributor application was not approved.',
                    default     => 'Your contributor application is still under review.',
                });
        }

        return $next($request);
    }
}
