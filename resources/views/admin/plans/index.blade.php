@extends('layouts.admin')

@section('title', 'Plans')
@section('subtitle', 'Pricing, limits and entitlements for every subscription plan.')

@section('content')

    <div class="d-flex flex-wrap justify-content-end align-items-start gap-2 mb-4">
        <a href="{{ route('admin.plans.create') }}" class="btn btn-ico-primary">Create plan</a>
    </div>

    @if ($plans->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-card-list" aria-hidden="true"></i></div>
            <h2 class="h6">No plans yet</h2>
            <p class="text-muted small mb-0">Create a subscription plan to start selling access.</p>
        </div>
    @else
        <div class="ico-card p-0">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Monthly</th>
                            <th scope="col">Annual</th>
                            <th scope="col">Daily download limit</th>
                            <th scope="col">Premium access</th>
                            <th scope="col">Status</th>
                            <th scope="col">Subscribers</th>
                            <th scope="col">Features</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="fw-semibold">{{ $plan->name }}</td>
                                <td><code class="small">{{ $plan->slug }}</code></td>
                                <td>${{ number_format((float) $plan->price_monthly, 2) }}</td>
                                <td>${{ number_format((float) $plan->price_annual, 2) }}</td>
                                <td>
                                    @if (is_null($plan->download_limit_daily))
                                        <span class="badge text-bg-light">Unlimited</span>
                                    @elseif ((int) $plan->download_limit_daily === 0)
                                        <span class="badge text-bg-light">None</span>
                                    @else
                                        {{ $plan->download_limit_daily }}/day
                                    @endif
                                </td>
                                <td>
                                    @if ($plan->premium_access)
                                        <span class="badge text-bg-success">Premium</span>
                                    @else
                                        <span class="badge text-bg-secondary">Standard</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $plan->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                    <span class="badge {{ $plan->is_public ? 'text-bg-info text-dark' : 'text-bg-secondary' }}">
                                        {{ $plan->is_public ? 'Public' : 'Hidden' }}
                                    </span>
                                </td>
                                <td>{{ $subscriberCounts[$plan->slug] ?? 0 }}</td>
                                <td>{{ $plan->features_count }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-ico-outline btn-sm">Edit</a>
                                        <button type="button" class="btn btn-ico-outline btn-sm text-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#delete-plan-{{ $plan->id }}">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- One confirm-delete modal per plan. --}}
        @foreach ($plans as $plan)
            <div class="modal fade" id="delete-plan-{{ $plan->id }}" tabindex="-1"
                 aria-labelledby="delete-plan-{{ $plan->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="delete-plan-{{ $plan->id }}-label">
                                    Delete &ldquo;{{ $plan->name }}&rdquo;?
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0 small">
                                    This cannot be undone. Plans with active subscribers cannot be deleted —
                                    deactivate them instead so existing subscribers keep their access.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Delete plan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
