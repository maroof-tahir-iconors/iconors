<?php

namespace App\Services\Download;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\Download;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mints expiring signed URLs for premium source files and streams them.
 *
 * Brief §32: "Premium source files must never be available through
 * permanent public URLs."
 *
 * The guarantee rests on three things together — any one alone is not enough:
 *   1. Files live on a PRIVATE disk with no public route to them.
 *   2. The only handle is a signed URL carrying a short TTL.
 *   3. The signature binds the URL to ONE user, so a leaked or shared
 *      link is useless to anyone else even before it expires.
 *
 * Point 3 is the one usually missed. A signed URL that only encodes the
 * file id is a bearer token: paste it in a forum and the asset is public
 * until it expires.
 */
class SecureDownloadService
{
    public function __construct(
        private readonly DownloadEntitlementService $entitlements,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @throws RuntimeException when the user is not entitled.
     */
    public function createSignedUrl(User $user, Asset $asset, ?AssetFile $file = null): string
    {
        $entitlement = $this->entitlements->check($user, $asset);

        if (! $entitlement->allowed) {
            throw new RuntimeException($entitlement->reason);
        }

        $file ??= $this->primarySourceFile($asset);

        if (! $file) {
            throw new RuntimeException('no_downloadable_file');
        }

        // A file that failed or is awaiting malware/SVG sanitisation is
        // never served, no matter how well-entitled the requester is.
        if ($file->scan_status !== 'clean') {
            throw new RuntimeException('file_not_available');
        }

        $ttl = max(1, (int) $this->settings->get('download_url_ttl_minutes', 5));

        return URL::temporarySignedRoute(
            'download.serve',
            now()->addMinutes($ttl),
            [
                'asset' => $asset->id,
                'file'  => $file->id,
                // Binds the signature to this user. Tampering with it
                // invalidates the signature; omitting it would make the
                // URL transferable.
                'u'     => $user->id,
            ],
        );
    }

    /**
     * Streams the file. Re-verifies entitlement rather than trusting the
     * signature alone: a URL minted 5 minutes ago must not still work if
     * the subscription was cancelled or the asset suspended since.
     */
    public function serve(User $user, Asset $asset, AssetFile $file): StreamedResponse
    {
        if ((int) $file->asset_id !== (int) $asset->id) {
            throw new RuntimeException('file_asset_mismatch');
        }

        $entitlement = $this->entitlements->check($user, $asset);

        if (! $entitlement->allowed) {
            throw new RuntimeException($entitlement->reason);
        }

        if ($file->scan_status !== 'clean') {
            throw new RuntimeException('file_not_available');
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw new RuntimeException('file_missing');
        }

        $this->record($user, $asset, $file, $entitlement->accessType);

        return $disk->download($file->path, $file->original_name);
    }

    /**
     * Records the download and credits the contributor.
     *
     * Wrapped in a transaction with the counter bump so a failure cannot
     * leave a download recorded but the contributor uncredited — that
     * discrepancy is invisible until a payout is disputed.
     */
    private function record(User $user, Asset $asset, AssetFile $file, string $accessType): void
    {
        DB::transaction(function () use ($user, $asset, $file, $accessType) {
            $download = Download::create([
                'user_id'         => $user->id,
                'asset_id'        => $asset->id,
                'asset_file_id'   => $file->id,
                'contributor_id'  => $asset->contributor_id,
                'access_type'     => $accessType,
                'license_id'      => $asset->license_id,
                'ip_address'      => request()->ip(),
                'user_agent'      => substr((string) request()->userAgent(), 0, 500),
            ]);

            DB::table('assets')->where('id', $asset->id)->increment('downloads_count');
            DB::table('contributors')->where('id', $asset->contributor_id)->increment('downloads_count');

            // Subscription downloads accrue royalties; a free download does
            // not. Purchase royalties are booked at checkout, not here, so
            // they are not double-counted on every re-download.
            if ($accessType === 'subscription') {
                event(new \App\Events\SubscriptionDownloadRecorded($download));
            }
        });
    }

    private function primarySourceFile(Asset $asset): ?AssetFile
    {
        return AssetFile::where('asset_id', $asset->id)
            ->where('is_source', true)
            ->where('scan_status', 'clean')
            ->orderBy('id')
            ->first();
    }
}
