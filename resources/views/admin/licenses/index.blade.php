@extends('layouts.admin')

@section('title', 'Licences')
@section('subtitle', 'Licence terms attached to assets and plans.')

@section('content')

    <div class="d-flex flex-wrap justify-content-end align-items-start gap-2 mb-4">
        <a href="{{ route('admin.licenses.create') }}" class="btn btn-ico-primary">Create licence</a>
    </div>

    @if ($licenses->isEmpty())
        <div class="empty-state ico-card">
            <div class="empty-state__icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div>
            <h2 class="h6">No licences yet</h2>
            <p class="text-muted small mb-0">Create a licence so assets have terms to be issued under.</p>
        </div>
    @else
        <div class="ico-card p-0">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Summary</th>
                            <th scope="col">Permissions</th>
                            <th scope="col">Assets</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($licenses as $license)
                            <tr>
                                <td class="fw-semibold">{{ $license->name }}</td>
                                <td><code class="small">{{ $license->slug }}</code></td>
                                <td class="small text-muted">{{ Str::limit($license->summary, 80) }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @if ($license->allows_commercial)
                                            <span class="badge text-bg-success">Commercial</span>
                                        @endif
                                        @if ($license->allows_modification)
                                            <span class="badge text-bg-info text-dark">Modify</span>
                                        @endif
                                        @if ($license->allows_redistribution)
                                            <span class="badge text-bg-info text-dark">Redistribute</span>
                                        @endif
                                        @if ($license->requires_attribution)
                                            <span class="badge text-bg-warning text-dark">Attribution required</span>
                                        @endif
                                        @if ($license->is_extended)
                                            <span class="badge text-bg-primary">Extended</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $license->assets_count }}</td>
                                <td>
                                    <span class="badge {{ $license->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $license->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="{{ route('admin.licenses.edit', $license) }}" class="btn btn-ico-outline btn-sm">Edit</a>
                                        <button type="button" class="btn btn-ico-outline btn-sm text-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#delete-license-{{ $license->id }}">
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

        {{-- One confirm-delete modal per licence. --}}
        @foreach ($licenses as $license)
            <div class="modal fade" id="delete-license-{{ $license->id }}" tabindex="-1"
                 aria-labelledby="delete-license-{{ $license->id }}-label" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('admin.licenses.destroy', $license) }}">
                            @csrf
                            @method('DELETE')
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="delete-license-{{ $license->id }}-label">
                                    Delete &ldquo;{{ $license->name }}&rdquo;?
                                </h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-0 small">
                                    This cannot be undone. Licences attached to assets cannot be deleted —
                                    reassign those assets first, or deactivate the licence instead.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-ico-outline btn-sm" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm">Delete licence</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
@endsection
