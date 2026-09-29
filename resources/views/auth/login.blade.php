@extends('layouts.app')

@section('title', 'Log in — Iconors')

@section('content')
<div class="container-ico px-3 py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="ico-card p-4 p-md-5">
                <h1 class="h4 mb-1">Welcome back</h1>
                <p class="text-muted small mb-4">Log in to your Iconors account.</p>

                {{-- Validation summary. Screen readers announce it via
                     role="alert"; sighted users get it above the fields
                     rather than only inline. --}}
                @if ($errors->any())
                    <div class="alert alert-danger small py-2" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email</label>
                        <input id="email" name="email" type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}"
                               required autocomplete="email" autofocus
                               @error('email') aria-describedby="email-error" @enderror>
                        @error('email')
                            <div id="email-error" class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold">Password</label>
                        <input id="password" name="password" type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               required autocomplete="current-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label small" for="remember">Remember me</label>
                    </div>

                    <button class="btn btn-ico-primary w-100" type="submit">Log in</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    New to Iconors? <a href="{{ route('register') }}">Create an account</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
