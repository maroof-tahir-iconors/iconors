@extends('layouts.app')

@section('title', 'Upload asset — Iconors')

@section('content')
<div class="container-ico px-3 py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">
            <div class="ico-card p-4 p-md-5">
                <h1 class="h4 mb-1">Upload an asset</h1>
                <p class="text-muted small mb-4">
                    Every upload goes to review before it is published. You will be notified of the decision.
                </p>

                @error('file')
                    <div class="alert alert-danger small" role="alert">
                        <strong>Upload rejected:</strong> {{ $message }}
                    </div>
                @enderror

                <form method="POST" action="{{ route('contributor.assets.store') }}"
                      enctype="multipart/form-data" novalidate>
                    @csrf

                    <div class="mb-4">
                        <label for="file" class="form-label small fw-semibold">File</label>

                        <div id="ico-dropzone" class="border border-2 border-dashed rounded-3 text-center p-4"
                             style="cursor: pointer" role="button" tabindex="0"
                             aria-label="Choose a file to upload, or drag and drop it here">
                            <i class="bi bi-cloud-upload fs-2 text-muted d-block mb-2" aria-hidden="true"></i>
                            <p class="mb-1 small">Drag and drop a file here, or click to browse</p>
                            <p class="small text-muted mb-0" id="ico-dropzone-filename">No file selected</p>
                            <input id="file" name="file" type="file"
                                   class="visually-hidden @error('file') is-invalid @enderror" required>
                        </div>
                        @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="title" class="form-label small fw-semibold">Title</label>
                        <input id="title" name="title" type="text"
                               class="form-control @error('title') is-invalid @enderror"
                               value="{{ old('title') }}" required minlength="3" maxlength="200">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label small fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea id="description" name="description" rows="4" maxlength="2000"
                                  class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="asset_type_id" class="form-label small fw-semibold">Asset type</label>
                            <select id="asset_type_id" name="asset_type_id"
                                    class="form-select @error('asset_type_id') is-invalid @enderror" required>
                                <option value="" disabled @selected(old('asset_type_id') === null)>Select a type</option>
                                @foreach ($assetTypes as $type)
                                    <option value="{{ $type->id }}" @selected((string) old('asset_type_id') === (string) $type->id)>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('asset_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="category_id" class="form-label small fw-semibold">Category <span class="text-muted fw-normal">(optional)</span></label>
                            <select id="category_id" name="category_id"
                                    class="form-select @error('category_id') is-invalid @enderror">
                                <option value="">None</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label for="style_id" class="form-label small fw-semibold">Style <span class="text-muted fw-normal">(optional)</span></label>
                            <select id="style_id" name="style_id"
                                    class="form-select @error('style_id') is-invalid @enderror">
                                <option value="">None</option>
                                @foreach ($styles as $style)
                                    <option value="{{ $style->id }}" @selected((string) old('style_id') === (string) $style->id)>
                                        {{ $style->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('style_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="license_id" class="form-label small fw-semibold">License <span class="text-muted fw-normal">(optional)</span></label>
                        <select id="license_id" name="license_id"
                                class="form-select @error('license_id') is-invalid @enderror">
                            <option value="">None</option>
                            @foreach ($licenses as $license)
                                <option value="{{ $license->id }}" @selected((string) old('license_id') === (string) $license->id)>
                                    {{ $license->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('license_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="tags" class="form-label small fw-semibold">Tags <span class="text-muted fw-normal">(optional)</span></label>
                        <input id="tags" name="tags" type="text"
                               class="form-control @error('tags') is-invalid @enderror"
                               value="{{ old('tags') }}" maxlength="500" aria-describedby="tags-help">
                        <div id="tags-help" class="form-text small">
                            Comma-separated, e.g. "flat, gradient, business".
                        </div>
                        @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_premium" value="1"
                                   id="is_premium" @checked(old('is_premium'))>
                            <label class="form-check-label small fw-semibold" for="is_premium">Premium asset</label>
                        </div>
                    </div>

                    <div class="mb-3" id="ico-price-wrap" style="{{ old('is_premium') ? '' : 'display:none' }}">
                        <label for="price" class="form-label small fw-semibold">Price (USD)</label>
                        <input id="price" name="price" type="number" step="0.01" min="0" max="9999"
                               class="form-control @error('price') is-invalid @enderror"
                               value="{{ old('price') }}">
                        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_animated" value="1"
                                   id="is_animated" @checked(old('is_animated'))>
                            <label class="form-check-label small" for="is_animated">Animated</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_3d" value="1"
                                   id="is_3d" @checked(old('is_3d'))>
                            <label class="form-check-label small" for="is_3d">3D</label>
                        </div>
                    </div>

                    <button class="btn btn-ico-primary w-100" type="submit">Submit for review</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var dropzone = document.getElementById('ico-dropzone');
        var input = document.getElementById('file');
        var filename = document.getElementById('ico-dropzone-filename');
        var premium = document.getElementById('is_premium');
        var priceWrap = document.getElementById('ico-price-wrap');

        if (dropzone && input && filename) {
            dropzone.addEventListener('click', function () {
                input.click();
            });

            dropzone.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    input.click();
                }
            });

            input.addEventListener('change', function () {
                filename.textContent = input.files.length ? input.files[0].name : 'No file selected';
            });

            ['dragover', 'dragenter'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    dropzone.classList.add('border-primary');
                });
            });

            ['dragleave', 'dragend'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    dropzone.classList.remove('border-primary');
                });
            });

            dropzone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropzone.classList.remove('border-primary');
                if (e.dataTransfer && e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    filename.textContent = e.dataTransfer.files[0].name;
                }
            });
        }

        if (premium && priceWrap) {
            premium.addEventListener('change', function () {
                priceWrap.style.display = premium.checked ? '' : 'none';
            });
        }
    })();
</script>
@endpush
