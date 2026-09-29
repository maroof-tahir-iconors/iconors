@extends('layouts.app')

@section('title', 'Applications closed — Iconors')

@section('content')
<div class="container-ico px-3 py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-xl-6">
            <div class="empty-state ico-card">
                <div class="empty-state__icon"><i class="bi bi-door-closed" aria-hidden="true"></i></div>
                <h1 class="h5">Contributor applications are temporarily closed</h1>
                <p class="text-muted small mb-3">
                    We are not accepting new contributor applications right now. Please check back later —
                    in the meantime you can browse and download assets from the marketplace.
                </p>
                <a class="btn btn-ico-primary btn-sm" href="{{ route('home') }}">Back to home</a>
            </div>
        </div>
    </div>
</div>
@endsection
