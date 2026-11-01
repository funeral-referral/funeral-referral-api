<?php

namespace App\Http\Controllers;

use App\Http\Resources\CrawlRunResource;
use App\Models\CrawlRun;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CrawlRunController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $runs = CrawlRun::query()
            ->with('source:id,key,name,type,state_code')
            ->latest('id')
            ->paginate(25);

        return CrawlRunResource::collection($runs);
    }
}
