{{--
    Shared chrome for every My Account tab: the page title and the tab bar.

    A component-style include rather than a Blade layout so each tab view can
    still `@extends('layouts.app')` and keep its own <title> and SEO block.

    `aria-current="page"` marks the active tab for screen readers; the visual
    underline is driven off the same condition so the two can never disagree.
--}}
@php
    $tabs = [
        // Served at /dashboard — the route name is load-bearing, see
        // AccountController::overview().
        ['route' => 'dashboard',            'label' => 'Overview',       'badge' => null],
        ['route' => 'account.collections',  'label' => 'My Collections', 'badge' => null],
        ['route' => 'account.downloads',    'label' => 'My Downloads',   'badge' => null],
        ['route' => 'account.following',    'label' => 'My Following',   'badge' => null],
        ['route' => 'account.api',          'label' => 'API',            'badge' => 'NEW'],
        ['route' => 'account.details',      'label' => 'Account Details','badge' => null],
        ['route' => 'account.subscription', 'label' => 'Subscription',   'badge' => null],
    ];
@endphp

<div class="account-head">
    <div class="container-ico">
        <h1 class="account-head__title">My Account</h1>

        <nav class="account-tabs" aria-label="Account sections">
            <ul role="list">
                @foreach ($tabs as $tab)
                    @php $isActive = request()->routeIs($tab['route']); @endphp
                    <li>
                        <a href="{{ route($tab['route']) }}"
                           class="account-tabs__link @if($isActive) is-active @endif"
                           @if($isActive) aria-current="page" @endif>
                            {{ $tab['label'] }}
                            @if ($tab['badge'])
                                <span class="account-tabs__badge">{{ $tab['badge'] }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</div>

@if (session('status'))
    <div class="container-ico">
        <div class="alert alert-success d-flex align-items-start gap-2 mt-4 mb-0" role="alert">
            <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
            <div>{{ session('status') }}</div>
        </div>
    </div>
@endif
