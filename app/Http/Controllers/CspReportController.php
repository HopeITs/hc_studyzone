<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class CspReportController extends Controller
{
    /**
     * Sink for the browser's Content-Security-Policy-Report-Only reports
     * (see SecurityHeaders middleware). Unauthenticated by nature — browsers
     * send these with no session context — so it's rate-limited instead.
     */
    public function store(Request $request): Response
    {
        $report = $request->input('csp-report') ?? json_decode($request->getContent(), true);

        if (is_array($report)) {
            Log::warning('CSP report-only violation', $report);
        }

        return response()->noContent();
    }
}
