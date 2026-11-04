<?php

namespace App\Http\Controllers\Farma;

use App\Exceptions\ApiOperationException;
use App\Exceptions\ApiSessionExpiredException;
use App\Http\Controllers\Controller;
use App\Services\Api\ProductApiService;
use App\Services\Images\Base64ImageSanitizer;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ProductPreviewController extends Controller
{
    public function __invoke(
        int $product,
        ProductApiService $api,
        Base64ImageSanitizer $sanitizer,
    ): Response {
        try {
            $image = $sanitizer->validateBase64($api->image($product));
            $binary = base64_decode($image['base64'], true);
        } catch (ApiSessionExpiredException) {
            return response('', 401);
        } catch (ApiOperationException|InvalidArgumentException) {
            return response('', 404);
        }

        if (! is_string($binary)) {
            return response('', 404);
        }

        return response($binary, 200, [
            'Content-Type' => $image['mime'],
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
