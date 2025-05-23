<?php

namespace App\Http\Controllers;

use App\Models\SocialLink;
use Illuminate\Http\Request;

class SocialLinkController extends Controller
{
    /**
     * Display the links for a given city.
     */
    public function show($city)
    {
        $link = SocialLink::where('city', $city)->firstOrFail();

        return view('social_links.show', [
            'link' => $link
        ]);
    }
}

