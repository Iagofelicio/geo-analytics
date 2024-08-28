<?php

namespace Iagofelicio\GeoAnalytics\Middleware;

use Closure;
use DateTime;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\Session;


class GeoAnalyticsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {

        $response = $next($request);
        if(!file_exists(geo_storage_path('tracking'))){
            return $response;
        }
        $server = $request->server->all();
        $timestamp = (new DateTime())->getTimestamp();

        try {
            $uri = $server['REQUEST_URI'];

            if(isset($server['HTTP_CF_CONNECTING_IP'])){
                $ip = $server['HTTP_CF_CONNECTING_IP'];

            } elseif(isset($server['HTTP_X_FORWARDED_FOR'])) {
                $ip = $server['HTTP_X_FORWARDED_FOR'];

            } elseif(isset($server['REMOTE_ADDR'])) {
                $ip = $server['REMOTE_ADDR'];
            } else {
                $ip = "unknown";
            }
        } catch(Exception $e) {
            $ip = "unknown";
        }

        $log = geo_storage_path('requests/unprocessed/'. $timestamp . '.' . str_replace('.','_',$ip) . '.' . Str::random(15) .'.yaml');
        file_put_contents($log, Yaml::dump([
            't' => $timestamp,
            'ip' => $ip,
            'uri' => $uri ?? 'unknown',
            'status' => $response->status()
        ]));

        return $response;
    }
}
