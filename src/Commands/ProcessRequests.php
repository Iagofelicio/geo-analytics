<?php

namespace Iagofelicio\GeoAnalytics\Commands;

use DateTime;
use Illuminate\Support\Number;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Filesystem\Filesystem;
use Iagofelicio\GeoAnalytics\Models\GeoAnalytics;

class ProcessRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'requests:process {--force-update} {--factory=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Process logs of requests";


    /**
     * Execute the console command.
     */
    public function handle()
    {

        if($this->option('factory') != null){
            GeoAnalytics::factory(intval($this->option('factory')));
        }

        $this->comment("Started at " . (new DateTime())->format("Y-m-d H:i:s") . " with flag --force-update " . ($this->option('force-update') ? "true" : "false"));

        $profile = Yaml::parseFile(geo_storage_path("profile.yaml"));
        $geoIpProviderIpApi = ($profile['ip_provider']['alias'] == 'ip-api.com') ? true : false;

        $filesystem = new Filesystem();
        $listFiles = $filesystem->files((geo_storage_path('requests/unprocessed')));
        if(empty($listFiles) && $this->option('force-update') == false){
            $this->comment("No new requests");
            $this->comment("Finished at " . (new DateTime())->format("Y-m-d H:i:s") . " without changes!");
            return 0;
        }

        $this->line("Reading DB Yaml files at ". (new DateTime())->format("Y-m-d H:i:s"));

        $rawDbAllPath = geo_storage_path("requests/db.yaml");
        if($filesystem->exists($rawDbAllPath)) $dbAll = Yaml::parseFile($rawDbAllPath);
        else $dbAll = [];

        $rawDbTodayPath = geo_storage_path("requests/db-today.yaml");
        if($filesystem->exists($rawDbTodayPath)) $dbToday = Yaml::parseFile($rawDbTodayPath);
        else $dbToday = [];

        $rawDbWeekPath = geo_storage_path("requests/db-week.yaml");
        if($filesystem->exists($rawDbWeekPath)) $dbWeek = Yaml::parseFile($rawDbWeekPath);
        else $dbWeek = [];

        $rawDbMonthPath = geo_storage_path("requests/db-month.yaml");
        if($filesystem->exists($rawDbMonthPath)) $dbMonth = Yaml::parseFile($rawDbMonthPath);
        else $dbMonth = [];

        $listHttpStatusPath = geo_storage_path("requests/http_status.yaml");
        if($filesystem->exists($listHttpStatusPath)) $listHttpStatus = Yaml::parseFile($listHttpStatusPath);
        else $listHttpStatus = [];

        $this->line("Finished reading DB Yaml files at ". (new DateTime())->format("Y-m-d H:i:s"));

        if($this->option('force-update') == true){
            $dbTodayRemoved = [];
            $dbTodayRemoved['header'] = $dbToday['header'];
            foreach($dbToday['data'] as $idx => $data ) {
                foreach($data as $datetime => $datasetRaw) {
                    if($datetime < (new DateTime())->format('Y-m-d 00:00:00') || $datetime > (new DateTime())->format('Y-m-d 23:59:59')){
                        $dbTodayRemoved['data'][$idx] = $dbToday['data'][$idx];
                        unset($dbToday['data'][$idx]);
                    }
                }
            }

            $dbWeekRemoved = [];
            $dbWeekRemoved['header'] = $dbWeek['header'];
            foreach($dbWeek['data'] as $idx => $data ) {
                foreach($data as $datetime => $datasetRaw) {
                    if($datetime < (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00') || $datetime > (new DateTime())->format('Y-m-d 23:59:59')){
                        $dbWeekRemoved['data'][$idx] = $dbWeek['data'][$idx];
                        unset($dbWeek['data'][$idx]);
                    }
                }
            }

            $dbMonthRemoved = [];
            $dbMonthRemoved['header'] = $dbMonth['header'];
            foreach($dbMonth['data'] as $idx => $data ) {
                foreach($data as $datetime => $datasetRaw) {
                    if($datetime < (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00') || $datetime > (new DateTime())->format('Y-m-d 23:59:59')){
                        $dbMonthRemoved['data'][$idx] = $dbMonth['data'][$idx];
                        unset($dbMonth['data'][$idx]);
                    }
                }
            }
            $datesPath = geo_storage_path("requests/analytics/dates.json");
            if(file_exists($datesPath)) {
                $datesDataset = json_decode(file_get_contents($datesPath), true);
            }
            else $datesDataset = [];

            $countriesPath = geo_storage_path("requests/analytics/countries.json");
            if(file_exists($countriesPath)){
                $countriesDataset = json_decode(file_get_contents($countriesPath), true);
            }
            else $countriesDataset = [];

            $citiesPath = geo_storage_path("requests/analytics/cities.json");
            if(file_exists($citiesPath)){
                $citiesDataset = json_decode(file_get_contents($citiesPath), true);
            }
            else $citiesDataset = [];

            $uriPath = geo_storage_path("requests/analytics/uris.json");
            if(file_exists($uriPath)){
                $uriDataset = json_decode(file_get_contents($uriPath), true);
            }
            else $uriDataset = [];

            $ipPath = geo_storage_path("requests/analytics/ips.json");
            if(file_exists($ipPath)){
                $ipDataset = json_decode(file_get_contents($ipPath), true);
            }
            else $ipDataset = [];

            $counter = 0;
            $totalIpsRequested = 0;
            $totalFilesProcessed = 0;
            foreach(['today','week','month'] as $timerange){
                $this->line("\n\tProcessing timerange $timerange at ". (new DateTime())->format("Y-m-d H:i:s"));
                if($timerange == "today") $database = $dbTodayRemoved;
                if($timerange == "week") $database = $dbWeekRemoved;
                if($timerange == "month") $database = $dbMonthRemoved;

                $nCounter = 0;
                if(!isset($database['data'])){
                    continue;
                }
                $header = explode(',',$database['header']);
                foreach($database['data'] as $data ) {
                    foreach($data as $datetime => $datasetRaw) {
                        $this->line("\t\t[$nCounter][$timerange] Procesing datetime $datetime");
                        $nCounter++;
                        $dataset = explode(',',$datasetRaw);
                        $contents = [];
                        foreach($header as $idx => $key){
                            if(in_array($key,['t','status'])){
                                $contents[$key] = intval($dataset[$idx]);
                            } else {
                                $contents[$key] = $dataset[$idx];
                            }
                        }
                        $date = DateTime::createFromFormat('U', $contents['t']);
                        #Store all available status files
                        if(!in_array($contents['status'],$listHttpStatus)){
                            $listHttpStatus[] = $contents['status'];
                            file_put_contents($listHttpStatusPath, Yaml::dump($listHttpStatus));
                            geo_permissions_path($listHttpStatusPath);
                        }
                        $contents['statusList'] = $listHttpStatus;
                        $contents['dateStr'] = $date->format("Y-m-d H:i:s");

                        if($geoIpProviderIpApi){
                            $totalFilesProcessed++;
                            if(!file_exists(geo_storage_path("requests/ips/". $contents['ip']. ".yaml"))){
                                $totalIpsRequested++;
                                $counter++;
                                if($counter == 1){
                                    $startTime = (new DateTime())->format("Y-m-d H:i:s");
                                    $endTime = (new DateTime())->modify("+1 minute")->format("Y-m-d H:i:s");
                                }
                                if($counter >= 45 && (new DateTime())->format("Y-m-d H:i:s") <= $endTime){
                                    $this->comment("\nStart time of request to ip-api.com: $startTime");
                                    $this->comment("End time of request to ip-api.com: $endTime");
                                    $this->comment("Total requests processed: $totalFilesProcessed");
                                    $this->comment("Total IPs requested in the last minute to ip-api.com: $counter");
                                    $this->comment("Total IPs requested to ip-api.com in the current execution: $totalIpsRequested");
                                    $this->comment("Total IPs cached: " . count($filesystem->files((geo_storage_path('requests/ips')))));

                                    $secondsLeft = intval((new DateTime($endTime))->getTimestamp()) - intval((new DateTime())->getTimestamp());
                                    $this->comment("Waiting left time for free quota of ip-api.com: $secondsLeft seconds");
                                    sleep($secondsLeft + 1);
                                    $startTime = (new DateTime())->format("Y-m-d H:i:s");
                                    $endTime = (new DateTime())->modify("+1 minute")->format("Y-m-d H:i:s");
                                    $counter = 0;
                                }
                            }
                        }
                        #Get IP Details
                        $ipDetails = GeoAnalytics::geoIp($contents['ip']);

                        #Update Analytics
                        $ipDataset = GeoAnalytics::updateAnalytics("ips",$ipDetails,$contents,$ipDataset,$timerange,'subtract');
                        $datesDataset = GeoAnalytics::updateAnalytics("dates",$ipDetails,$contents,$datesDataset,$timerange,'subtract');
                        $countriesDataset = GeoAnalytics::updateAnalytics("countries",$ipDetails,$contents,$countriesDataset,$timerange,'subtract');
                        $citiesDataset = GeoAnalytics::updateAnalytics("cities",$ipDetails,$contents,$citiesDataset,$timerange,'subtract');
                        $uriDataset = GeoAnalytics::updateAnalytics("uris",$ipDetails,$contents,$uriDataset,$timerange,'subtract');
                    }
                }
                if(empty($ipDataset[$timerange]['data'])) unset($ipDataset[$timerange]['data']);
                if(empty($datesDataset[$timerange]['data'])) unset($datesDataset[$timerange]['data']);
                if(empty($countriesDataset[$timerange]['data'])) unset($countriesDataset[$timerange]['data']);
                if(empty($citiesDataset[$timerange]['data'])) unset($citiesDataset[$timerange]['data']);
                if(empty($uriDataset[$timerange]['data'])) unset($uriDataset[$timerange]['data']);

                $this->line("\tFinished timerange $timerange at ". (new DateTime())->format("Y-m-d H:i:s"));

                #Update GeoJSON
                $this->line("\tUpdating geojson for timerange $timerange at ". (new DateTime())->format("Y-m-d H:i:s"));
                if(!empty($countriesDataset)){
                    $contents = [];
                    $contents['statusList'] = $listHttpStatus;
                    GeoAnalytics::updateAnalytics("geojson",null,$contents,$countriesDataset,$timerange,null);
                }
                $this->line("\tFinished geojson for timerange $timerange at ". (new DateTime())->format("Y-m-d H:i:s"));
            }

            $this->line("Finished data processing at ". (new DateTime())->format("Y-m-d H:i:s"));

            $this->line("\nWriting files at ". (new DateTime())->format("Y-m-d H:i:s"));
            #Write updated analytics files
            if(!empty($datesDataset)){
                file_put_contents($datesPath, json_encode($datesDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($datesPath);
            }
            if(!empty($countriesDataset)){
                file_put_contents($countriesPath, json_encode($countriesDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($countriesPath);
            }
            if(!empty($citiesDataset)){
                file_put_contents($citiesPath, json_encode($citiesDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($citiesPath);
            }
            if(!empty($uriDataset)){
                file_put_contents($uriPath, json_encode($uriDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($uriPath);
            }
            if(!empty($ipDataset)){
                file_put_contents($ipPath, json_encode($ipDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($ipPath);
            }

            $this->line("Finished writing files at ". (new DateTime())->format("Y-m-d H:i:s"));
        }

        if(!empty($listFiles)){
            $nFiles = count($listFiles);

            $datesPath = geo_storage_path("requests/analytics/dates.json");
            if(file_exists($datesPath)) $datesDataset = json_decode(file_get_contents($datesPath), true);
            else $datesDataset = [];

            $countriesPath = geo_storage_path("requests/analytics/countries.json");
            if(file_exists($countriesPath)) $countriesDataset = json_decode(file_get_contents($countriesPath), true);
            else $countriesDataset = [];

            $citiesPath = geo_storage_path("requests/analytics/cities.json");
            if(file_exists($citiesPath)) $citiesDataset = json_decode(file_get_contents($citiesPath), true);
            else $citiesDataset = [];

            $uriPath = geo_storage_path("requests/analytics/uris.json");
            if(file_exists($uriPath)) $uriDataset = json_decode(file_get_contents($uriPath), true);
            else $uriDataset = [];


            $ipPath = geo_storage_path("requests/analytics/ips.json");
            if(file_exists($ipPath)) $ipDataset = json_decode(file_get_contents($ipPath), true);
            else $ipDataset = [];

            $counter = 0;
            $totalIpsRequested = 0;
            $totalFilesProcessed = 0;
            $blacklist = Yaml::parseFile(geo_storage_path("requests/blacklist.yaml"));

            foreach($listFiles as $idx => $file) {
                $contents = Yaml::parseFile($file->getPathname());
                if(isset($blacklist)){
                    if(in_array($contents['ip'],$blacklist)){
                        $filesystem->move($file->getPathname(), str_replace('unprocessed','processed',$file->getPathname()));
                        continue;
                    }
                }

                $date = DateTime::createFromFormat('U', $contents['t']);
                $contents['dateStr'] = $date->format("Y-m-d H:i:s");

                //Update List of available status
                if(!in_array($contents['status'],$listHttpStatus)){
                    $listHttpStatus[] = $contents['status'];
                    file_put_contents($listHttpStatusPath, Yaml::dump($listHttpStatus));
                    geo_permissions_path($listHttpStatusPath);
                }

                //Update DBs
                $dbAll['header'] = implode(',',array_keys($contents));
                $dbAll['data'][] = [$date->format("Y-m-d H:i:s") => implode(',',$contents)];

                if($contents['dateStr'] >= (new DateTime())->format('Y-m-d 00:00:00') && $contents['dateStr'] <= (new DateTime())->format('Y-m-d 23:59:59')){
                    $dbToday['header'] = implode(',',array_keys($contents));
                    $dbToday['data'][] = [$date->format("Y-m-d H:i:s") => implode(',',$contents)];
                }

                if($contents['dateStr'] >= (new DateTime())->modify("-6 days")->format('Y-m-d 00:00:00') && $contents['dateStr'] <= (new DateTime())->format('Y-m-d 23:59:59')){
                    $dbWeek['header'] = implode(',',array_keys($contents));
                    $dbWeek['data'][] = [$date->format("Y-m-d H:i:s") => implode(',',$contents)];
                }

                if($contents['dateStr'] >= (new DateTime())->modify("-1 month")->format('Y-m-d 00:00:00') && $contents['dateStr'] <= (new DateTime())->format('Y-m-d 23:59:59')){
                    $dbMonth['header'] = implode(',',array_keys($contents));
                    $dbMonth['data'][] = [$date->format("Y-m-d H:i:s") => implode(',',$contents)];
                }

                $contents['statusList'] = $listHttpStatus;

                #Get IP Details
                if($geoIpProviderIpApi){
                    $totalFilesProcessed++;
                    if(!file_exists(geo_storage_path("requests/ips/". $contents['ip']. ".yaml"))){
                        $counter++;
                        $totalIpsRequested++;
                        if($counter == 1){
                            $startTime = (new DateTime())->format("Y-m-d H:i:s");
                            $endTime = (new DateTime())->modify("+1 minute")->format("Y-m-d H:i:s");
                            $this->comment("\nStart time of request to ip-api.com: $startTime");
                            $this->comment("End time of request to ip-api.com: $endTime");
                        }
                        if($counter >= 45 && (new DateTime())->format("Y-m-d H:i:s") <= $endTime){
                            $this->comment("Total requests processed: $totalFilesProcessed");
                            $this->comment("Total IPs requested in the last minute to ip-api.com: $counter");
                            $this->comment("Total IPs requested to ip-api.com in the current execution: $totalIpsRequested");
                            $this->comment("Total IPs cached: " . count($filesystem->files((geo_storage_path('requests/ips')))));

                            $secondsLeft = intval((new DateTime($endTime))->getTimestamp()) - intval((new DateTime())->getTimestamp());
                            $this->comment("Waiting left time for free quota of ip-api.com: $secondsLeft seconds");
                            sleep($secondsLeft + 1);
                            $startTime = (new DateTime())->format("Y-m-d H:i:s");
                            $endTime = (new DateTime())->modify("+1 minute")->format("Y-m-d H:i:s");
                            $counter = 0;
                        }
                    }
                }
                $ipDetails = GeoAnalytics::geoIp($contents['ip']);

                #Update Analytics
                $ipDataset = GeoAnalytics::updateAnalytics("ips",$ipDetails,$contents,$ipDataset,null,'add');
                $datesDataset = GeoAnalytics::updateAnalytics("dates",$ipDetails,$contents,$datesDataset,null,'add');
                $countriesDataset = GeoAnalytics::updateAnalytics("countries",$ipDetails,$contents,$countriesDataset,null,'add');
                $citiesDataset = GeoAnalytics::updateAnalytics("cities",$ipDetails,$contents,$citiesDataset,null,'add');
                $uriDataset = GeoAnalytics::updateAnalytics("uris",$ipDetails,$contents,$uriDataset,null,'add');

                #Move file to processed list
                $filesystem->move($file->getPathname(), str_replace('unprocessed','processed',$file->getPathname()));
            }

            #Update GeoJSON
            if(!empty($countriesDataset)){
                $contents = [];
                $contents['statusList'] = $listHttpStatus;
                GeoAnalytics::updateAnalytics("geojson",null,$contents,$countriesDataset,null,null);
            }

            #Write updated analytics files
            if(!empty($datesDataset)){
                file_put_contents($datesPath, json_encode($datesDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($datesPath);
            }
            if(!empty($countriesDataset)){
                file_put_contents($countriesPath, json_encode($countriesDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($countriesPath);
            }
            if(!empty($citiesDataset)){
                file_put_contents($citiesPath, json_encode($citiesDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($citiesPath);
            }
            if(!empty($uriDataset)){
                file_put_contents($uriPath, json_encode($uriDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($uriPath);
            }
            if(!empty($ipDataset)){
                file_put_contents($ipPath, json_encode($ipDataset,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                geo_permissions_path($ipPath);
            }
        }

        //Update DB yaml files
        $this->line("\nUpdating DB yaml files at ". (new DateTime())->format("Y-m-d H:i:s"));
        if(!empty($dbAll)){
            file_put_contents($rawDbAllPath, Yaml::dump($dbAll));
            geo_permissions_path($rawDbAllPath);
        }

        if(!empty($dbToday)){
            file_put_contents($rawDbTodayPath, Yaml::dump($dbToday));
            geo_permissions_path($rawDbTodayPath);
        }

        if(!empty($dbWeek)){
            file_put_contents($rawDbWeekPath, Yaml::dump($dbWeek));
            geo_permissions_path($rawDbWeekPath);
        }

        if(!empty($dbMonth)){
            file_put_contents($rawDbMonthPath, Yaml::dump($dbMonth));
            geo_permissions_path($rawDbMonthPath);
        }
        $this->line("Finished updating DB yaml files at ". (new DateTime())->format("Y-m-d H:i:s"));

        $this->line("\nUpdating cache at ". (new DateTime())->format("Y-m-d H:i:s"));
        GeoAnalytics::update_cache();
        $this->line("Finished updating cache at ". (new DateTime())->format("Y-m-d H:i:s"));


        $this->comment("\nFinished requests processing at " . (new DateTime())->format("Y-m-d H:i:s") . "!");
        return 0;
    }
}
