@extends('layouts.app')

@section('title', 'Two-Factor Setup')

@section('content')
    <div class="card narrow">
        <h3>Two-Factor Authentication Setup</h3>

        @if(session('status'))
            <p class="muted">{{ session('status') }}</p>
        @endif

        @if($totpEnabled)
            <p class="muted">Two-factor authentication is enabled.</p>
            <p class="muted">Remaining recovery codes: {{ $remainingRecoveryCodes }}</p>
        @else
            <p class="muted">Use Google Authenticator or Microsoft Authenticator and add this secret:</p>
            <p><code>{{ $setupSecret }}</code></p>
            <p class="muted">You can also paste this URI in any TOTP app that supports manual URI import:</p>
            <p><code style="word-break: break-all;">{{ $otpauthUri }}</code></p>

            <form method="POST" action="{{ route('two_factor.setup.store') }}" class="form-grid">
                @csrf
                <label>
                    Authenticator code
                    <input type="text" name="code" inputmode="numeric" maxlength="6" required>
                </label>
                <button class="btn primary" type="submit">Enable 2FA</button>
            </form>
        @endif

        @if(count($newRecoveryCodes) > 0)
            <h4>Recovery Codes</h4>
            <p class="muted">Save these now. Each code can be used only once.</p>
            <ul>
                @foreach($newRecoveryCodes as $recoveryCode)
                    <li><code>{{ $recoveryCode }}</code></li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
