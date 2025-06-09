<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    /**
     * Display the links for a given city.
     */
    public function show($city)
    {
        $cityObj = City::where('code', $city)->firstOrFail();

        // Map city code to English name
        $englishNames = [
            'homs' => 'Homs',
            'damascus' => 'Damascus',
            'aleppo' => 'Aleppo',
            'latakia' => 'Latakia',
            'mouzeina' => 'Mouzeina',
            'tartus' => 'Tartus',
        ];

        // Inject English name dynamically
        $cityObj->name_en = $englishNames[$city] ?? $cityObj->name;

        return view('social_links.show', [
            'city' => $cityObj
        ]);
    }
}
