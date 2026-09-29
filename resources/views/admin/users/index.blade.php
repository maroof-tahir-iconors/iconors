@extends('layouts.admin')

@section('title', 'Users')
@section('subtitle', 'Accounts, roles and access.')

@section('content')

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                    Total users
                </span>
                <span class="stat-card__value">{{ number_format($counts['total']) }}</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
                    Verified
                </span>
                <span class="stat-card__value">{{ number_format($counts['verified']) }}</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-slash-circle"></i></span>
                    Suspended
                </span>
                <span class="stat-card__value">{{ number_format($counts['suspended']) }}</span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="ico-card stat-card">
                <span class="stat-card__label">
                    <span class="stat-card__icon" aria-hidden="true"><i class="bi bi-person-plus"></i></span>
                    New this week
                </span>
                <span class="stat-card__value">{{ number_format($counts['newThisWeek']) }}</span>
            </div>
        </div>
    </div>

    <div class="ico-card p-3 mb-4">
        <form method="GET" action="{{ route('admin.users.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label for="user-search-q" class="form-label small fw-semibold">Search</label>
                <input type="text" id="user-search-q" name="q" class="form-control"
                       placeholder="Search name or email" value="{{ request('q') }}">
            </div>

            <div class="col-6 col-md-3">
                <label for="user-search-role" class="form-label small fw-semibold">Role</label>
                <select id="user-search-role" name="role" class="form-select">
                    <option value="">All roles</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role }}" @selected(request('role') === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-6 col-md-2">
                <label for="user-search-status" class="form-label small fw-semibold">Status</label>
                <select id="user-search-status" name="status" class="form-select">
                    <option value="">Any status</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    <option value="unverified" @selected(request('status') === 'unverified')>Unverified</option>
                </select>
            </div>

            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-ico-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    @if ($users->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h2 class="h6">No users found</h2>
            <p class="text-muted small mb-0">Try a different search term or clear the filters.</p>
        </div>
    @else
        <div class="ico-card p-0">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Roles</th>
                            <th scope="col">Verified</th>
                            <th scope="col">Joined</th>
                            <th scope="col">Manage</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.users.show', $user) }}" class="fw-semibold">
                                        {{ $user->name }}
                                    </a>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @forelse ($user->roles as $role)
                                            <span class="badge {{ $role->name === 'Suspended User' ? 'text-bg-danger' : 'text-bg-secondary' }}">
                                                {{ $role->name }}
                                            </span>
                                        @empty
                                            <span class="text-muted small">&mdash;</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    @if ($user->email_verified_at)
                                        <i class="bi bi-patch-check-fill text-success" aria-hidden="true"></i>
                                        <span class="visually-hidden">Verified</span>
                                    @else
                                        <i class="bi bi-dash-circle text-muted" aria-hidden="true"></i>
                                        <span class="visually-hidden">Not verified</span>
                                    @endif
                                </td>
                                <td>{{ $user->created_at->diffForHumans() }}</td>
                                <td>
                                    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-ico-outline btn-sm">
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
@endsection
