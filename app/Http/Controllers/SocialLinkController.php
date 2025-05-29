<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\SocialLink;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    /**
     * Display the links for a given city.
     */
    public function show($city)
    {
        $cityObj = City::where('code', $city)->firstOrFail();

        return view('social_links.show', [
            'city' => $cityObj
        ]);
    }
}


