<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Str;

function probe(string $url, int $durationSeconds = 60, int $timeout = 15): array
{
    $timings = [
        'ttfb_ms'   => null,
        'total_ms'  => null,
    ];

    $response = null;

    // Request + timings
    $response = Http::withHeaders([
            'User-Agent' => 'DownLinkNotifyer/1.0 (+https://downlink.techvince.com)'
        ])
        ->timeout($timeout)
        ->withOptions([
            'allow_redirects' => true,
            'on_stats' => function (\GuzzleHttp\TransferStats $stats) use (&$timings) {
                $handler = $stats->getHandlerStats(); // cURL stats
                // TTFB: namelookup + connect + appconnect + pretransfer + starttransfer
                if (isset($handler['starttransfer_time'])) {
                    $timings['ttfb_ms']  = (int) round($handler['starttransfer_time'] * 1000);
                }
                $timings['total_ms'] = (int) round($stats->getTransferTime() * 1000);
            },
        ])
        ->get($url);


            // new retrive
                $psi_api_url = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
                $psi_response = Http::get($psi_api_url, [
                    'url'      => $url, // User ka URL
                    'strategy' => 'desktop', // Ya 'mobile'
                    // Aapko API key ki zaroorat nahi hai, agar requests limited ho
                ]);
                $performanceScore = null;
                $seoScore = null;
                $lcp = null;
                if ($psi_response->successful()) {
                    $data = $psi_response->json();

                    // Data nikalna
                    $performanceScore = $data['lighthouseResult']['categories']['performance']['score'] * 100;
                    $seoScore = $data['lighthouseResult']['categories']['seo']['score'] * 100;
                    
                    // Core Web Vitals
                    $lcp = $data['lighthouseResult']['audits']['largest-contentful-paint']['displayValue'];

                    // ... baaqi sab metrics bhi yahan se mil jaengi.
                }
            // new retrive end





    $statusCode   = $response->status();
    $isUp         = ($statusCode >= 200 && $statusCode < 400);
    $finalUrl     = (string) ($response->effectiveUri() ?? $url);
    $contentType  = $response->header('content-type');
    $encoding     = $response->header('content-encoding');
    $html         = (Str::startsWith($contentType, 'text/html')) ? $response->body() : '';
    $htmlBytes    = strlen($html);

    // Approx total asset weight (HEAD on top assets, max 15)
    [$assetBytes, $topAssets] = estimateAssetsWeight($finalUrl, $html, 15, $timeout);

    // SSL info (only for https)
    $sslDaysLeft = null;
    if (Str::startsWith($finalUrl, 'https://')) {
        $sslDaysLeft = getSslDaysLeft(parse_url($finalUrl, PHP_URL_HOST));
    }

    $now = now();
    $nextCheckAt = $now->copy()->addSeconds($durationSeconds);

    return [
        // Status block
        'status'        => $isUp ? 'up' : 'down',
        'status_code'   => $statusCode,
        'last_checked_at' => $now->toDateTimeString(),
        'next_check_at' => $nextCheckAt->toDateTimeString(),

        // Speed
        'response_time_ms' => $timings['total_ms'],
        'ttfb_ms'          => $timings['ttfb_ms'],

        // Page size
        'html_bytes'       => $htmlBytes,
        'assets_bytes'     => $assetBytes,
        'top_assets'       => $topAssets, // [{url, bytes}] (where available)

        // Security
        'ssl_days_left'    => $sslDaysLeft,

        // Raw extras
        'final_url'        => $finalUrl,
        'content_type'     => $contentType,
        'content_encoding' => $encoding,

        'performanceScore' => $performanceScore,
        'seoScore' => $seoScore,
        'lcp' => $lcp
    ];
}

function estimateAssetsWeight(string $baseUrl, string $html, int $limit = 15, int $timeout = 10): array
{
    if (!$html) return [0, []];

    $assetUrls = extractAssetUrls($baseUrl, $html);
    if (empty($assetUrls)) return [0, []];

    $assetUrls = array_slice($assetUrls, 0, $limit);

    $total = 0;
    $top   = [];

    foreach ($assetUrls as $aurl) {
        try {
            // Prefer HEAD for speed; fall back to GET if needed
            $head = Http::timeout($timeout)->head($aurl);
            $len  = (int) ($head->header('content-length') ?? 0);

            if ($len === 0 && $head->failed()) {
                // Some servers block HEAD; do a lightweight GET
                $get = Http::timeout($timeout)->withHeaders(['Range' => 'bytes=0-0'])->get($aurl);
                $len = (int) ($get->header('content-length') ?? 0);
            }

            if ($len > 0) {
                $total += $len;
                $top[] = ['url' => $aurl, 'bytes' => $len];
            }
        } catch (\Throwable $e) {
            // ignore single-asset failures
        }
    }

    // Sort biggest first
    usort($top, fn($a, $b) => $b['bytes'] <=> $a['bytes']);

    return [$total, $top];
}

function extractAssetUrls(string $baseUrl, string $html): array
{
    $urls = [];

    // Quick regex for src/href of common assets
    preg_match_all('/<(img|script)\s[^>]*src=["\']([^"\']+)["\']/i', $html, $m1);
    preg_match_all('/<link\s[^>]*rel=["\'](?:stylesheet|preload)["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m2);

    $candidates = array_merge($m1[2] ?? [], $m2[1] ?? []);
    $candidates = array_unique($candidates);

    // Normalize to absolute
    $base = parse_url($baseUrl);
    $scheme = $base['scheme'] ?? 'https';
    $host   = $base['host']   ?? '';

    foreach ($candidates as $u) {
        if (Str::startsWith($u, '//')) {
            $urls[] = $scheme . ':' . $u;
        } elseif (Str::startsWith($u, ['http://', 'https://'])) {
            $urls[] = $u;
        } elseif (Str::startsWith($u, '/')) {
            $urls[] = $scheme . '://' . $host . $u;
        } else {
            // relative path
            $basePath = rtrim(dirname($base['path'] ?? '/'), '/');
            $urls[] = $scheme . '://' . $host . ($basePath ? $basePath . '/' : '/') . $u;
        }
    }

    return array_values(array_filter($urls));
}

function getSslDaysLeft(?string $host): ?int
{
    if (!$host) return null;

    try {
        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'SNI_enabled'       => true,
                'peer_name'         => $host,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$client) return null;

        $params = stream_context_get_params($client);
        if (!isset($params['options']['ssl']['peer_certificate'])) return null;

        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        if (!isset($cert['validTo_time_t'])) return null;

        $days = (int) floor(($cert['validTo_time_t'] - time()) / 86400);
        return $days;
    } catch (\Throwable $e) {
        return null;
    }
}