<?php

namespace App\Http\Controllers\Intake;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AddressSuggestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());
        if (strlen($query) < 3) {
            return response()->json(['data' => []]);
        }

        $response = Http::timeout(6)
            ->withHeaders(['User-Agent' => 'ZookaHQ/1.0 (address autocomplete)'])
            ->get('https://photon.komoot.io/api/', [
                'q' => $query,
                'limit' => 6,
                'lang' => 'en',
            ]);

        if (! $response->successful()) {
            return response()->json(['data' => []]);
        }

        $suggestions = collect($response->json('features') ?? [])
            ->map(function ($feature) {
                $props = $feature['properties'] ?? [];
                $line1 = trim(($props['housenumber'] ?? '').' '.($props['street'] ?? ''));
                if ($line1 === '') {
                    $line1 = $props['name'] ?? '';
                }
                $city = $props['city'] ?? $props['town'] ?? $props['village'] ?? $props['district'] ?? '';
                $state = $this->stateCode($props['state'] ?? '');
                $zip = $props['postcode'] ?? '';

                $label = collect([$line1, $city, trim($state.' '.$zip)])
                    ->filter(fn ($part) => $part !== '')
                    ->implode(', ');

                if ($label === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'address' => $line1,
                    'address_2' => '',
                    'city' => $city,
                    'state' => $state,
                    'zip' => $zip,
                ];
            })
            ->filter()
            ->unique('label')
            ->values();

        return response()->json(['data' => $suggestions]);
    }

    protected function stateCode(string $state): string
    {
        $state = trim($state);
        if ($state === '' || strlen($state) <= 3) {
            return $state;
        }

        $map = [
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR',
            'california' => 'CA', 'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE',
            'district of columbia' => 'DC', 'florida' => 'FL', 'georgia' => 'GA', 'hawaii' => 'HI',
            'idaho' => 'ID', 'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA',
            'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME',
            'maryland' => 'MD', 'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN',
            'mississippi' => 'MS', 'missouri' => 'MO', 'montana' => 'MT', 'nebraska' => 'NE',
            'nevada' => 'NV', 'new hampshire' => 'NH', 'new jersey' => 'NJ', 'new mexico' => 'NM',
            'new york' => 'NY', 'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH',
            'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI',
            'south carolina' => 'SC', 'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX',
            'utah' => 'UT', 'vermont' => 'VT', 'virginia' => 'VA', 'washington' => 'WA',
            'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY',
        ];

        return $map[strtolower($state)] ?? $state;
    }
}
