<?php

namespace Iagofelicio\GeoAnalytics\Models;

use DateTime;
use ErrorException;
use FilesystemIterator;
use Illuminate\Support\Number;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Filesystem\Filesystem;

class GeoAnalytics
{

    /**
     * Create required application directories
     *
     * @return boolean
     */
    public static function init_directories()
    {
        if(!file_exists(geo_storage_path())){
            mkdir(geo_storage_path(), 0777, true);
            geo_permissions_path(geo_storage_path());
        }
        if(!file_exists(geo_storage_path('requests'))){
            mkdir(geo_storage_path('requests'), 0777, true);
            geo_permissions_path(geo_storage_path('requests'));
        }
        if(!file_exists(geo_storage_path('requests/unprocessed'))){
            mkdir(geo_storage_path('requests/unprocessed'), 0777, true);
            geo_permissions_path(geo_storage_path('requests/unprocessed'));
        }
        if(!file_exists(geo_storage_path('requests/processed'))){
            mkdir(geo_storage_path('requests/processed'), 0777, true);
            geo_permissions_path(geo_storage_path('requests/processed'));
        }
        if(!file_exists(geo_storage_path('requests/ips'))){
            mkdir(geo_storage_path('requests/ips'), 0777, true);
            geo_permissions_path(geo_storage_path('requests/ips'));
        }
        if(!file_exists(geo_storage_path('requests/analytics'))){
            mkdir(geo_storage_path('requests/analytics'), 0777, true);
            geo_permissions_path(geo_storage_path('requests/analytics'));
        }
    }

