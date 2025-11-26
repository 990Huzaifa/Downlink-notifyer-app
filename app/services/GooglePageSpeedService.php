<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GooglePageSpeedService
{
    /**
     * The base URL for the PageSpeed Insights API.
     */
    protected const PSI_BASE_URL = 'https://www.googleapis.com/pagespeedwidgets/v5/runPagespeed';

    /**
     * The base URL for the Chrome UX Report API.
     */
    protected const CRUX_BASE_URL = 'https://chromeuxreport.googleapis.com/v1/records';

    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.api_key');
    }

    public function getPageSpeedData(string $url, string $strategy = 'desktop'): ?array
    {

        $categories = [
            'SEO', 
            'PERFORMANCE', 
            'BEST_PRACTICES', 
            'ACCESSIBILITY',
            // 'PWA' is also available if needed
        ];
        try {
            $response = Http::get(self::PSI_BASE_URL, [
                'url' => $url,
                'strategy' => $strategy,
                'key' => $this->apiKey,
                'category' => $categories,
                // Add fields to limit the data returned for performance
                'fields' => 'loadingExperience,lighthouseResult.categories',
            ]);

            // Check if the request was successful (HTTP status 200)
            if ($response->successful()) {
                return $response->json();
            }

            // Log error or handle failure case
            logger()->error('PageSpeed Insights API call failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            logger()->error('PageSpeed Insights API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getCruxData(string $originOrUrl): ?array
    {
        try {
            $endpoint = self::CRUX_BASE_URL . ':queryRecord?key=' . $this->apiKey;
            $body = [
                'origin' => $originOrUrl,
            ];
            $response = Http::post($endpoint, $body);
            if ($response->successful()) {
                return $response->json();
            }

            logger()->error('CrUX API call failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            logger()->error('CrUX API exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}