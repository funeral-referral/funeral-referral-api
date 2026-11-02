<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Outreach\Unsubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnsubscribeController extends Controller
{
    /**
     * Public and signed. A GET from the link in the email and the one-click POST
     * from a mail client both unsubscribe straight away, with no login or form.
     */
    public function __invoke(Request $request, Campaign $campaign, Unsubscriber $unsubscriber): Response
    {
        $unsubscriber->unsubscribe($campaign);

        if ($request->isMethod('post')) {
            return response('', 200);
        }

        $company = e(config('outreach.company'));

        return response(
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Unsubscribed</title></head>'
            .'<body style="font-family: system-ui, sans-serif; max-width: 32rem; margin: 4rem auto; padding: 0 1rem;">'
            ."<h1>You are unsubscribed</h1><p>{$company} will not email this address again.</p></body></html>",
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
