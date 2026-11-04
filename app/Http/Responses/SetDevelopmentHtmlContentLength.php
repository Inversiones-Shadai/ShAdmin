<?php

namespace App\Http\Responses;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SetDevelopmentHtmlContentLength
{
    public function __invoke(RequestHandled $event): void
    {
        $request = $event->request;
        $response = $event->response;
        $server = (string) $request->server('SERVER_SOFTWARE');

        if (! app()->isLocal() || ! str_starts_with($server, 'PHP/') || ! str_contains($server, '(Development Server)')
            || ! $request->isMethod('GET') || $response->getStatusCode() !== 200
            || $response instanceof StreamedResponse || $response instanceof BinaryFileResponse
            || ! str_contains(strtolower((string) $response->headers->get('Content-Type')), 'text/html')
            || $response->headers->has('Content-Encoding') || $response->headers->has('Transfer-Encoding')
            || headers_sent()) {
            return;
        }

        // Encoders can still change the bytes after the response leaves Laravel.
        $compression = strtolower(trim((string) ini_get('zlib.output_compression')));
        if (! in_array($compression, ['', '0', 'off'], true) || (string) ini_get('output_handler') !== '') {
            return;
        }

        foreach (ob_list_handlers() as $handler) {
            if (preg_match('/gzhandler|mb_output_handler|iconv|brotli/i', $handler)) {
                return;
            }
        }

        $content = $response->getContent();
        if (is_string($content)) {
            $response->headers->set('Content-Length', (string) strlen($content));
        }
    }
}
