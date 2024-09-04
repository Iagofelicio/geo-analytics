<?php

namespace Iagofelicio\GeoAnalytics\Controllers;

use DateTime;
use DatePeriod;
use DateInterval;
use DivisionByZeroError;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Statamic\Facades\CP\Toast;
use Composer\InstalledVersions;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Filesystem\Filesystem;
use Statamic\Http\Controllers\Controller;
use Iagofelicio\GeoAnalytics\Models\GeoAnalytics;

class GeoAnalyticsController extends Controller
{

    public function map($timerange)
    {
        return view("geo-analytics::map",['timerange' => $timerange]);
    }

    public function get_breakpoints($max_value, $num_breakpoints) {
        $interval = $max_value / $num_breakpoints;
        $breakpoints = [];
        for ($i = 0; $i <= $num_breakpoints; $i++) {
            $breakpoints[] = intval(round($i * $interval));
        }
        return $breakpoints;
    }

    public function geojsonDates($timerange)
    {
        if(!file_exists(geo_storage_path("requests/analytics/countries.json"))){
            return ['dates' => null];
        }
        $processedJsonPath = geo_storage_path("requests/analytics/countries.json");
        $dataJson = json_decode(file_get_contents($processedJsonPath),true);
        if(!isset($dataJson[$timerange]['data'])){
            return ['dates' => null];
        }
        return [
            "dates" => $dataJson[$timerange]['dates']
        ];
    }

    public function profile()
    {
        if(!file_exists(geo_storage_path("profile.yaml"))){
            return ['profile' => null];
        }
        $profile = Yaml::parseFile(geo_storage_path("profile.yaml"));

        $packageLatestVersionInfo = json_decode(file_get_contents("https://repo.packagist.org/p2/iagofelicio/geo-analytics.json"), true);
        $packageLatestVersion = $packageLatestVersionInfo['packages']['iagofelicio/geo-analytics'][0]['version'];
        $packageCurrentVersion = InstalledVersions::getPrettyVersion('iagofelicio/geo-analytics');

        $profile['version']['current'] = $packageCurrentVersion;
        $profile['version']['latest'] = $packageLatestVersion;
        $profile['version']['updated'] = ($packageCurrentVersion == $packageLatestVersion);

        return ["profile" => $profile];
    }

    public function cache()
    {
        if(!file_exists(geo_storage_path("cache.yaml"))){
            return ['cache' => null];
        }
        $cache = Yaml::parseFile(geo_storage_path("cache.yaml"));

        return ["cache" => $cache];
    }

    public function geojsonData($timerange)
    {
        if(!file_exists(geo_storage_path("requests/analytics/countries.json")) || !file_exists(geo_storage_path("requests/analytics/countries-$timerange.geojson"))){
            return ["geojson" => null];
        }

        $processedGeojsonPath = geo_storage_path("requests/analytics/countries-$timerange.geojson");
        $processedJsonPath = geo_storage_path("requests/analytics/countries.json");
        $dataJson = json_decode(file_get_contents($processedJsonPath),true);
        if(!isset($dataJson[$timerange]['data'])){
            return ['geojson' => null];
        }
        $maxVistits = 0;
        foreach($dataJson[$timerange]['data'] as $countryName => $countryInfo){
            $totalRequests = array_sum(data_get($countryInfo,"requests.*"));
            if($totalRequests > $maxVistits){
                $maxVistits = $totalRequests;
            }
        }
        $breakpoints = $this->get_breakpoints((ceil($maxVistits / 10) * 10), 8);
        unset($breakpoints[8]);

        if(!file_exists($processedGeojsonPath)){
            return ["geojson" => []];
        }

        return [
            "geojson" => json_decode(file_get_contents($processedGeojsonPath),true),
            "breakpoints" => $breakpoints,
            "data" => $dataJson[$timerange]['dates']
        ];
    }

