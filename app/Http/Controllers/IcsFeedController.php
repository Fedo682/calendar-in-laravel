<?php

namespace App\Http\Controllers;

use App\Support\Calendar\CalendarFeedTokenService;
use App\Support\Calendar\IcsFeedBuilder;
use App\Support\Calendar\IcsFeedCache;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IcsFeedController extends Controller
{
    public function __construct(
        private readonly CalendarFeedTokenService $tokens,
        private readonly IcsFeedBuilder $builder,
        private readonly IcsFeedCache $cache,
    ) {}

    public function show(Request $request, string $token): Response
    {
        $record = $this->tokens->resolve($token);

        // Invalid or revoked both 404 - never confirm a token ever existed.
        if ($record === null) {
            abort(404);
        }

        $this->tokens->touch($record, $request);

        $fingerprint = $this->cache->fingerprint($record->user);
        $etag = '"'.$fingerprint.'"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response($this->builder->build($record->user), 200)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename=calendar.ics')
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age='.config('calendar.ics.cache_ttl'))
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
