@extends('layouts.app')

@section('title', 'Account Details — My Account')

@section('content')
@include('account.partials.shell')

<div class="container-ico py-4">

    <div class="row g-3">

        <div class="col-lg-7">
            <div class="ico-card p-4">
                <h2 class="h6 mb-3">Profile</h2>

                <form method="POST" action="{{ route('account.details.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input id="name" name="name" type="text" required maxlength="255"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $user->name) }}" autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" required maxlength="255"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $user->email) }}" autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror

                        @if (! $user->email_verified_at)
                            <div class="form-text text-warning-emphasis">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                This address is not verified.
                            </div>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-ico-primary btn-sm">Save changes</button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="ico-card p-4 mb-3">
                <h2 class="h6 mb-3">Change password</h2>

                <form method="POST" action="{{ route('account.password.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current password</label>
                        <input id="current_password" name="current_password" type="password" required
                               class="form-control @error('current_password') is-invalid @enderror"
                               autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">New password</label>
                        <input id="password" name="password" type="password" required minlength="8"
                               class="form-control @error('password') is-invalid @enderror"
                               autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">At least 8 characters.</div>
                    </div>

                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Confirm new password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               class="form-control" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-ico-outline btn-sm">Update password</button>
                </form>
            </div>

            <div class="ico-card p-4">
                <h2 class="h6 mb-3">Account</h2>
                <dl class="account-meta mb-0">
                    <dt>Member since</dt>
                    <dd>{{ $user->created_at->format('j F Y') }}</dd>

                    <dt>Role</dt>
                    <dd>{{ $user->getRoleNames()->first() ?? 'Customer' }}</dd>

                    <dt>Plan</dt>
                    <dd>{{ $user->currentPlan()?->name ?? 'Free' }}</dd>
                </dl>
            </div>
        </div>

    </div>

</div>
@endsection
