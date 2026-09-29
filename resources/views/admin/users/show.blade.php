@extends('layouts.admin')

@section('title', $user->name)
@section('subtitle', $user->email)

@php
    $contributorStatusColors = [
        'pending'            => 'text-bg-secondary',
        'under_review'       => 'text-bg-info',
        'more_info_required' => 'text-bg-warning',
        'approved'           => 'text-bg-success',
        'rejected'           => 'text-bg-danger',
    ];
@endphp

@section('content')

    <div class="row g-3">
        <div class="col-lg-8">

            {{-- Account summary --}}
            <div class="ico-card p-3 mb-3">
                <h2 class="h6 mb-3">Account summary</h2>

                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted fw-normal">Email</dt>
                    <dd class="col-sm-8">{{ $user->email }}</dd>

                    <dt class="col-sm-4 text-muted fw-normal">Verified</dt>
                    <dd class="col-sm-8">
                        @if ($user->email_verified_at)
                            <span class="badge text-bg-success">
                                <i class="bi bi-patch-check-fill" aria-hidden="true"></i>
                                Verified {{ $user->email_verified_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="badge text-bg-secondary">
                                <i class="bi bi-dash-circle" aria-hidden="true"></i>
                                Not verified
                            </span>
                        @endif
                    </dd>

                    <dt class="col-sm-4 text-muted fw-normal">Joined</dt>
                    <dd class="col-sm-8">{{ $user->created_at->format('M j, Y') }} ({{ $user->created_at->diffForHumans() }})</dd>

                    <dt class="col-sm-4 text-muted fw-normal">Downloads</dt>
                    <dd class="col-sm-8">{{ number_format($downloads) }}</dd>

                    <dt class="col-sm-4 text-muted fw-normal">Orders</dt>
                    <dd class="col-sm-8">{{ number_format($orders) }}</dd>

                    @if ($user->contributor)
                        <dt class="col-sm-4 text-muted fw-normal">Contributor</dt>
                        <dd class="col-sm-8">
                            {{ $user->contributor->display_name }}
                            <span class="badge {{ $contributorStatusColors[$user->contributor->status] ?? 'text-bg-secondary' }}">
                                {{ \Illuminate\Support\Str::headline($user->contributor->status) }}
                            </span>
                        </dd>
                    @endif
                </dl>
            </div>

            {{-- Recent downloads --}}
            <div class="ico-card p-3 mb-3">
                <h2 class="h6 mb-3">Recent downloads</h2>

                @if ($lastDownloads->isEmpty())
                    <p class="small mb-0 text-muted">This user has not downloaded anything yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th scope="col">Title</th>
                                    <th scope="col">Access type</th>
                                    <th scope="col">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lastDownloads as $download)
                                    <tr>
                                        <td>{{ $download->title }}</td>
                                        <td><span class="badge text-bg-secondary">{{ \Illuminate\Support\Str::headline($download->access_type) }}</span></td>
                                        <td>{{ \Carbon\Carbon::parse($download->created_at)->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Recent activity --}}
            <div class="ico-card p-3">
                <h2 class="h6 mb-3">Recent activity</h2>

                @if ($activity->isEmpty())
                    <p class="small mb-0 text-muted">No audited activity recorded for this account yet.</p>
                @else
                    <ul class="list-unstyled mb-0 small" role="list">
                        @foreach ($activity as $row)
                            <li class="d-flex justify-content-between align-items-start gap-2 py-1">
                                <span>
                                    <code style="color:var(--ico-primary-darker)">{{ $row->action }}</code>
                                    <span class="text-muted">by {{ $row->actor_label }}</span>
                                </span>
                                <span class="text-muted flex-shrink-0">{{ \Carbon\Carbon::parse($row->created_at)->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="col-lg-4">

            {{-- Roles --}}
            <div class="ico-card p-3 mb-3">
                <h2 class="h6 mb-3">Roles</h2>

                <form method="POST" action="{{ route('admin.users.roles', $user) }}">
                    @csrf

                    @foreach ($roles as $role)
                        @php
                            $canAssign = in_array($role, $assignable, true);
                        @endphp
                        <div class="form-check">
                            <input type="checkbox"
                                   class="form-check-input"
                                   id="role-{{ $user->id }}-{{ $loop->index }}"
                                   name="roles[]"
                                   value="{{ $role }}"
                                   @checked($user->hasRole($role))
                                   @disabled(! $canAssign)
                                   @if (! $canAssign) title="You cannot grant a role you do not hold" @endif>
                            <label class="form-check-label" for="role-{{ $user->id }}-{{ $loop->index }}">
                                {{ $role }}
                            </label>
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-ico-primary btn-sm mt-2">Update roles</button>
                </form>
            </div>

            @if (! $user->email_verified_at)
                {{-- Verify --}}
                <div class="ico-card p-3 mb-3">
                    <h2 class="h6 mb-2">Verify email</h2>
                    <p class="small text-muted mb-2">Mark this account's email address as verified.</p>
                    <form method="POST" action="{{ route('admin.users.verify', $user) }}">
                        @csrf
                        <button type="submit" class="btn btn-ico-outline btn-sm">Mark as verified</button>
                    </form>
                </div>
            @endif

            @if (! $user->hasRole('Suspended User'))
                {{-- Suspend --}}
                <div class="ico-card p-3 mb-3">
                    <h2 class="h6 mb-2">Suspend account</h2>
                    <p class="small text-muted mb-2">Removes every role and signs the user out everywhere.</p>
                    <button type="button" class="btn btn-danger btn-sm"
                            data-bs-toggle="modal" data-bs-target="#suspend-user-{{ $user->id }}">
                        Suspend user
                    </button>
                </div>

                <div class="modal fade" id="suspend-user-{{ $user->id }}" tabindex="-1"
                     aria-labelledby="suspend-user-{{ $user->id }}-label" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.users.suspend', $user) }}">
                                @csrf
                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="suspend-user-{{ $user->id }}-label">
                                        Suspend {{ $user->name }}
                                    </h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="suspend-reason-{{ $user->id }}" class="form-label small fw-semibold">Reason</label>
                                    <textarea id="suspend-reason-{{ $user->id }}" name="reason" rows="4"
                                              class="form-control" required minlength="10"
                                              aria-describedby="suspend-reason-{{ $user->id }}-help"></textarea>
                                    <div id="suspend-reason-{{ $user->id }}-help" class="form-text small">
                                        At least 10 characters. This is recorded in the audit log.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger btn-sm">Suspend user</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            @if ($user->hasRole('Suspended User'))
                {{-- Restore --}}
                <div class="ico-card p-3 mb-3">
                    <h2 class="h6 mb-2">Restore account</h2>
                    <p class="small text-muted mb-2">Lift the suspension and grant a role.</p>
                    <button type="button" class="btn btn-ico-primary btn-sm"
                            data-bs-toggle="modal" data-bs-target="#restore-user-{{ $user->id }}">
                        Restore user
                    </button>
                </div>

                <div class="modal fade" id="restore-user-{{ $user->id }}" tabindex="-1"
                     aria-labelledby="restore-user-{{ $user->id }}-label" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.users.restore', $user) }}">
                                @csrf
                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="restore-user-{{ $user->id }}-label">
                                        Restore {{ $user->name }}
                                    </h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="restore-role-{{ $user->id }}" class="form-label small fw-semibold">Role to grant</label>
                                    <select id="restore-role-{{ $user->id }}" name="role" class="form-select" required>
                                        @foreach ($assignable as $role)
                                            <option value="{{ $role }}">{{ $role }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-ico-primary btn-sm">Restore user</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Danger zone --}}
            <div class="ico-card p-3 border-danger">
                <h2 class="h6 mb-3 text-danger">Danger zone</h2>

                <div class="mb-3">
                    <p class="small text-muted mb-2">Force this user out of every active session.</p>
                    <form method="POST" action="{{ route('admin.users.logout', $user) }}">
                        @csrf
                        <button type="submit" class="btn btn-ico-outline btn-sm">Force logout</button>
                    </form>
                </div>

                <div class="mb-3">
                    <p class="small text-muted mb-2">Invalidate their password and clear sessions.</p>
                    <button type="button" class="btn btn-ico-outline btn-sm"
                            data-bs-toggle="modal" data-bs-target="#password-reset-user-{{ $user->id }}">
                        Force password reset
                    </button>
                </div>

                <div>
                    <p class="small text-muted mb-2">Sign in as this account to see what they see.</p>
                    <button type="button" class="btn btn-ico-outline btn-sm"
                            data-bs-toggle="modal" data-bs-target="#impersonate-user-{{ $user->id }}">
                        Impersonate
                    </button>
                </div>
            </div>

            <div class="modal fade" id="password-reset-user-{{ $user->id }}" tabindex="-1"
                 aria-labelledby="password-reset-user-{{ $user->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.users.password', $user) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="password-reset-user-{{ $user->id }}-label">
                                    Force password reset for {{ $user->name }}?
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small mb-0">
                                    Invalidates their password and clears sessions. They must use forgot-password.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Force password reset</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="impersonate-user-{{ $user->id }}" tabindex="-1"
                 aria-labelledby="impersonate-user-{{ $user->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.users.impersonate', $user) }}">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="impersonate-user-{{ $user->id }}-label">
                                    Impersonate {{ $user->name }}?
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small mb-0">
                                    You will be signed in as this user until you stop impersonating.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-ico-primary btn-sm">Impersonate</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
