<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Licence terms management — brief §25 taxonomies, §29 licence explanations.
 *
 * Licence text is legally operative: it is what a customer is granted at
 * download. Every download therefore stores a SNAPSHOT of the terms as
 * they were at that moment (`download_licenses.terms_snapshot`), so
 * editing this text never retroactively changes what someone already
 * bought.
 */
class LicenseController extends Controller
{
    public function index(Request $request)
    {
        $this->gate($request);

        return view('admin.licenses.index', [
            'licenses' => License::withCount('assets')->orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->gate($request);

        return view('admin.licenses.edit', [
            'license' => new License([
                'is_active'            => true,
                'allows_modification'  => true,
                'requires_attribution' => true,
            ]),
        ]);
    }

    public function edit(Request $request, License $license)
    {
        $this->gate($request);

        return view('admin.licenses.edit', ['license' => $license]);
    }

    public function store(Request $request)
    {
        $this->gate($request);

        $license = License::create($this->validated($request));
        $this->audit($request, 'license.created', $license);

        return redirect()->route('admin.licenses.index')
            ->with('status', "Licence “{$license->name}” created.");
    }

    public function update(Request $request, License $license)
    {
        $this->gate($request);

        $before = $license->only(['name', 'summary', 'allows_commercial', 'requires_attribution']);

        $license->update($this->validated($request, $license));

        $this->audit($request, 'license.updated', $license, $before);

        return redirect()->route('admin.licenses.index')
            ->with('status', "Licence “{$license->name}” updated. Existing downloads keep the terms they were issued under.");
    }

    public function destroy(Request $request, License $license)
    {
        $this->gate($request);

        $inUse = DB::table('assets')->where('license_id', $license->id)->whereNull('deleted_at')->count();

        if ($inUse > 0) {
            return back()->withErrors([
                'license' => "This licence is attached to {$inUse} ".Str::plural('asset', $inUse)
                    .'. Reassign those assets first, or deactivate the licence instead.',
            ]);
        }

        $license->delete();
        $this->audit($request, 'license.deleted', $license);

        return redirect()->route('admin.licenses.index')->with('status', 'Licence deleted.');
    }

    private function validated(Request $request, ?License $license = null): array
    {
        $unique = $license ? ",{$license->id}" : '';

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'slug'      => ['required', 'string', 'max:140', "unique:licenses,slug{$unique}"],
            'summary'   => ['required', 'string', 'max:500'],
            'full_text' => ['required', 'string', 'max:60000'],

            'allows_commercial'     => ['nullable', 'boolean'],
            'allows_modification'   => ['nullable', 'boolean'],
            'allows_redistribution' => ['nullable', 'boolean'],
            'requires_attribution'  => ['nullable', 'boolean'],
            'is_extended'           => ['nullable', 'boolean'],
            'is_active'             => ['nullable', 'boolean'],
            'sort_order'            => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'summary.required'   => 'A short summary is required — it is what customers actually read.',
            'full_text.required' => 'The full licence text is required; it is the legally operative wording.',
        ]);

        foreach ([
            'allows_commercial', 'allows_modification', 'allows_redistribution',
            'requires_attribution', 'is_extended', 'is_active',
        ] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['slug'] = Str::slug($data['slug']);

        return $data;
    }

    private function audit(Request $request, string $action, License $license, array $before = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id'    => $request->user()->id,
            'actor_label' => $request->user()->email,
            'action'      => $action,
            'target_type' => License::class,
            'target_id'   => $license->id,
            'old_values'  => $before ? json_encode($before) : null,
            'new_values'  => json_encode($license->only(['name', 'summary', 'allows_commercial'])),
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 500),
            'created_at'  => now(),
        ]);
    }

    private function gate(Request $request): void
    {
        abort_unless($request->user()?->can('taxonomies.edit.all'), 403);
    }
}
