<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetFile;
use App\Services\Download\SecureDownloadService;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class DownloadController extends Controller
{
    public function __construct(
        private readonly SecureDownloadService $downloads,
    ) {}

    /** Mints a short-lived signed URL for the client to follow. */
    public function request(Request $request, Asset $asset)
    {
        try {
            $url = $this->downloads->createSignedUrl($request->user(), $asset);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $this->messageFor($e->getMessage()),
                'reason'  => $e->getMessage(),
            ], $this->statusFor($e->getMessage()));
        }

        return response()->json(['url' => $url]);
    }

    /**
     * Serves the file. The 'signed' middleware validates TTL + signature;
     * this method additionally confirms the signature belongs to the
     * authenticated user, so a leaked URL cannot be replayed by someone else.
     */
    public function serve(Request $request, Asset $asset, AssetFile $file)
    {
        if ((int) $request->query('u') !== (int) $request->user()->id) {
            abort(Response::HTTP_FORBIDDEN, 'This download link was issued to a different account.');
        }

        try {
            return $this->downloads->serve($request->user(), $asset, $file);
        } catch (RuntimeException $e) {
            abort($this->statusFor($e->getMessage()), $this->messageFor($e->getMessage()));
        }
    }

    private function statusFor(string $reason): int
    {
        return match ($reason) {
            'authentication_required' => Response::HTTP_UNAUTHORIZED,
            'asset_unavailable', 'no_downloadable_file', 'file_missing' => Response::HTTP_NOT_FOUND,
            'daily_limit_reached', 'plan_limit_reached' => Response::HTTP_TOO_MANY_REQUESTS,
            default => Response::HTTP_FORBIDDEN,
        };
    }

    /** Reasons are mapped to plain language — support tickets depend on this. */
    private function messageFor(string $reason): string
    {
        return match ($reason) {
            'authentication_required' => 'Please sign in to download this asset.',
            'asset_unavailable'       => 'This asset is not available.',
            'purchase_required'       => 'This is a premium asset. Purchase it or subscribe to download.',
            'subscription_required'   => 'This asset is available to subscribers only.',
            'plan_excludes_premium'   => 'Your current plan does not include premium assets.',
            'plan_excludes_asset_type'=> 'Your current plan does not include this asset type.',
            'daily_limit_reached'     => 'You have reached your daily download limit.',
            'plan_limit_reached'      => 'You have reached your plan download limit.',
            'file_not_available'      => 'This file is still being processed. Please try again shortly.',
            'no_downloadable_file'    => 'No downloadable file is attached to this asset.',
            'file_missing'            => 'The file could not be found.',
            default                   => 'You are not permitted to download this asset.',
        };
    }
}
