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
            $ip = $this->getIp();
        } catch(Exception $e) {
            $ip = "unknown";
        }

        if($ip == "127.0.0.1" || $ip == "localhost"){
            $profile = Yaml::parseFile(geo_storage_path('profile.yaml'));
            $ip = $profile['my_ip'] ?? 'unknown';
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

    public function getIp(){
        foreach (array('HTTP_CF_CONNECTING_IP','HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
            if (array_key_exists($key, $_SERVER) === true){
                foreach (explode(',', $_SERVER[$key]) as $ip){
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                        return $ip;
                    }
                }
            }
        }
        return request()->ip();
    }
}
