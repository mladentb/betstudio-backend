<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UpdateLastActivity
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $request->user()->update([
                'last_active_at' => Carbon::now(),
            ]);
        }

        return $next($request);
    }
}
