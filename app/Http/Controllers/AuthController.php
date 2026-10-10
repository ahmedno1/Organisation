<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TotpService;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    private const TWO_FACTOR_MAX_ATTEMPTS = 5;

    public function __construct(private readonly TotpService $totpService)
    {
    }

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();
        if ($user?->locked_until && $user->locked_until->isFuture()) {
            $seconds = now()->diffInSeconds($user->locked_until);
            throw ValidationException::withMessages([
                'email' => __('ui.auth_locked', ['seconds' => $seconds]),
            ]);
        }

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => __('ui.auth_too_many_attempts', ['seconds' => $seconds]),
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            if ($user) {
                $attempts = (int) $user->failed_login_attempts + 1;
                $user->failed_login_attempts = $attempts;
                if ($attempts >= 7) {
                    $user->locked_until = now()->addMinutes(15);
                    $user->failed_login_attempts = 0;
                }
                $user->save();
            }

            return back()->withErrors([
                'email' => __('ui.auth_invalid_credentials'),
            ])->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $authUser = $request->user();
        if ($authUser) {
            $authUser->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();
        }

        if ($authUser && $authUser->isDeveloper()) {
            if (! $authUser->hasTotpEnabled()) {
                ActivityLogger::log($request, 'auth.2fa.setup_required', null, ['user_id' => $authUser->id]);
                return redirect()
                    ->route('two_factor.setup')
                    ->with('status', __('ui.auth_setup_required'));
            }

            Auth::logout();
            $request->session()->put('two_factor_user_id', $authUser->id);
            $request->session()->put('two_factor_remember', $request->boolean('remember'));
            $request->session()->put('two_factor_attempts', 0);
            $request->session()->put('two_factor_signature', $this->twoFactorSignature($request));
            ActivityLogger::log($request, 'auth.2fa.challenge', null, ['user_id' => $authUser->id]);

            return redirect()
                ->route('two_factor.challenge')
                ->with('status', __('ui.auth_enter_2fa_code'));
        }

        ActivityLogger::log($request, 'auth.login');
        return redirect()->intended(route('dashboard'));
    }

    public function showTwoFactorChallenge(Request $request): View
    {
        abort_unless($request->session()->has('two_factor_user_id'), 404);

        if (! hash_equals((string) $request->session()->get('two_factor_signature', ''), $this->twoFactorSignature($request))) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_attempts', 'two_factor_signature']);
            abort(403);
        }

        return view('auth.two_factor');
    }

    public function verifyTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        if (! hash_equals((string) $request->session()->get('two_factor_signature', ''), $this->twoFactorSignature($request))) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_attempts', 'two_factor_signature']);
            return redirect()->route('login')->withErrors(['email' => __('ui.auth_session_mismatch')]);
        }

        $attempts = (int) $request->session()->get('two_factor_attempts', 0);
        if ($attempts >= self::TWO_FACTOR_MAX_ATTEMPTS) {
            $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_attempts', 'two_factor_signature']);
            return redirect()->route('login')->withErrors(['email' => __('ui.auth_too_many_verification_attempts')]);
        }

        $userId = (int) $request->session()->get('two_factor_user_id');
        $remember = (bool) $request->session()->get('two_factor_remember', false);
        $user = User::query()->findOrFail($userId);

        $inputCode = trim((string) $request->input('code'));
        $secret = $user->getTotpSecret();
        $totpValid = $secret ? $this->totpService->verify($secret, $inputCode) : false;
        $recoveryValid = ! $totpValid && $user->consumeRecoveryCode($inputCode);

        if (! $totpValid && ! $recoveryValid) {
            $attempts++;
            $request->session()->put('two_factor_attempts', $attempts);

            if ($attempts >= self::TWO_FACTOR_MAX_ATTEMPTS) {
                $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_attempts', 'two_factor_signature']);
                ActivityLogger::log($request, 'auth.2fa.locked', null, ['user_id' => $user->id]);
                return redirect()->route('login')->withErrors(['email' => __('ui.auth_too_many_verification_attempts')]);
            }

            return back()->withErrors(['code' => __('ui.auth_invalid_verification_code')]);
        }

        if ($recoveryValid) {
            $user->save();
            ActivityLogger::log($request, 'auth.2fa.recovery_code_used', null, ['user_id' => $user->id]);
        }

        Auth::login($user, $remember);
        $request->session()->forget(['two_factor_user_id', 'two_factor_remember', 'two_factor_attempts', 'two_factor_signature']);
        $request->session()->regenerate();
        ActivityLogger::log($request, 'auth.login.2fa_verified');

        return redirect()->route('dashboard');
    }

    public function showTwoFactorSetup(Request $request): View
    {
        $user = $request->user();
        abort_unless($user && $user->isDeveloper(), 403);

        $pendingSecret = (string) $request->session()->get('two_factor_setup_secret', '');
        if (! $user->hasTotpEnabled() && $pendingSecret === '') {
            $pendingSecret = $this->totpService->generateSecret();
            $request->session()->put('two_factor_setup_secret', $pendingSecret);
        }

        $setupSecret = $user->hasTotpEnabled() ? null : $pendingSecret;
        $otpauthUri = $setupSecret
            ? $this->totpService->provisioningUri($user->email, $setupSecret, (string) config('app.name', 'Institutions System'))
            : null;

        $newRecoveryCodes = $request->session()->pull('two_factor_new_recovery_codes', []);

        return view('auth.two_factor_setup', [
            'setupSecret' => $setupSecret,
            'otpauthUri' => $otpauthUri,
            'totpEnabled' => $user->hasTotpEnabled(),
            'newRecoveryCodes' => is_array($newRecoveryCodes) ? $newRecoveryCodes : [],
            'remainingRecoveryCodes' => count($user->getRecoveryCodes()),
        ]);
    }

    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->isDeveloper(), 403);

        if ($user->hasTotpEnabled()) {
            return redirect()->route('two_factor.setup')->with('status', __('ui.auth_2fa_already_enabled'));
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $secret = (string) $request->session()->get('two_factor_setup_secret', '');
        if ($secret === '') {
            return redirect()->route('two_factor.setup')->withErrors(['code' => __('ui.auth_setup_session_expired')]);
        }

        if (! $this->totpService->verify($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('ui.auth_invalid_authenticator_code')]);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->setTotpSecret($secret);
        $user->setRecoveryCodes($recoveryCodes);
        $user->save();

        $request->session()->forget('two_factor_setup_secret');
        $request->session()->put('two_factor_new_recovery_codes', $recoveryCodes);
        ActivityLogger::log($request, 'auth.2fa.enabled', null, ['user_id' => $user->id]);

        return redirect()->route('two_factor.setup')->with('status', __('ui.auth_2fa_enabled'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        ActivityLogger::log($request, 'auth.logout');

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function twoFactorSignature(Request $request): string
    {
        return hash('sha256', ($request->ip() ?? 'unknown').'|'.(string) $request->userAgent());
    }

    private function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
        }

        return $codes;
    }
}
