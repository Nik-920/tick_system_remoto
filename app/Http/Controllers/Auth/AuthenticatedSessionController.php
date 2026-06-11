<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();

            if ($user instanceof User && $this->hasRole($user, 'reporter')) {
                return redirect()->route('reporter.dashboard');
            }

            return redirect()->route('dashboard.index');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);

        if (! Auth::attempt($credentials, $remember)) {
            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => 'Las credenciales proporcionadas no son validas.',
                ]);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        if ($user instanceof User && $this->hasRole($user, 'reporter')) {
            return redirect()->route('reporter.dashboard');
        }

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function hasRole(User $user, string $role): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole($role);
    }
}
