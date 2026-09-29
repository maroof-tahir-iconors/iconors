@extends('layouts.app')

@section('title', 'Create your account — Iconors')

@section('content')
<div class="container-ico px-3 py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5 col-xl-4">
            <div class="ico-card p-4 p-md-5">
                <h1 class="h4 mb-1">Create your account</h1>
                <p class="text-muted small mb-4">Free to join. Download free assets straight away.</p>

                <form method="POST" action="{{ route('register') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label small fw-semibold">Name</label>
                        <input id="name" name="name" type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name') }}" required autocomplete="name" autofocus>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label small fw-semibold">Email</label>
                        <input id="email" name="email" type="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email') }}" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label small fw-semibold">Password</label>
                        <input id="password" name="password" type="password"
                               class="form-control @error('password') is-invalid @enderror"
                               required autocomplete="new-password"
                               aria-describedby="password-help">
                        <div id="password-help" class="form-text small">
                            At least 8 characters, with letters and numbers.
                        </div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label for="password_confirmation" class="form-label small fw-semibold">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password"
                               class="form-control" required autocomplete="new-password">
                    </div>

                    <button class="btn btn-ico-primary w-100" type="submit">Create account</button>
                </form>

                <p class="text-center small text-muted mt-4 mb-0">
                    Already have an account? <a href="{{ route('login') }}">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
