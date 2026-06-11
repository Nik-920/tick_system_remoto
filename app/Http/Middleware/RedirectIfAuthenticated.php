<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * @param  Closure(Request): Response  $next
     * @param  string  ...$guards
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                if ($user instanceof User
                    && method_exists($user, 'hasRole')
                    && $user->hasRole('reporter')
                ) {
                    return redirect()->route('reporter.dashboard');
                }

                return redirect()->route('dashboard.index');
            }
        }

        return $next($request);
    }
}
