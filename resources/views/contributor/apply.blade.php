@extends('layouts.app')

@section('title', 'Become a contributor — Iconors')

@section('content')
<div class="container-ico px-3 py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="ico-card p-4 p-md-5">
                <h1 class="h4 mb-1">Become a contributor</h1>
                <p class="text-muted small mb-4">
                    Tell us about yourself and your work. We usually review applications within 3 working days.
                </p>

                @if ($existing && in_array($existing->status, ['rejected', 'more_info_required'], true))
                    <div class="alert {{ $existing->status === 'rejected' ? 'alert-danger' : 'alert-warning' }} small" role="alert">
                        <p class="fw-semibold mb-1">
                            @if ($existing->status === 'rejected')
                                Your previous application was not approved.
                            @else
                                We need a little more information.
                            @endif
                        </p>
                        @if ($existing->reviewer_notes)
                            <p class="mb-0">{{ $existing->reviewer_notes }}</p>
                        @endif
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger small py-2" role="alert">
                        Please fix the errors below and try again.
                    </div>
                @endif

                <form method="POST" action="{{ route('contributor.apply.store') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="full_name" class="form-label small fw-semibold">Full name</label>
                        <input id="full_name" name="full_name" type="text"
                               class="form-control @error('full_name') is-invalid @enderror"
                               value="{{ old('full_name') }}" required maxlength="160" autofocus>
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="display_name" class="form-label small fw-semibold">Display name</label>
                        <input id="display_name" name="display_name" type="text"
                               class="form-control @error('display_name') is-invalid @enderror"
                               value="{{ old('display_name') }}" required maxlength="120"
                               aria-describedby="display_name-help">
                        <div id="display_name-help" class="form-text small">
                            Shown publicly on your assets and profile.
                        </div>
                        @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="country" class="form-label small fw-semibold">Country</label>
                        <select id="country" name="country"
                                class="form-select @error('country') is-invalid @enderror" required>
                            <option value="" disabled @selected(old('country') === null)>Select your country</option>
                            @foreach ([
                                'US' => 'United States',
                                'GB' => 'United Kingdom',
                                'CA' => 'Canada',
                                'AU' => 'Australia',
                                'DE' => 'Germany',
                                'FR' => 'France',
                                'ES' => 'Spain',
                                'IT' => 'Italy',
                                'NL' => 'Netherlands',
                                'SE' => 'Sweden',
                                'PL' => 'Poland',
                                'IN' => 'India',
                                'PK' => 'Pakistan',
                                'BD' => 'Bangladesh',
                                'PH' => 'Philippines',
                                'ID' => 'Indonesia',
                                'BR' => 'Brazil',
                                'MX' => 'Mexico',
                                'NG' => 'Nigeria',
                                'ZA' => 'South Africa',
                            ] as $code => $label)
                                <option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="biography" class="form-label small fw-semibold">Biography</label>
                        <textarea id="biography" name="biography" rows="5"
                                  class="form-control @error('biography') is-invalid @enderror"
                                  required aria-describedby="biography-help">{{ old('biography') }}</textarea>
                        <div id="biography-help" class="form-text small">
                            Tell us about your experience and the kind of work you make. At least 40 characters.
                        </div>
                        @error('biography')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="portfolio_url" class="form-label small fw-semibold">Portfolio URL</label>
                        <input id="portfolio_url" name="portfolio_url" type="url"
                               class="form-control @error('portfolio_url') is-invalid @enderror"
                               value="{{ old('portfolio_url') }}" required
                               placeholder="https://" maxlength="255">
                        @error('portfolio_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="website" class="form-label small fw-semibold">Website <span class="text-muted fw-normal">(optional)</span></label>
                        <input id="website" name="website" type="url"
                               class="form-control @error('website') is-invalid @enderror"
                               value="{{ old('website') }}" placeholder="https://" maxlength="255">
                        @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <fieldset class="mb-3">
                        <legend class="form-label small fw-semibold h6">Specialties</legend>
                        <div class="row row-cols-2 g-2 @error('specialties') is-invalid @enderror">
                            @foreach (['Icons', 'Illustrations', '3D', 'Lottie Animations', 'Stickers', 'Emojis'] as $specialty)
                                <div class="col">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox"
                                               name="specialties[]" value="{{ $specialty }}"
                                               id="specialty-{{ Str::slug($specialty) }}"
                                               @checked(collect(old('specialties', []))->contains($specialty))>
                                        <label class="form-check-label small" for="specialty-{{ Str::slug($specialty) }}">
                                            {{ $specialty }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('specialties')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        @error('specialties.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </fieldset>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox"
                                   name="terms" value="1" id="terms" @checked(old('terms')) required>
                            <label class="form-check-label small" for="terms">
                                I accept the contributor terms.
                            </label>
                            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <button class="btn btn-ico-primary w-100" type="submit">Submit application</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
