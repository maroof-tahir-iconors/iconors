@extends('layouts.admin')

@section('title', 'Contributor applications')
@section('subtitle', 'Review and decide on incoming contributor applications.')

@php
    $statusTabs = [
        'pending'            => 'Pending',
        'under_review'       => 'Under review',
        'more_info_required' => 'More info required',
        'approved'           => 'Approved',
        'rejected'           => 'Rejected',
    ];

    $statusColors = [
        'pending'            => 'bg-secondary',
        'under_review'       => 'bg-info text-dark',
        'more_info_required' => 'bg-warning text-dark',
        'approved'           => 'bg-success',
        'rejected'           => 'bg-danger',
    ];

    $currentStatus = request('status', 'pending');
@endphp

@section('content')

    <nav class="mb-4" aria-label="Filter applications by status">
        <ul class="nav nav-pills flex-wrap gap-1">
            @foreach ($statusTabs as $value => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $currentStatus === $value ? 'active' : '' }}"
                       href="{{ route('admin.applications', ['status' => $value]) }}">
                        {{ $label }} <span class="badge bg-secondary bg-opacity-50 ms-1">{{ $counts[$value] ?? 0 }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    @if ($applications->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h2 class="h6">No applications here</h2>
            <p class="text-muted small mb-0">There are no applications with this status.</p>
        </div>
    @else
        <div class="ico-card p-0">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Applicant</th>
                            <th scope="col">Display name</th>
                            <th scope="col">Country</th>
                            <th scope="col">Portfolio</th>
                            <th scope="col">Submitted</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($applications as $application)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $application->user->name }}</div>
                                    <div class="small text-muted">{{ $application->user->email }}</div>
                                </td>
                                <td>{{ $application->display_name }}</td>
                                <td>{{ $application->country }}</td>
                                <td>
                                    <a href="{{ $application->portfolio_url }}" target="_blank" rel="noopener">
                                        View <i class="bi bi-box-arrow-up-right small" aria-hidden="true"></i>
                                    </a>
                                </td>
                                <td>{{ $application->created_at->format('M j, Y') }}</td>
                                <td>
                                    <span class="badge {{ $statusColors[$application->status] ?? 'bg-secondary' }}">
                                        {{ $statusTabs[$application->status] ?? Str::headline($application->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if ($application->status === 'pending')
                                        <div class="d-flex flex-wrap gap-1">
                                            <form method="POST" action="{{ route('admin.applications.approve', $application) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-ico-primary btn-sm">Approve</button>
                                            </form>

                                            <button type="button" class="btn btn-ico-outline btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#reject-application-{{ $application->id }}">
                                                Reject
                                            </button>

                                            <button type="button" class="btn btn-ico-outline btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#info-application-{{ $application->id }}">
                                                Request info
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted small">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">
            {{ $applications->links() }}
        </div>

        {{-- Reject / request-info modals, one pair per application. --}}
        @foreach ($applications as $application)
            @if ($application->status === 'pending')
                <div class="modal fade" id="reject-application-{{ $application->id }}" tabindex="-1"
                     aria-labelledby="reject-application-{{ $application->id }}-label" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.applications.reject', $application) }}">
                                @csrf
                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="reject-application-{{ $application->id }}-label">
                                        Reject {{ $application->display_name }}'s application
                                    </h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="reject-reason-{{ $application->id }}" class="form-label small fw-semibold">Reason</label>
                                    <textarea id="reject-reason-{{ $application->id }}" name="reason" rows="4"
                                              class="form-control" required minlength="10"
                                              aria-describedby="reject-reason-{{ $application->id }}-help"></textarea>
                                    <div id="reject-reason-{{ $application->id }}-help" class="form-text small">
                                        Shown to the applicant. At least 10 characters.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger btn-sm">Reject application</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="info-application-{{ $application->id }}" tabindex="-1"
                     aria-labelledby="info-application-{{ $application->id }}-label" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('admin.applications.info', $application) }}">
                                @csrf
                                <div class="modal-header">
                                    <h2 class="modal-title h5" id="info-application-{{ $application->id }}-label">
                                        Request more info from {{ $application->display_name }}
                                    </h2>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <label for="info-notes-{{ $application->id }}" class="form-label small fw-semibold">Notes</label>
                                    <textarea id="info-notes-{{ $application->id }}" name="notes" rows="4"
                                              class="form-control" required minlength="10"
                                              aria-describedby="info-notes-{{ $application->id }}-help"></textarea>
                                    <div id="info-notes-{{ $application->id }}-help" class="form-text small">
                                        Shown to the applicant. At least 10 characters.
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-ico-primary btn-sm">Request info</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
@endsection
