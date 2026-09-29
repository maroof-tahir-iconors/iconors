@extends('layouts.app')

@section('title', 'Your application — Iconors')

@php
    $statusLabels = [
        'pending'            => 'Pending',
        'under_review'       => 'Under review',
        'more_info_required' => 'More info required',
        'approved'           => 'Approved',
        'rejected'           => 'Not approved',
    ];

    $statusColors = [
        'pending'            => 'bg-secondary',
        'under_review'       => 'bg-info text-dark',
        'more_info_required' => 'bg-warning text-dark',
        'approved'           => 'bg-success',
        'rejected'           => 'bg-danger',
    ];

    // Stepper position: 1 = submitted only, 2 = under review, 3 = decision.
    $step = match ($application->status) {
        'pending' => 1,
        'under_review', 'more_info_required' => 2,
        'approved', 'rejected' => 3,
        default => 1,
    };
@endphp

@section('content')
<div class="container-ico px-3 py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="ico-card p-4 p-md-5">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                    <h1 class="h4 mb-0">Your application</h1>
                    <span class="badge {{ $statusColors[$application->status] ?? 'bg-secondary' }}">
                        {{ $statusLabels[$application->status] ?? Str::headline($application->status) }}
                    </span>
                </div>
                <p class="text-muted small mb-4">
                    Submitted {{ $application->created_at->format('M j, Y') }}
                </p>

                {{-- Simple visual stepper. --}}
                <ol class="list-unstyled d-flex align-items-center gap-2 mb-4" aria-label="Application progress">
                    <li class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill {{ $step >= 1 ? 'bg-primary' : 'bg-secondary' }}">1</span>
                        <span class="small {{ $step >= 1 ? 'fw-semibold' : 'text-muted' }}">Submitted</span>
                    </li>
                    <li aria-hidden="true" class="flex-grow-1 border-top mx-2" style="min-width:1.5rem"></li>
                    <li class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill {{ $step >= 2 ? 'bg-primary' : 'bg-secondary' }}">2</span>
                        <span class="small {{ $step >= 2 ? 'fw-semibold' : 'text-muted' }}">Under review</span>
                    </li>
                    <li aria-hidden="true" class="flex-grow-1 border-top mx-2" style="min-width:1.5rem"></li>
                    <li class="d-flex align-items-center gap-2">
                        <span class="badge rounded-pill {{ $step >= 3 ? 'bg-primary' : 'bg-secondary' }}">3</span>
                        <span class="small {{ $step >= 3 ? 'fw-semibold' : 'text-muted' }}">Decision</span>
                    </li>
                </ol>

                @if ($application->reviewer_notes)
                    <div class="alert {{ $application->status === 'rejected' ? 'alert-danger' : 'alert-warning' }} small" role="alert">
                        <p class="fw-semibold mb-1">Note from the review team</p>
                        <p class="mb-0">{{ $application->reviewer_notes }}</p>
                    </div>
                @endif

                @if (in_array($application->status, ['rejected', 'more_info_required'], true))
                    <p class="mb-0">
                        <a class="btn btn-ico-primary" href="{{ route('contributor.apply') }}">Revise and resubmit</a>
                    </p>
                @elseif ($application->status === 'approved')
                    <p class="mb-0">
                        <a class="btn btn-ico-primary" href="{{ route('contributor.assets.index') }}">Go to your contributor workspace</a>
                    </p>
                @else
                    <p class="text-muted small mb-0">
                        We will email you as soon as a decision has been made. No action is needed from you right now.
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
