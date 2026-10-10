@extends('layouts.app')

@section('title', 'Two-Factor Verification')

@section('content')
    <div class="card narrow">
        <h3>Two-Factor Verification</h3>
        <p class="muted">Enter the 6-digit code from your authenticator app. You can also use a recovery code.</p>
        <form method="POST" action="{{ route('two_factor.verify') }}" class="form-grid">
            @csrf
            <label>
                Verification code
                <input type="text" name="code" inputmode="text" maxlength="32" required>
            </label>
            <button class="btn primary" type="submit">Verify</button>
        </form>
    </div>
@endsection