    public function cardsData()
    {

        if(!file_exists(geo_storage_path("requests/analytics/countries.json")) && !file_exists(geo_storage_path("requests/analytics/uris.json")) && !file_exists(geo_storage_path("requests/analytics/ips.json")) && !file_exists(geo_storage_path("requests/analytics/cities.json")) ){
            return ['cards' => null];
        }

        // Cards
        $countriesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/countries.json")),true);
        $nCountries = count(array_unique(data_get($countriesRaw['all']['data'],"*.country")));


        $citiesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/cities.json")),true);
        $nCities = count(array_unique(data_get($citiesRaw['all']['data'],"*.city")));

        $requestsAll = array_sum(data_get($countriesRaw['all']['data'],"*.requests.*"));
        $requestsSuccess = array_sum(data_get($countriesRaw['all']['data'],"*.requests.200"));

        $rankCountry = [];
        foreach($countriesRaw['all']['data'] as $countryName => $data){
            if(!isset($rankCountry[$data['country']])){
                $rankCountry[$data['country']] = array_sum($data['requests']);
            } else {
                $rankCountry[$data['country']] += array_sum($data['requests']);
            }
        }

        $rankCity = [];
        foreach($citiesRaw['all']['data'] as $cityName => $data){
            if(!isset($rankCity[$data['city']])){
                $rankCity[$data['city']] = array_sum($data['requests']);
            } else {
                $rankCity[$data['city']] += array_sum($data['requests']);
            }
        }


        arsort($rankCountry);
        arsort($rankCity);
        reset($rankCountry);
        $topCountry = key($rankCountry);

        reset($rankCity);
        $topCity = key($rankCity);

        $urisRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/uris.json")),true);
        $rankUri = [];
        $nUris = count(array_keys($urisRaw['all']['data']));
        foreach($urisRaw['all']['data'] as $uri => $data){
            if(!isset($rankUri[$uri])){
                $rankUri[$uri] = array_sum(data_get($data,"requests.*.total"));
            } else {
                $rankUri[$uri] += array_sum(data_get($data,"requests.*.total"));
            }
        }
        arsort($rankUri);
        reset($rankUri);
        $topUri = key($rankUri);

        $ipsRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/ips.json")),true);
        $nIps = count(array_keys($ipsRaw['all']['data']));
        $rankIp = [];
        foreach($ipsRaw['all']['data'] as $ip => $data){
            if(!isset($rankIp[$ip])){
                $rankIp[$ip] = array_sum($data['requests']);
            } else {
                $rankIp[$ip] += array_sum($data['requests']);
            }
        }
        arsort($rankIp);
        reset($rankIp);
        $topIp = key($rankIp);

        $cardsInfo = [
            "unique_countries" => $nCountries,
            "unique_cities" => $nCities,
            "requests_all" => $requestsAll,
            "requests_success" => $requestsSuccess,
            "requests_all_abbreviated" => Number::abbreviate($requestsAll, precision: 0),
            "requests_success_abbreviated" => Number::abbreviate($requestsSuccess),
            "top_country" => $topCountry,
            "top_city" => $topCity,
            "top_uri" => $topUri,
            "unique_uris" => $nUris,
            "unique_ips" => $nIps,
            "top_ip" => $topIp
        ];
        return [
            'cards' => $cardsInfo
        ];


    }

