@extends('layouts.admin')

@section('title', 'Marketplace rules')
@section('subtitle', 'Download limits, upload caps, moderation policy and link security.')

@section('content')

    <form method="POST" action="{{ route('admin.settings.marketplace.update') }}" novalidate>
        @csrf
        <input type="hidden" name="_fields" value="guest_download_enabled,require_asset_review,watermark_previews">

        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Downloads</h2>

            <div class="mb-3">
                <label for="free_download_limit_daily" class="form-label small fw-semibold">Free download limit (daily)</label>
                <input id="free_download_limit_daily" name="free_download_limit_daily" type="number" min="0" max="100000"
                       class="form-control @error('free_download_limit_daily') is-invalid @enderror"
                       value="{{ old('free_download_limit_daily', $settings['free_download_limit_daily']['value'] ?? 3) }}"
                       aria-describedby="free_download_limit_daily-help">
                <div id="free_download_limit_daily-help" class="form-text small">
                    Downloads per day for free users. Set to 0 for unlimited.
                </div>
                @error('free_download_limit_daily')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="guest_download_enabled" name="guest_download_enabled" value="1"
                           @checked(old('guest_download_enabled', $settings['guest_download_enabled']['value'] ?? false))>
                    <label class="form-check-label small fw-semibold" for="guest_download_enabled">Allow guest downloads</label>
                </div>
                @error('guest_download_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <label for="download_url_ttl_minutes" class="form-label small fw-semibold">Signed download link lifetime (minutes)</label>
                <input id="download_url_ttl_minutes" name="download_url_ttl_minutes" type="number" min="1" max="1440"
                       class="form-control @error('download_url_ttl_minutes') is-invalid @enderror"
                       value="{{ old('download_url_ttl_minutes', $settings['download_url_ttl_minutes']['value'] ?? 15) }}"
                       aria-describedby="download_url_ttl_minutes-help">
                <div id="download_url_ttl_minutes-help" class="form-text small">
                    How long a signed download link stays valid. Keep this short — a long-lived link is
                    effectively a shareable key to a premium file.
                </div>
                @error('download_url_ttl_minutes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="ico-card p-4 p-md-5 mb-4">
            <h2 class="h6 mb-3">Uploads & moderation</h2>

            <div class="mb-3">
                <label for="upload_max_size_mb" class="form-label small fw-semibold">Maximum upload size (MB)</label>
                <input id="upload_max_size_mb" name="upload_max_size_mb" type="number" min="1" max="2048"
                       class="form-control @error('upload_max_size_mb') is-invalid @enderror"
                       value="{{ old('upload_max_size_mb', $settings['upload_max_size_mb']['value'] ?? 100) }}">
                @error('upload_max_size_mb')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="require_asset_review" name="require_asset_review" value="1"
                           @checked(old('require_asset_review', $settings['require_asset_review']['value'] ?? true))
                           aria-describedby="require_asset_review-help">
                    <label class="form-check-label small fw-semibold" for="require_asset_review">Require review before publishing</label>
                </div>
                <div id="require_asset_review-help" class="form-text small">
                    When off, contributor uploads publish immediately with no moderation.
                </div>
                @error('require_asset_review')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <div class="mb-1">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch"
                           id="watermark_previews" name="watermark_previews" value="1"
                           @checked(old('watermark_previews', $settings['watermark_previews']['value'] ?? false))>
                    <label class="form-check-label small fw-semibold" for="watermark_previews">Watermark preview images</label>
                </div>
                @error('watermark_previews')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>

        <button type="submit" class="btn btn-ico-primary">Save marketplace settings</button>
    </form>
@endsection