    /**
     * Get directory size in bytes
     *
     * @return boolean
     */
    public static function getDirectorySize($path){

        $bytestotal = 0;
        $path = realpath($path);
        if($path!==false && $path!='' && file_exists($path)){
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                $bytestotal += $object->getSize();
            }
        }
        return $bytestotal;
    }

    /**
     * Update application cache
     *
     * @return boolean
     */
    public static function update_cache()
    {
        $filesystem = new Filesystem();

        #Update log cache
        $helper = new FilesystemIterator(geo_storage_path('requests/processed'), FilesystemIterator::SKIP_DOTS);
        $nProcessed = iterator_count($helper);
        $filesProcessedSize = GeoAnalytics::getDirectorySize(geo_storage_path('requests/processed'));

        $helper = new FilesystemIterator(geo_storage_path('requests/unprocessed'), FilesystemIterator::SKIP_DOTS);
        $nUnprocessed = iterator_count($helper);
        $filesUnprocessedSize = GeoAnalytics::getDirectorySize(geo_storage_path('requests/unprocessed'));

        $helper = new FilesystemIterator(geo_storage_path('requests/ips'), FilesystemIterator::SKIP_DOTS);
        $nIps = iterator_count($helper);
        $filesIpsSize = GeoAnalytics::getDirectorySize(geo_storage_path('requests/ips'));

        $filesApp = $filesystem->files(geo_storage_path('requests/analytics'));
        $filesAppSize = 0;
        $nApp = count($filesApp);
        foreach($filesApp as $fileA){
            $filesAppSize += $filesystem->size($fileA);
        }
        if(file_exists(geo_storage_path("requests/db.yaml"))){
            $filesAppSize += $filesystem->size(geo_storage_path("requests/db.yaml"));
        }

        $cache = [
            'latest_update' => (new DateTime())->format('Y-m-d H:i:s'),
            'cache_ips' => $filesIpsSize,
            'cache_ips_records' => $nIps,
            'cache_ips_str' => Number::fileSize($filesIpsSize, precision: 2),
            'cache_log' => $filesProcessedSize + $filesUnprocessedSize,
            'cache_log_records' => $nProcessed +  $nUnprocessed,
            'cache_log_str' => Number::fileSize(($filesProcessedSize + $filesUnprocessedSize), precision: 2),
            'cache_app' => $filesAppSize,
            'cache_app_records' => $nApp,
            'cache_app_str' => Number::fileSize($filesAppSize, precision: 2),
        ];

        file_put_contents(geo_storage_path('cache.yaml'), Yaml::dump($cache));
        geo_permissions_path(geo_storage_path('cache.yaml'));
    }

    /**
     * Get application profile
     *
     * @return boolean
     */
    public static function get_profile()
    {
        return Yaml::parseFile(geo_storage_path('profile.yaml'));
    }

    /**
     * Initiate application profile
     *
     * @return boolean
     */
    public static function init_profile()
    {
        $ipInfo = json_decode(file_get_contents('https://api.myip.com'),true);

        $currentProfile = [];
        if(file_exists(geo_storage_path('profile.yaml'))){
            $currentProfile = Yaml::parseFile(geo_storage_path('profile.yaml'));
        }

        if(!file_exists(geo_storage_path('enabled')) && !file_exists(geo_storage_path('disabled'))){
            $status = true;
            file_put_contents(geo_storage_path('enabled'),'Tracking requests');
            geo_permissions_path(geo_storage_path('enabled'));
        } elseif(file_exists(geo_storage_path('enabled'))){
            $status = true;
        } elseif(file_exists(geo_storage_path('disabled'))){
            $status = false;
        } else {
            $status = true;
            file_put_contents(geo_storage_path('enabled'),'Tracking requests');
            geo_permissions_path(geo_storage_path('enabled'));
        }

        if(!file_exists(geo_storage_path('requests/blacklist.yaml'))){
            file_put_contents(geo_storage_path('requests/blacklist.yaml'),Yaml::dump([]));
        }

        $profile = [
            'status' => $status,
            'ip_provider' => $currentProfile['ip_provider'] ?? ['alias' => 'ip-api.com','token' => ''],
            'store_ips' => $currentProfile['store_ips'] ?? true,
            'my_ip' => $ipInfo['ip'] ?? 'unknown',
            'visible_codes' => $currentProfile['visible_codes'] ?? [200]
        ];

        file_put_contents(geo_storage_path('profile.yaml'), Yaml::dump($profile));
        geo_permissions_path(geo_storage_path('profile.yaml'));
        geo_permissions_path(geo_storage_path('requests/blacklist.yaml'));
    }

    /**
     * Get IP Geo Information
     *
     * @return boolean
     */
    public static function geoIp($ip, $forceProvider = false)
    {
        $profile = Yaml::parseFile(geo_storage_path('profile.yaml'));
        $provider = $profile['ip_provider']['alias'];
        $token = $profile['ip_provider']['token'];
        $storeIps = $profile['store_ips'];
        $ipKeys = ['country','countryCode','city','lat','lon'];

        $ipPath = geo_storage_path("requests/ips/$ip.yaml");
        if(!$forceProvider && $storeIps && file_exists($ipPath)){
            $ipDetails = Yaml::parseFile($ipPath);
            return $ipDetails;
        }

        if($ip == 'unknown'){
            $ipDetails = [
                "country" => "unknown",
                "countryCode" => "unknown",
                "city" => "unknown",
                "lat" => "unknown",
                "lon" => "unknown",
                "provider" => $provider,
            ];
        } else {
            if($provider == "ip-api.com"){
                try{
                    $ipDetailsFull = json_decode(file_get_contents("http://ip-api.com/json/$ip"), true);
                    if(!isset($ipDetailsFull['country'])){
                        sleep(3);
                        $ipDetailsFull = json_decode(file_get_contents("http://ip-api.com/json/$ip"), true);
                    }
                    foreach($ipKeys as $key) $ipDetails[$key] = $ipDetailsFull[$key] ?? 'unknown';
                } catch(Exception $e) {
                    foreach($ipKeys as $key) $ipDetails[$key] = 'unknown';
                } catch(ErrorException $e) {
                    foreach($ipKeys as $key) $ipDetails[$key] = 'unknown';
                }
            }
            if($provider == "apiip.net"){
                try{
                    $content = file_get_contents("https://apiip.net/api/check?ip=$ip&accessKey=$token");
                    $ipDetailsFull = json_decode($content, true);
                    if(!isset($ipDetailsFull["countryName"])){
                        sleep(3);
                        $content = file_get_contents("https://apiip.net/api/check?ip=$ip&accessKey=$token");
                        $ipDetailsFull = json_decode($content, true);
                    }
                    foreach($ipKeys as $key){
                        if($key == "country"){
                            $ipDetails[$key] = $ipDetailsFull["countryName"] ?? 'unknown';
                        } elseif($key == "region"){
                            $ipDetails[$key] = $ipDetailsFull["regionCode"] ?? 'unknown';
                        } elseif($key == "lat"){
                            $ipDetails[$key] = $ipDetailsFull["latitude"] ?? 'unknown';
                        } elseif($key == "lon"){
                            $ipDetails[$key] = $ipDetailsFull["longitude"] ?? 'unknown';
                        } else {
                            $ipDetails[$key] = $ipDetailsFull[$key] ?? 'unknown';
                        }
                    }
                } catch(ErrorException $e) {
                    foreach($ipKeys as $key) $ipDetails[$key] = 'unknown';
                } catch(Exception $e) {
                    foreach($ipKeys as $key) $ipDetails[$key] = 'unknown';
                }
            }
            if($provider == "ip2location.io"){
                try{
                    $content = file_get_contents("https://api.ip2location.io/?key=$token&ip=$ip");
                    $ipDetailsFull = json_decode($content, true);
                    if(!isset($ipDetailsFull["country_name"])){
                        sleep(3);
                        $content = file_get_contents("https://api.ip2location.io/?key=$token&ip=$ip");
                        $ipDetailsFull = json_decode($content, true);
                    }
                    foreach($ipKeys as $key){
                        if($key == "country"){
                            $ipDetails[$key] = $ipDetailsFull["country_name"] ?? 'unknown';
                        } elseif($key == "countryCode"){
                            $ipDetails[$key] = $ipDetailsFull["country_code"] ?? 'unknown';
                        } elseif($key == "lat"){
                            $ipDetails[$key] = $ipDetailsFull["latitude"] ?? 'unknown';
                        } elseif($key == "lon"){
                            $ipDetails[$key] = $ipDetailsFull["longitude"] ?? 'unknown';
                        } elseif($key == "city"){
                            $ipDetails[$key] = $ipDetailsFull["city_name"] ?? 'unknown';
                        } else {
                            $ipDetails[$key] = $ipDetailsFull[$key] ?? 'unknown';
                        }
                    }
                } catch(ErrorException $e) {
                    foreach($ipKeys as $key) $ipDetails[$key] = 'unknown';
                } catch(Exception $e) {
                    foreach($ipKeys as $key) $ipDetails[$key] = 'unknown';
                }
            }

            $ipDetails['provider'] = $provider;
            $ipDetails['country'] = GeoAnalytics::getCountryName($ipDetails['country']);

            if($storeIps) file_put_contents($ipPath, Yaml::dump($ipDetails));
        }
        return $ipDetails;
    }

    /**
     * Get proper country name
     *
     * @return boolean
     */
    public static function getCountryName($alias, $aliases = null)
    {
        if($aliases == null){
            $countriesAliases = json_decode(file_get_contents(__DIR__ . "/../../assets/countries-aliases.json"),true);
        } else {
            $countriesAliases = $aliases;
        }
        if(!in_array($alias,array_keys($countriesAliases))){
            foreach($countriesAliases as $label => $alternatives){
                if(in_array($alias,$alternatives)){
                    return $label;
                }
            }
        }
        return $alias;
    }

    /**
     * Update Analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalytics($model,$ipDetails,$contents,$dataset,$forceTimerange,$operation)
    {

        foreach(['all','today','week','month'] as $timerange){
            if($forceTimerange != null && $timerange != $forceTimerange) continue;

            if($model == "ips") $dataset = GeoAnalytics::updateAnalyticsIps($timerange,$ipDetails,$contents,$dataset,true,$operation);
            if($model == "dates") $dataset = GeoAnalytics::updateAnalyticsDates($timerange,$ipDetails,$contents,$dataset,true,$operation);
            if($model == "countries") $dataset = GeoAnalytics::updateAnalyticsCountries($timerange,$ipDetails,$contents,$dataset,true,$operation);
            if($model == "cities") $dataset = GeoAnalytics::updateAnalyticsCities($timerange,$ipDetails,$contents,$dataset,true,$operation);
            if($model == "uris") $dataset = GeoAnalytics::updateAnalyticsUris($timerange,$ipDetails,$contents,$dataset,true,$operation);
            if($model == "geojson") GeoAnalytics::updateAnalyticsMap($timerange,$ipDetails,$contents,$dataset,true,$operation);
        }
        return $dataset;
    }

    /**
     * Update IP analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalyticsIps($timerange,$ipDetails,$contents,$dataset,$newRequests,$operation)
    {

        if($newRequests){
            $date = DateTime::createFromFormat('U', $contents['t']);
            $dateStr = $contents['dateStr'];
            $ip = $contents['ip'];
            $uri = $contents['uri'];
            $status = $contents['status'];
            $statusList = $contents['statusList'];

            if($timerange == "today"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ip]['requests'][$status])){
                            $dataset[$timerange]['data'][$ip]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ip]['location']['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ip]['location']['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ip]['location']['city'] = $ipDetails['city'];
                            $dataset[$timerange]['data'][$ip]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ip]['requests'][$status]) && $dataset[$timerange]['data'][$ip]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ip]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ip])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ip],"requests.*")) == 0){
                            unset($dataset[$timerange]['data'][$ip]);
                        }
                    }
                }
            } elseif($timerange == "week"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ip]['requests'][$status])){
                            $dataset[$timerange]['data'][$ip]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ip]['location']['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ip]['location']['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ip]['location']['city'] = $ipDetails['city'];
                            $dataset[$timerange]['data'][$ip]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ip]['requests'][$status]) && $dataset[$timerange]['data'][$ip]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ip]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ip])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ip],"requests.*")) == 0){
                            unset($dataset[$timerange]['data'][$ip]);
                        }
                    }
                }
            } elseif($timerange == "month"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ip]['requests'][$status])){
                            $dataset[$timerange]['data'][$ip]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ip]['location']['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ip]['location']['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ip]['location']['city'] = $ipDetails['city'];
                            $dataset[$timerange]['data'][$ip]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ip]['requests'][$status]) && $dataset[$timerange]['data'][$ip]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ip]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ip])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ip],"requests.*")) == 0){
                            unset($dataset[$timerange]['data'][$ip]);
                        }
                    }
                }
            } else {
                if(isset($dataset[$timerange]['dates']['start'])){
                    if($dataset[$timerange]['dates']['start'] > $dateStr){
                        $dataset[$timerange]['dates']['start'] = $dateStr;
                    }
                } else {
                    $dataset[$timerange]['dates']['start'] = $dateStr;
                }
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format("Y-m-d H:i:s");

                if($operation == 'add' || $operation == null){
                    if(isset($dataset[$timerange]['data'][$ip]['requests'][$status])){
                        $dataset[$timerange]['data'][$ip]['requests'][$status]++;
                    } else {
                        $dataset[$timerange]['data'][$ip]['location']['countryCode'] = $ipDetails['countryCode'];
                        $dataset[$timerange]['data'][$ip]['location']['country'] = $ipDetails['country'];
                        $dataset[$timerange]['data'][$ip]['location']['city'] = $ipDetails['city'];
                        $dataset[$timerange]['data'][$ip]['requests'][$status] = 1;
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ip]['requests'][$status]) && $dataset[$timerange]['data'][$ip]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ip]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ip])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ip],"requests.*")) == 0){
                            unset($dataset[$timerange]['data'][$ip]);
                        }
                    }
                }
            }

            return $dataset;
        } else {
        }
    }

    /**
     * Update IP analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalyticsDates($timerange,$ipDetails,$contents,$dataset,$newRequests,$operation)
    {
        if($newRequests){
            $date = DateTime::createFromFormat('U', $contents['t']);
            $dateStr = (new DateTime($contents['dateStr']))->format("Y-m-d H:i:00");
            $ip = $contents['ip'];
            $uri = $contents['uri'];
            $status = $contents['status'];
            $statusList = $contents['statusList'];

            if(!isset($dataset[$timerange]['available_status_code'])){
                $dataset[$timerange]['available_status_code'][] = $status;
            } else {
                if(!in_array($status,$dataset[$timerange]['available_status_code'])){
                    $dataset[$timerange]['available_status_code'][] = $status;
                }
            }
            if($timerange == "today"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']++;
                        } else {
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] = 1;
                        }
                        if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']])){
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]++;
                        } else {
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                        }

                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$dateStr]);
                        }
                    }
                }
            } elseif($timerange == "week"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']++;
                        } else {
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] = 1;
                        }
                        if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']])){
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]++;
                        } else {
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$dateStr]);
                        }
                    }
                }
            } elseif($timerange == "month"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']++;
                        } else {
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] = 1;
                        }
                        if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']])){
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]++;
                        } else {
                            $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$dateStr]);
                        }
                    }
                }
            } else {
                if(isset($dataset[$timerange]['dates']['start'])){
                    if($dataset[$timerange]['dates']['start'] > $dateStr){
                        $dataset[$timerange]['dates']['start'] = $dateStr;
                    }
                } else {
                    $dataset[$timerange]['dates']['start'] = $dateStr;
                }
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format("Y-m-d H:i:s");

                if($operation == 'add' || $operation == null){
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']++;
                    } else {
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] = 1;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']])){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]++;
                    } else {
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$dateStr]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }

                    if(isset($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$dateStr]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$dateStr]);
                        }
                    }
                }
            }

            return $dataset;
        } else {
        }

    }

    /**
     * Update Countries analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalyticsCountries($timerange,$ipDetails,$contents,$dataset,$newRequests,$operation)
    {
        if($newRequests){
            $date = DateTime::createFromFormat('U', $contents['t']);
            $dateStr = (new DateTime($contents['dateStr']))->format("Y-m-d H:i:00");
            $ip = $contents['ip'];
            $uri = $contents['uri'];
            $status = $contents['status'];
            $statusList = $contents['statusList'];

            if($timerange == "today"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status])){
                            $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ipDetails['country']]['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ipDetails['country']]['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['country']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['country']]);
                        }
                    }
                }
            } elseif($timerange == "week"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status])){
                            $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ipDetails['country']]['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ipDetails['country']]['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['country']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['country']]);
                        }
                    }
                }
            } elseif($timerange == "month"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status])){
                            $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ipDetails['country']]['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ipDetails['country']]['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['country']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['country']]);
                        }
                    }
                }
            } else {
                if(isset($dataset[$timerange]['dates']['start'])){
                    if($dataset[$timerange]['dates']['start'] > $dateStr){
                        $dataset[$timerange]['dates']['start'] = $dateStr;
                    }
                } else {
                    $dataset[$timerange]['dates']['start'] = $dateStr;
                }
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format("Y-m-d H:i:s");
                if($operation == 'add' || $operation == null){
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status])){
                        $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]++;
                    } else {
                        $dataset[$timerange]['data'][$ipDetails['country']]['countryCode'] = $ipDetails['countryCode'];
                        $dataset[$timerange]['data'][$ipDetails['country']]['country'] = $ipDetails['country'];
                        $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] = 1;
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['country']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['country']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['country']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['country']]);
                        }
                    }
                }
            }
            return $dataset;
        } else {
        }

    }

    /**
     * Update Countries analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalyticsCities($timerange,$ipDetails,$contents,$dataset,$newRequests,$operation)
    {
        if($newRequests){
            $date = DateTime::createFromFormat('U', $contents['t']);
            $dateStr = (new DateTime($contents['dateStr']))->format("Y-m-d H:i:00");
            $ip = $contents['ip'];
            $uri = $contents['uri'];
            $status = $contents['status'];
            $statusList = $contents['statusList'];

            if($timerange == "today"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status])){
                            $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ipDetails['city']]['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['city'] = $ipDetails['city'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['lat'] = $ipDetails['lat'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['lon'] = $ipDetails['lon'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['city']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['city']]);
                        }
                    }
                }
            } elseif($timerange == "week"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status])){
                            $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ipDetails['city']]['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['city'] = $ipDetails['city'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['lat'] = $ipDetails['lat'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['lon'] = $ipDetails['lon'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['city']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['city']]);
                        }
                    }
                }
            } elseif($timerange == "month"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status])){
                            $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]++;
                        } else {
                            $dataset[$timerange]['data'][$ipDetails['city']]['countryCode'] = $ipDetails['countryCode'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['country'] = $ipDetails['country'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['city'] = $ipDetails['city'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['lat'] = $ipDetails['lat'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['lon'] = $ipDetails['lon'];
                            $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['city']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['city']]);
                        }
                    }
                }
            } else {
                if(isset($dataset[$timerange]['dates']['start'])){
                    if($dataset[$timerange]['dates']['start'] > $dateStr){
                        $dataset[$timerange]['dates']['start'] = $dateStr;
                    }
                } else {
                    $dataset[$timerange]['dates']['start'] = $dateStr;
                }
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format("Y-m-d H:i:s");
                if($operation == 'add' || $operation == null){
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status])){
                        $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]++;
                    } else {
                        $dataset[$timerange]['data'][$ipDetails['city']]['countryCode'] = $ipDetails['countryCode'];
                        $dataset[$timerange]['data'][$ipDetails['city']]['country'] = $ipDetails['country'];
                        $dataset[$timerange]['data'][$ipDetails['city']]['city'] = $ipDetails['city'];
                        $dataset[$timerange]['data'][$ipDetails['city']]['lat'] = $ipDetails['lat'];
                        $dataset[$timerange]['data'][$ipDetails['city']]['lon'] = $ipDetails['lon'];
                        $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] = 1;
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]) && $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status] > 0){
                        $dataset[$timerange]['data'][$ipDetails['city']]['requests'][$status]--;
                    }
                    if(isset($dataset[$timerange]['data'][$ipDetails['city']])){
                        if(array_sum(data_get($dataset[$timerange]['data'][$ipDetails['city']],"*.*")) == 0){
                            unset($dataset[$timerange]['data'][$ipDetails['city']]);
                        }
                    }
                }
            }
            return $dataset;
        } else {
        }

    }


    /**
     * Update Countries analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalyticsUris($timerange,$ipDetails,$contents,$dataset,$newRequests,$operation)
    {
        if($newRequests){
            $date = DateTime::createFromFormat('U', $contents['t']);
            $dateStr = (new DateTime($contents['dateStr']))->format("Y-m-d H:i:00");
            $ip = $contents['ip'];
            $uri = $contents['uri'];
            $status = $contents['status'];
            $statusList = $contents['statusList'];

            if($timerange == "today"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['code'] = $status;
                        if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['total']++;
                        } else {
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] = 1;
                        }

                        if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]++;
                        } else {
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total']) && $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] == 0){
                            unset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]);
                        }
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$uri]);
                        }
                    }
                }
            } elseif($timerange == "week"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['code'] = $status;
                        if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['total']++;
                        } else {
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] = 1;
                        }

                        if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]++;
                        } else {
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total']) && $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] == 0){
                            unset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]);
                        }
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$uri]);
                        }
                    }
                }
            } elseif($timerange == "month"){
                $dataset[$timerange]['dates']['start'] = (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00');
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format('Y-m-d 23:59:59');
                if($operation == 'add' || $operation == null){
                    if(($dateStr >= $dataset[$timerange]['dates']['start'] && $dateStr <= $dataset[$timerange]['dates']['end'])){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['code'] = $status;
                        if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['total']++;
                        } else {
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] = 1;
                        }

                        if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]++;
                        } else {
                            $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                        }
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total']) && $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] == 0){
                            unset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]);
                        }
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$uri]);
                        }
                    }
                }
            } else {
                if(isset($dataset[$timerange]['dates']['start'])){
                    if($dataset[$timerange]['dates']['start'] > $dateStr){
                        $dataset[$timerange]['dates']['start'] = $dateStr;
                    }
                } else {
                    $dataset[$timerange]['dates']['start'] = $dateStr;
                }
                $dataset[$timerange]['dates']['end'] = (new DateTime())->format("Y-m-d H:i:s");

                $dataset[$timerange]['data'][$uri]['requests'][$status]['code'] = $status;
                if($operation == 'add' || $operation == null){
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['total']++;
                    } else {
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] = 1;
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]++;
                    } else {
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] = 1;
                    }
                } else {
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total']) && $dataset[$timerange]['data'][$uri]['requests'][$status]['total'] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['total']--;
                    }
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]) && $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] > 0){
                        $dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]--;
                    }

                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']] == 0){
                            unset($dataset[$timerange]['data'][$uri]['requests'][$status]['countries'][$ipDetails['country']]);
                        }
                    }
                    if(isset($dataset[$timerange]['data'][$uri]['requests'][$status]['total'])){
                        if($dataset[$timerange]['data'][$uri]['requests'][$status]['total'] == 0){
                            unset($dataset[$timerange]['data'][$uri]);
                        }
                    }
                }
            }

            return $dataset;
        } else {
        }

    }


    /**
     * Update Geojson analytics with new request information
     *
     * @return boolean
     */
    public static function updateAnalyticsMap($timerange,$ipDetails,$contents,$dataset,$newRequests,$operation)
    {
        $filesystem = new Filesystem();

        if($newRequests){
            $statusList = $contents['statusList'];
            $basePath = __DIR__ . "/../../assets/natural-earth-data-countries-raw.geojson";
            $geojsonPath = geo_storage_path("requests/analytics/countries-$timerange.geojson");
            $geojson = json_decode(file_get_contents($basePath),true);
            foreach($geojson['features'] as $key => $list){
                $geoCountryCode = $list['properties']['countryCode'];
                $geoCountryName = $list['properties']['country'];
                if(isset($dataset[$timerange]['data'])){
                    if(in_array($geoCountryName,array_keys($dataset[$timerange]['data']))){
                        $geojson['features'][$key]['properties']['requests_total'] = array_sum(data_get($dataset[$timerange]['data'][$geoCountryName],"requests.*"));
                        foreach($statusList as $code){
                            $geojson['features'][$key]['properties']['requests'][$code] = $dataset[$timerange]['data'][$geoCountryName]['requests'][$code] ?? 0;
                        }
                    } else {
                        $geojson['features'][$key]['properties']['requests_total'] = 0;
                        foreach($statusList as $code){
                            $geojson['features'][$key]['properties']['requests'][$code] = 0;
                        }
                    }
                }
            }
            file_put_contents($geojsonPath, json_encode($geojson,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            geo_permissions_path($geojsonPath);
        }

        return true;
    }

    public static function factory($range)
    {
        $uris200 = ["/","/home","/blog"];
        $uris404 = ["/error1","/error2","/error3"];

        $counter = 1;
        $listCountries = [];
        $limit = random_int(1000,2500);
        foreach(range(1,$range) as $n){

            //Wait api-ip.com limits
            if($counter % 40 == 0){
                echo "\nSleeping for 1 minute";
                sleep(60);
            }

            //Build random IP
            $aux_1 = random_int(1,255);
            $aux_2 = random_int(1,255);
            $aux_3 = random_int(1,255);
            $aux_4 = random_int(1,255);
            $ip = "$aux_1.$aux_2.$aux_3.$aux_4";

            //Test IP
            $ipInfo = GeoAnalytics::geoIp($ip);
            if($ipInfo['country'] == "unknown" && $ipInfo['city'] == "unknown"){
                echo "\n[$counter] IP ($ip): Invalid";
                $counter++;
                continue;
            }

            if(isset($listCountries[$ipInfo['country']])){
                if($listCountries[$ipInfo['country']] > $limit){
                    continue;
                }
                $listCountries[$ipInfo['country']]++;
            } else {
                $listCountries[$ipInfo['country']] = 1;
            }

            foreach(range(1,random_int(1,120)) as $n2){
                //Status code and URIs
                if(($n2 % 2 == 0) || ($n2 % 2 == 3)){
                    $statusCode = 200;
                } else {
                    $statusCode = random_int(0,1) == 0 ? 200 : 404;
                }

                if($statusCode == 200){
                    $uri = $uris200[random_int(0,2)];
                } else {
                    $uri = $uris404[random_int(0,2)];
                }


                //Timestamp
                $addMin = random_int(0,59);
                $addHour = random_int(0,23);
                $addDays = random_int(-90,-1);
                $dt = (new DateTime())->modify("$addDays days, $addHour hours, $addMin minutes")->format("Y-m-d H:i:s");

                $yaml = [];
                $yaml['t'] = (new DateTime($dt))->getTimestamp();
                $yaml['ip'] = $ip;
                $yaml['uri'] = $uri;
                $yaml['status'] = $statusCode;

                //Write fake request file
                $filePath = geo_storage_path("requests/unprocessed/$ip-$statusCode-$n-$n2.yaml");
                file_put_contents($filePath, Yaml::dump($yaml));
                geo_permissions_path($filePath);
                echo "\n[$counter][$n2] Status Code: $statusCode | DateTime: $dt | IP ($ip): ". $ipInfo['country'];
            }
            $counter++;
        }
        echo "\n\nFactory finished.\n\n";
        exit();
    }
}