    public function citiesData($timerange)
    {

        if(!file_exists(geo_storage_path("requests/analytics/cities.json"))){
            return [
                'cities' => null,
                'download' => false
            ];
        }

        $citiesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/cities.json")),true);
        if(!isset($citiesRaw[$timerange]['data'])){
            return [
                'cities' => null,
                'download' => $citiesRaw['all']['data'] ? true : false
            ];
        }

        // City/Countries table
        $citiesTable = [];
        $columnsCities = [];
        $columnsCities[] = ['title' => "City"];
        $columnsCities[] = ['title' => "Country"];
        $columnsCities[] = ['title' => 'Total'];
        $columnsCities[] = ['title' => 'Total
            <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                <span class="text-gray-800 dark:text-dark-150 font-medium">200</span>
            </span>
        '];
        $columnsCities[] = ['title' => 'Success Rate'];

        foreach($citiesRaw[$timerange]['data'] as $cityName => $data){
            $citiesTable[] = [
                $cityName,
                $data['country'] . '</span>' . '
                    <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                        <span class="text-gray-800 dark:text-dark-150 font-medium">'. $data['countryCode'] .'</span>
                    </span>
                ',
                array_sum($data['requests']),
                $data['requests']['200'] ?? 0,
                isset($data['requests']['200']) ? Number::percentage(($data['requests']['200'] / array_sum($data['requests']))*100, precision: 2) : Number::percentage(0, precision: 2)
            ];
        }

        $citiesInfo = [
            "columns" => $columnsCities,
            "data" => $citiesTable,
            "sorting" => [[2,'desc']]
        ];

        return [
            'cities' => $citiesInfo,
            'dates' => $citiesRaw[$timerange]['dates'],
            'download' => $citiesRaw['all']['data'] ? true : false
        ];
    }

    public function changeIpStatus(Request $request)
    {
        $ip = $request['ip'];
        $status = $request['status'];
        $blacklist = Yaml::parseFile(geo_storage_path("requests/blacklist.yaml"));
        if($status == "block"){
            if(isset($blacklist)){
                if(!in_array($ip,$blacklist)){
                    $blacklist[] = $ip;
                }
            } else {
                $blacklist[] = $ip;
            }
        } elseif($status == "track"){
            if(isset($blacklist)){
                if(in_array($ip,$blacklist)){
                    $key = array_search($ip,$blacklist);
                    unset($blacklist[$key]);
                }
            }
        } else {
            return false;
        }
        file_put_contents(geo_storage_path('requests/blacklist.yaml'), Yaml::dump($blacklist));

        return true;
    }

    public function ipsData($timerange)
    {

        if(!file_exists(geo_storage_path("requests/analytics/ips.json"))){
            return [
                'dates' => null,
                'download' => false
            ];
        }

        $ipsRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/ips.json")),true);
        if(!isset($ipsRaw[$timerange]['data'])){
            return [
                'dates' => null,
                'download' => $ipsRaw['all']['data'] ? true : false
            ];
        }

        $blacklist = Yaml::parseFile(geo_storage_path("requests/blacklist.yaml"));

        $ipsTable = [];
        $columnsIps = [];
        $columnsIps[] = ['title' => "IP"];
        $columnsIps[] = ['title' => 'Location'];
        $columnsIps[] = ['title' => 'Total'];
        $columnsIps[] = ['title' => 'Total
            <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                <span class="text-gray-800 dark:text-dark-150 font-medium">200</span>
            </span>
        '];
        $columnsIps[] = ['title' => 'Success Rate'];
        $columnsIps[] = ['title' => 'Status'];
        $columnsIps[] = ['title' => 'Actions'];

        $ipsTable = [];
        foreach($ipsRaw[$timerange]['data'] as $ip => $data){
            try{
                $calc = (($data['requests']['200'] ?? 0) / array_sum($data['requests']))*100;
            } catch (DivisionByZeroError $e) {
                $calc = 0;
            }

            if(isset($blacklist)){
                if(!in_array($ip,$blacklist)){
                    $status = '
                        <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm" style="background-color: #b9e0ba">
                            <span class="text-gray-800 font-medium">Monitoring</span>
                        </span>
                    ';
                    $btn1 = '
                        <button onclick="changeIpStatus(\''.$ip.'\',\'block\')" class="btn btn-sm text-[10px]">
                            Disable
                        </button>
                    ';

                } else {
                    $status = '
                        <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm" style="background-color: #ec9f9f">
                            <span class="text-gray-800 font-medium">Blocked</span>
                        </span>
                    ';
                    $btn1 = '
                        <button onclick="changeIpStatus(\''.$ip.'\',\'track\')" class="btn btn-sm text-[10px]">
                            Enable
                        </button>
                    ';

                }
            } else {
                $status = '
                    <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm" style="background-color: #b9e0ba">
                        <span class="text-gray-800 font-medium">Monitoring</span>
                    </span>
                ';
                $btn1 = '
                    <button onclick="changeIpStatus(\''.$ip.'\',\'block\')" class="btn btn-sm text-[10px]">
                        Disable
                    </button>
                ';
            }
            $ipsTable[$ip] = [
                $ip,
                $data['location']['city'] . '<br></span>' . '
                    <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                        <span class="text-gray-800 dark:text-dark-150 font-medium">'. $data['location']['country'] .'</span>
                    </span>
                ',
                array_sum($data['requests']),
                $data['requests']['200'] ?? 0,
                Number::percentage($calc, precision: 2),
                $status,
                "$btn1"
            ];
        }

        $ipsInfo = [
            "columns" => $columnsIps,
            "data" => array_values($ipsTable),
            "sorting" => [[2,'desc']]
        ];
        return [
            'ips' => $ipsInfo,
            'dates' => $ipsRaw[$timerange]['dates'],
            'download' => $ipsRaw['all']['data'] ? true : false
        ];
    }

    public function datesData($timerange)
    {

        if(!file_exists(geo_storage_path("requests/analytics/dates.json"))){
            return [
                'dates' => null,
                'download' => false
            ];
        }

        $datesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/dates.json")),true);
        if(!isset($datesRaw[$timerange]['data'])){
            return [
                'dates' => null,
                'download' => $datesRaw['all']['data'] ? true : false
            ];
        }

        $datesTable = [];
        $columnsDate = [];
        $columnsDate[] = ['title' => "Datetime"];
        $columnsDate[] = ['title' => 'Total'];
        $columnsDate[] = ['title' => 'Total
            <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                <span class="text-gray-800 dark:text-dark-150 font-medium">200</span>
            </span>
        '];
        $columnsDate[] = ['title' => 'Success Rate'];

        $datasetHourly = [];
        foreach($datesRaw[$timerange]['data'] as $date => $data){
            $tmpDate = (new DateTime($date))->format("Y-m-d H:00:00");
            if(isset($datasetHourly[$tmpDate])){
                $datasetHourly[$tmpDate]['total'] = $datasetHourly[$tmpDate]['total'] + array_sum(data_get($data,"requests.*.total"));
                $datasetHourly[$tmpDate]['200'] = $datasetHourly[$tmpDate]['200'] + ($data['requests']['200']['total'] ?? 0);
            } else {
                $datasetHourly[$tmpDate]['total'] = array_sum(data_get($data,"requests.*.total"));
                $datasetHourly[$tmpDate]['200'] = ($data['requests']['200']['total'] ?? 0);
            }
        }
        ksort($datasetHourly);
        foreach($datasetHourly as $date => $values){
            try{
                $calc = ($values[200] / $values['total'])*100;
            } catch (DivisionByZeroError $e) {
                $calc = 0;
            }
            $datesTable[$date] = [
                '<span class="text-[12px]"><b>' . (new DateTime($date))->format("Y-m-d") .'</b>&nbsp;<span class="text-[10px]">' . (new DateTime($date))->format("H:00")  .' to ' . (new DateTime($date))->modify("+59 minutes")->format("H:59") . '</span></span>',
                $values['total'],
                $values[200],
                Number::percentage($calc, precision: 2)
            ];
        }

        $datesInfo = [
            "columns" => $columnsDate,
            "data" => array_values($datesTable),
            "sorting" => [[0,'desc']]
        ];
        return [
            'dates' => $datesInfo,
            'datesRange' => $datesRaw[$timerange]['dates'],
            'download' => $datesRaw['all']['data'] ? true : false
        ];
    }

    public function timeseriesData(Request $request)
    {
        $dtFilterStart = $request['startFilter'];
        $dtFilterEnd = $request['endFilter'];
        if(!file_exists(geo_storage_path("requests/analytics/dates.json"))){
            return ['dates' => null];
        }

        $datesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/dates.json")),true);
        krsort($datesRaw['all']['data']);

        $tmpDates = [];
        $allDates = [];
        $codes = $datesRaw['all']['available_status_code'];
        foreach($codes as $code){
            foreach($datesRaw['all']['data'] as $datetime => $info){
                $auxDate = (new DateTime($datetime))->format("Y-m-d");
                if(!in_array($auxDate,$allDates)){
                    $allDates[] = $auxDate;
                }
                if(isset($tmpDates[$code][$auxDate])){
                    $tmpDates[$code][$auxDate] += $info['requests'][$code]['total'] ?? 0;
                } else {
                    $tmpDates[$code][$auxDate] = $info['requests'][$code]['total'] ?? 0;
                }
            }
            ksort($tmpDates[$code]);
        }
        $allDates = array_values($allDates);

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod((new DateTime($datesRaw['all']['dates']['start'])), $interval, (new DateTime($datesRaw['all']['dates']['end']))->modify("+1 day"));
        foreach($period as $tmpDate) {
            //Filter data
            if($dtFilterStart == "default" && $dtFilterEnd != "default") {
                if($tmpDate->format("Y-m-d") > $dtFilterEnd){
                    if(in_array($tmpDate->format("Y-m-d"),$allDates)){
                        $keyToRemove = array_search($tmpDate->format("Y-m-d"), $allDates);
                        if($keyToRemove !== false){
                            unset($allDates[$keyToRemove]);
                            $allDates = array_values($allDates);
                        }
                    }
                } else {
                    if(!in_array($tmpDate->format("Y-m-d"),$allDates)) $allDates[] = $tmpDate->format("Y-m-d");
                }
            } elseif($dtFilterStart != "default" && $dtFilterEnd == "default") {
                if($tmpDate->format("Y-m-d") < $dtFilterStart){
                    if(in_array($tmpDate->format("Y-m-d"),$allDates)){
                        $keyToRemove = array_search($tmpDate->format("Y-m-d"), $allDates);
                        if($keyToRemove !== false){
                            unset($allDates[$keyToRemove]);
                            $allDates = array_values($allDates);
                        }
                    }
                } else {
                    if(!in_array($tmpDate->format("Y-m-d"),$allDates)) $allDates[] = $tmpDate->format("Y-m-d");
                }
            } elseif($dtFilterStart != "default" && $dtFilterEnd != "default") {

                if($tmpDate->format("Y-m-d") < $dtFilterStart || $tmpDate->format("Y-m-d") > $dtFilterEnd){
                    if(in_array($tmpDate->format("Y-m-d"),$allDates)){
                        $keyToRemove = array_search($tmpDate->format("Y-m-d"), $allDates);
                        if($keyToRemove !== false){
                            unset($allDates[$keyToRemove]);
                            $allDates = array_values($allDates);
                        }
                    }
                } else {
                    if(!in_array($tmpDate->format("Y-m-d"),$allDates)) $allDates[] = $tmpDate->format("Y-m-d");
                }
            }
        }

        foreach($tmpDates as $tmpC => $tmpData){
            foreach($tmpData as $tmpD => $tmpValue){
                if(!in_array($tmpD,$allDates)){
                    unset($tmpDates[$tmpC][$tmpD]);
                }
            }
        }

        sort($allDates);
        foreach($codes as $code){
            foreach($allDates as $dt){
                if(!isset($tmpDates[$code][$dt])){
                    $tmpDates[$code][$dt] = 0;
                }
            }
            ksort($tmpDates[$code]);
        }
        $dataset = [];
        $idx = 0;

        foreach($tmpDates as $code => $info){
            foreach($info as $datetime => $total){
                $dataset["datasets"][$idx]['data'][] = $total;
                $dataset["datasets"][$idx]['label'] = "HTTP $code";
                if($code == 200){
                    $dataset["datasets"][$idx]['backgroundColor'] = "rgba(87, 151, 90, 0.5)";
                    $dataset["datasets"][$idx]['borderColor'] = "rgba(87, 151, 90)";
                } elseif($code == 404) {
                    $dataset["datasets"][$idx]['backgroundColor'] = "rgba(198, 67, 67, 0.5)";
                    $dataset["datasets"][$idx]['borderColor'] = "rgba(198, 67, 67)";
                } else {
                    $r = random_int(0,255);
                    $g = random_int(0,255);
                    $b = random_int(0,255);
                    $dataset["datasets"][$idx]['backgroundColor'] = "rgba($r, $g, $b, 0.5)";
                    $dataset["datasets"][$idx]['borderColor'] = "rgba($r, $g, $b)";
                }
            }
            $idx++;
        }
        $dataset["labels"] = $allDates;
        return ["traces" => $dataset, "dates" => $datesRaw['all']['dates']];
    }

    public function uriData($timerange)
    {

        if(!file_exists(geo_storage_path("requests/analytics/uris.json"))){
            return [
                'uris' => null,
                'download' => false
            ];
        }

        $urisRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/uris.json")),true);
        if(!isset($urisRaw[$timerange]['data'])){
            return [
                'uris' => null,
                'download' => $urisRaw['all']['data'] ? true : false
            ];
        }
        $urisTable = [];
        $columnsUri = [];
        $columnsUri[] = ['title' => "URI"];
        $columnsUri[] = ['title' => "Unique Countries"];
        $columnsUri[] = ['title' => "Total"];
        foreach($urisRaw[$timerange]['data'] as $uri => $data){
            foreach($data['requests'] as $code => $info){
                $urisTable[] = [
                    '<span class="text-[14px">' . $uri . '</span>
                    <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                        <span class="text-gray-800 dark:text-dark-150 font-medium">'. $code .'</span>
                    </span>',
                    '<span class="inline-flex items-baseline">' .
                        count(array_keys($info['countries'])) . '&nbsp;&nbsp;
                            <div class="inline h-4 w-4 rtl:ml-4 ltr:mr-4 text-gray-800 dark:text-dark-175" title="'. implode(", ", array_keys($info['countries']) ) .'" >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="11.985" r="11.5" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"></circle><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" d="M.673 9.985h6.084a3 3 0 0 1 2.122.878L10 11.984a3 3 0 0 1 .121 4.115l-1.363 1.533A3 3 0 0 0 8 19.625v3.145M20.261 3.985h-5.8a2.25 2.25 0 0 0 0 4.5h.432a3 3 0 0 1 2.5 1.335l2.218 3.329a3 3 0 0 0 2.5 1.336h1.121"></path></svg>
                            </div>
                        </span>',
                    "<b>".$info['total']."</b>"
                ];
            }
        }
        $urisInfo = [
            "columns" => $columnsUri,
            "data" => $urisTable,
            "sorting" => [[2,'desc']]
        ];
        return [
            'uris' => $urisInfo,
            'dates' => $urisRaw[$timerange]['dates'],
            'download' => $urisRaw['all']['data'] ? true : false
        ];

    }

    public function download($datasetName)
    {
        $contents = "";

        if($datasetName == "uri"){
            $urisRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/uris.json")),true);
            $contentArr = [];
            $contentArr[] = "uri,code,requests,unique_countries,list_countries";
            foreach($urisRaw['all']['data'] as $uri => $requests){
                foreach($requests['requests'] as $code => $info){
                    $total = $info['total'];
                    $countries = count(array_keys($info['countries']));
                    $contentArr[] = "$uri,$code,$total,$countries,".implode("; ",array_keys($info['countries']));
                }
            }
            $contents = implode("\n",$contentArr);
        } elseif($datasetName == "dates") {
            $datesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/dates.json")),true);
            krsort($datesRaw['all']['data']);

            $tmpDates = [];
            $allDates = [];
            $codes = $datesRaw['all']['available_status_code'];
            foreach($codes as $code){
                foreach($datesRaw['all']['data'] as $datetime => $info){
                    $auxDate = (new DateTime($datetime))->format("Y-m-d");
                    if(!in_array($auxDate,$allDates)){
                        $allDates[] = $auxDate;
                    }
                    if(isset($tmpDates[$code][$auxDate])){
                        $tmpDates[$code][$auxDate] += $info['requests'][$code]['total'] ?? 0;
                    } else {
                        $tmpDates[$code][$auxDate] = $info['requests'][$code]['total'] ?? 0;
                    }
                }
                ksort($tmpDates[$code]);
            }
            $allDates = array_values($allDates);

            sort($allDates);
            foreach($codes as $code){
                foreach($allDates as $dt){
                    if(!isset($tmpDates[$code][$dt])){
                        $tmpDates[$code][$dt] = 0;
                    }
                }
                ksort($tmpDates[$code]);
            }
            $dataset = [];
            $header = [];
            $header[] = "date";
            $idx = 0;
            foreach($tmpDates as $code => $info){
                $header[] = "http-$code";
                foreach($info as $datetime => $total){
                    $dataset["datasets"][$idx]['data'][] = $total;
                    $dataset["datasets"][$idx]['label'] = "HTTP $code";
                }
                $idx++;
            }
            $header[] = "total";
            $dataset["labels"] = $allDates;
            $contentArr[] = implode(',',$header);
            foreach($dataset["labels"] as $key => $date){
                $line = [];
                $line[] = $date;
                $sum = 0;
                foreach($codes as $idx => $code){
                    $line[] = $dataset["datasets"][$idx]['data'][$key];
                    $sum += $dataset["datasets"][$idx]['data'][$key];
                }
                $line[] = $sum;
                $contentArr[] = implode(',',$line);
            }
            $contents = implode("\n",$contentArr);
        } elseif($datasetName == "cities") {
            $citiesRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/cities.json")),true);
            $contentArr = [];
            $contentArr[] = "city,country,countryCode,total_requests,total_requests_success,success_rate";
            foreach($citiesRaw['all']['data'] as $city => $data){
                $country = $data['country'];
                $countryCode = $data['countryCode'];
                $totalRequests = array_sum($data['requests']);
                $totalRequestsSucess = $data['requests']['200'] ?? 0;
                $successRate = ($totalRequestsSucess > 0) ? ($totalRequestsSucess / array_sum($data['requests'])) : 0;
                $contentArr[] = "$city,$country,$countryCode,$totalRequests,$totalRequestsSucess,$successRate";
            }
            $contents = implode("\n",$contentArr);

        } elseif($datasetName == "ips") {
            if(file_exists(geo_storage_path('requests/blacklist.yaml'))){
                $blacklist = Yaml::parseFile(geo_storage_path('requests/blacklist.yaml'));
            } else {
                $blacklist = [];
            }
            $ipsRaw = json_decode(file_get_contents(geo_storage_path("requests/analytics/ips.json")),true);
            $contentArr = [];
            $contentArr[] = "ip,city,country,total_requests,total_requests_success,success_rate,status";
            foreach($ipsRaw['all']['data'] as $ip => $data){
                $city = $data['location']['city'];
                $country = $data['location']['country'];
                $totalRequests = array_sum($data['requests']);
                $totalRequestsSucess = $data['requests']['200'] ?? 0;
                $successRate = ($totalRequestsSucess > 0) ? ($totalRequestsSucess / array_sum($data['requests'])) : 0;
                $status = (in_array($ip,$blacklist)) ? "blocked" : "monitoring";
                $contentArr[] = "$ip,$city,$country,$totalRequests,$totalRequestsSucess,$successRate,$status";
            }
            $contents = implode("\n",$contentArr);
        } elseif($datasetName == "full"){
            $db = Yaml::parseFile(geo_storage_path("requests/db.yaml"));
            $contentArr = [];
            $contentHeader = explode(',',$db['header']);
            $addHeader = true;
            foreach($db['data'] as $info){
                foreach($info as $datetime => $request){
                    $requestArr = explode(',',$request);
                    $ipDetails = GeoAnalytics::geoIp($requestArr[1]);
                    if($addHeader){
                        foreach(array_keys($ipDetails) as $dets){
                            $contentHeader[] = $dets;
                        }
                        $contentHeader[] = "datetime";
                        $contentArr[] = implode(',',$contentHeader);
                        $addHeader = false;
                    }
                    foreach($ipDetails as $dets){
                        $requestArr[] = $dets;
                    }
                    $requestArr[] = $datetime;
                    $contentArr[] = implode(',',$requestArr);
                }
            }
        } else {
            $contentArr[] = implode(',',['Unknown information']);
        }
        $contents = implode("\n",$contentArr);

        return response()->streamDownload(function () use($contents) {
            echo $contents;
        }, "geo-analitics.csv");

    }

    public function clearIpCache()
    {
        $filesystem = new Filesystem();
        $path = geo_storage_path("requests/ips");
        if(file_exists($path)){
            $filesystem->cleanDirectory($path);
        }
        GeoAnalytics::update_cache();
        return redirect()->route('statamic.cp.utilities.geo-analytics');
    }


    public function clearLogCache()
    {
        $filesystem = new Filesystem();
        $path = geo_storage_path("requests/processed");
        if(file_exists($path)){
            $filesystem->cleanDirectory($path);
        }
        GeoAnalytics::update_cache();
        return redirect()->route('statamic.cp.utilities.geo-analytics');
    }

    public function updatePreferences(Request $request)
    {
        $profileLatest = Yaml::parseFile(geo_storage_path('profile.yaml'));

        $profile = [
            'status' => $request['status'],
            'ip_provider' => [
                'alias' => $request['ip_provider_alias'],
                'token' => $request['ip_provider_token'] ?? ''
            ],
            'store_ips' => $request['store_ips'],
            'my_ip' => $profileLatest['my_ip']
        ];

        file_put_contents(geo_storage_path('profile.yaml'), Yaml::dump($profile));
        geo_permissions_path(geo_storage_path('profile.yaml'));

        if($request['status']){
            file_put_contents(geo_storage_path('enabled'),'Tracking requests');
            geo_permissions_path(geo_storage_path('enabled'));

            if(file_exists(geo_storage_path('disabled'))){
                unlink(geo_storage_path('disabled'));
            }
        } else {
            if(file_exists(geo_storage_path('enabled'))){
                unlink(geo_storage_path('enabled'));
            }

            file_put_contents(geo_storage_path('disabled'),'Not tracking requests');
            geo_permissions_path(geo_storage_path('disabled'));
        }

        Toast::success('<span style="font-size: 14px">Preferences updated.</span>')->duration(5000);

        if(!$request['status']){
            Toast::info('<span style="font-size: 14px">Geo Analytics disabled.</span>')->duration(5000);
        } else {
            $ip = $profile['my_ip'] ?? 'unknown';
            $ipDetails = GeoAnalytics::geoIp($ip, true);

            if($ipDetails['country'] == "unknown" && $ipDetails['city'] == "unknown"){
                $message = '
                <span style="font-size: 14px; white-space: pre-line !important"><i style="margin-bottom:5px;background-color: #f44336; font-size: 12px">Unable to obtain valid information from '.$request['ip_provider_alias'].'.</i> <span style="font-size: 12px">(Your Server IP: <b>'.$ip.'</b> | Country: <b>'.$ipDetails['country'].'</b> | City: <b>'.$ipDetails['city'].'</b>)</span></span>
                ';
                Toast::error($message)->duration(10000);

            } elseif($ipDetails['country'] != "unknown" && $ipDetails['city'] != "unknown"){
                $message = '
                <span style="font-size: 14px; white-space: pre-line !important"><i style="margin-bottom:5px;background-color: #76cb7a; font-size: 12px">Successfuly obtained information from '.$request['ip_provider_alias'].'.</i> <span style="font-size: 12px">(Your Server IP: <b>'.$ip.'</b> | Country: <b>'.$ipDetails['country'].'</b> | City: <b>'.$ipDetails['city'].'</b>)</span></span>
                ';
                Toast::success($message)->duration(10000);
            } else {
                $message = '
                <span style="font-size: 14px; white-space: pre-line !important"><i style="margin-bottom:5px;background-color: #3c5b68; font-size: 12px">Obtained information from '.$request['ip_provider_alias'].' might not be fully correct.</i> <span style="font-size: 12px">(Your Server IP: <b>'.$ip.'</b> | Country: <b>'.$ipDetails['country'].'</b> | City: <b>'.$ipDetails['city'].'</b>)</span></span>
                ';
                Toast::info($message)->duration(10000);
            }
        }
        return true;
    }


    public function resetAppData()
    {
        $filesystem = new Filesystem();
        foreach(['analytics','ips','processed','unprocessed'] as $dir){
            $path = geo_storage_path("requests/$dir");
            if(file_exists($path)){
                $filesystem->cleanDirectory($path);
            }
        }

        if(file_exists(geo_storage_path("requests/db.yaml"))){
            $filesystem->delete(geo_storage_path("requests/blacklist.yaml"));
        }

        if(file_exists(geo_storage_path("requests/db.yaml"))){
            $filesystem->delete(geo_storage_path("requests/db.yaml"));
        }

        if(file_exists(geo_storage_path("requests/db-today.yaml"))){
            $filesystem->delete(geo_storage_path("requests/db-today.yaml"));
        }

        if(file_exists(geo_storage_path("requests/db-week.yaml"))){
            $filesystem->delete(geo_storage_path("requests/db-week.yaml"));
        }

        if(file_exists(geo_storage_path("requests/db-month.yaml"))){
            $filesystem->delete(geo_storage_path("requests/db-month.yaml"));
        }

        if(file_exists(geo_storage_path("requests/http_status.yaml"))){
            $filesystem->delete(geo_storage_path("requests/http_status.yaml"));
        }

        GeoAnalytics::update_cache();
        return redirect()->route('statamic.cp.utilities.geo-analytics');
    }


}
