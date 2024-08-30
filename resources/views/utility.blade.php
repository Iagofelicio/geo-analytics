@extends('statamic::layout')
@section('title', __("Geo Analytics"))

@push('head')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://nightly.datatables.net/js/dataTables.js"></script>
    <link href="https://nightly.datatables.net/css/dataTables.dataTables.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.plot.ly/plotly-2.34.0.js" charset="utf-8"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
@endpush

@section('content')
    <div class="pb-4">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-[24px]">
                    {{ __("Geo Analytics") }}
                </p>
            </div>
            <div class="grid justify-items-end items-center	">
                <div class="text-[12px] text-right">
                    <p><span id="statusGeoAnalytics"></span>&nbsp;&nbsp;&nbsp;
                    <span id="latestUpdate"></span></p>
                </div>
            </div>
          </div>
    </div>
    <div id="divCardsEmpty" style="display: none">
        <div class="grid gap-4 grid-cols-1 sm:grid-cols-4 pb-4">
            <div class="mt-3 card">
                <div class="text-center text-[12px]">
                    No data
                </div>
            </div>
            <div class="mt-3 card">
                <div class="text-center text-[12px]">
                    No data
                </div>
            </div>
            <div class="mt-3 card">
                <div class="text-center text-[12px]">
                    No data
                </div>
            </div>
            <div class="mt-3 card">
                <div class="text-center text-[12px]">
                    No data
                </div>
            </div>
        </div>
        <div class="flex items-center justify-between text-[12px] pb-4">
            <p><i>Hover over the cards numbers for additional information.</i></p>
        </div>

    </div>

    <div id="divCards" style="display: none">
        <div class="grid grid-cols-4 pb-4 gap-4">
            <div class="mt-3 card">
                <div class="text-center">
                    <div title="Number of total requests received, including successful and failed attempts.">
                        <span id="totalRequestsAbbreviated" class="text-[3.5rem]"></span><br>
                        <span id="totalRequestsTotal" class="text-[0.75rem] font-bold"></span>
                        <span class="text-[0.9rem] text-center">
                            <p>Requests</p>
                        </span>
                    </div>
                    <div title="Number of successful requests." class="mt-5 text-[0.75rem]">
                        <p class="text-center">
                            <p>Successful responses</p>
                            <p><b><span id="totalRequestsSuccess"></span></b></p>
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-3 card">
                <div class="text-center">
                    <span title="Total unique countries." id="totalCountries" class="text-[3.5rem]"></span>
                    <span class="text-[0.9rem] text-center">
                        <p>Countries</p>
                    </span>
                    <div class="mt-5 text-[0.75rem]">
                        <p class="text-center">
                            <p>Top Country: <b><span id="topCountry"></span></b></p>
                            <p>Top City: <b><span id="topCity"></span></b></p>
                        </p>
                    </div>

                </div>
            </div>

            <div class="mt-3 card">
                <div class="text-center">
                    <span title="Total unique URIs." id="totalUris" class="text-[3.5rem]"></span>
                    <span class="text-[0.9rem] text-center">
                        <p>URIs</p>
                    </span>
                    <div class="mt-5 text-[0.75rem]">
                        <p class="text-center">
                            <p>Top URI</p>
                            <p><b><span id="topUri"></span></b></p>
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-3 card">
                <div class="text-center">
                    <span title="Total unique IPs." id="totalIps" class="text-[3.5rem]"></span>
                    <span class="text-[0.9rem] text-center">
                        <p>IPs</p>
                    </span>
                    <div class="mt-5 text-[0.75rem]">
                        <p class="text-center">
                            <p>Top IP</p>
                            <p><b><span id="topIp"></span></b></p>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-between text-[12px] pb-4">
            <p><i>Hover over the cards numbers for additional information.</i></p>
        </div>
    </div>
    <div class="pb-4">
        <div class="mt-3 card pb-4">
            <div class="card-header pb-4">
                <p><b>Heatmap of requests by country</b></p>
                <p class="text-[13px] text-justify">A visual representation of the number of requests from different countries, using color intensity to indicate the volume of traffic.</p>
                <div class="text-[11px] pb-2">
                    <button onclick="plotMap('all')"><span id="plotMapSpan-all">All</span></button>&nbsp;&nbsp;
                    <button onclick="plotMap('today')"><span id="plotMapSpan-today">Today</span></button>&nbsp;&nbsp;
                    <button onclick="plotMap('week')"><span id="plotMapSpan-week">7 Days</span></button>&nbsp;&nbsp;
                    <button onclick="plotMap('month')"><span id="plotMapSpan-month">30 Days</span></button>&nbsp;&nbsp;
                </div>
            </div>
            <div class="card-body">
                <div id="divMapEmpty" style="display: none" class="pt-4 text-center text-[12px]">
                    No data
                </div>
                <div id="divMapIframe" style="display: none">
                    <iframe id="geoMapIframe" class="h-[32rem] w-full" src="{{route('statamic.cp.geoMap',['all'])}}"></iframe>
                    <div class="text-[12px] pt-4">
                        <p><i id="mapDatesMessage"></i></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pb-4">
        <div class="mt-3 card">
            <div class="card-header pb-4">
                <div class="flex flex-row pb-2">
                    <div class="w-[75%]">
                        <div class="flex">
                            <b>Daily requests</b>
                        </div>
                        <div class="pb-2">
                            <p class="text-[13px] text-justify">The chart visualizes daily request over time, categorized by HTTP status code.</p>
                        </div>
                    </div>
                    <div class="w-[25%] text-[11px] text-right">
                        <div id="divDownloadDates" style="display: none">
                            <a href="{{route('statamic.cp.download',['dates'])}}" title="Download all data"><u>Download</u></a>
                        </div>
                    </div>
                </div>
                <div id="divFilterDates" style="display: none">
                    <div class="flex flex-row items-center pb-2">
                        <div class="flex flex-row items-center pr-8">
                            <label class="text-[12px] font-bold pr-4" for="startDateTimeseries">Start:</label>
                            <input class="text-[12px] text-black" type="date" id="startDateTimeseries" name="startDateTimeseries">
                        </div>
                        <div class="flex flex-row items-center pr-8">
                            <label class="text-[12px] font-bold pr-4" for="endDateTimeseries">End:</label>
                            <input class="text-[12px] text-black" type="date" id="endDateTimeseries" name="endDateTimeseries">
                        </div>
                        <div class="flex flex-row items-center pr-8">
                            <button class="text-[12px]" onclick="filterTimeseries()"><u><a>Filter</a></u></button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="divTimeseriesEmpty" style="display: none" class="pt-4 text-center text-[12px]">
                No data
            </div>
            <div class="card-body text-[13px]">
                <div class="w-full px-4"><canvas id="dailyRequests"></canvas></div>
            </div>
        </div>
    </div>

    <div class="pb-4">
        <div class="mt-3 card" >
            <div class="card-header pb-4">
                <div class="flex flex-row pb-2">
                    <div class="w-[75%]">
                        <div class="flex">
                            <b>Requests by URI</b>&nbsp;&nbsp;
                            <span
                                title="This table provides a detailed overview of website traffic at the URI level, highlighting popular resources, geographic reach, and overall performance. It helps identify high-traffic URIs, common errors, and opportunities for optimization."
                                class="self-center rounded-full border border-gray-200 text-gray-600 px-[4px] py-[0px] text-[10px]">?
                            </span>
                        </div>
                        <div class="text-[11px] pb-2">
                            <button onclick="getUriData('all', true)"><span id="uriSpan-all">All</span></button>&nbsp;&nbsp;
                            <button onclick="getUriData('today', true)"><span id="uriSpan-today">Today</span></button>&nbsp;&nbsp;
                            <button onclick="getUriData('week', true)"><span id="uriSpan-week">7 Days</span></button>&nbsp;&nbsp;
                            <button onclick="getUriData('month', true)"><span id="uriSpan-month">30 Days</span></button>&nbsp;&nbsp;
                        </div>
                    </div>
                    <div class="w-[25%] text-[11px] text-right">
                        <div id="divDownloadUri" style="display: none">
                            <a href="{{route('statamic.cp.download',['uri'])}}"><u>Download</u></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body text-[13px]">
                <div id="divUrisEmpty" style="display: none" class="text-center text-[12px]">
                    No data
                </div>
                <div id="divTableUri" style="display: none">
                    <table id="tableUri" class="display">
                        <thead>
                        </thead>
                        <tbody class="text-[12px]">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="text-[12px] pt-4">
                <p><i id="uriDatesMessage"></i></p>
            </div>
        </div>
    </div>

    <div class="pb-4">
        <div class="mt-3 card" >
            <div class="card-header pb-4">
                <div class="flex flex-row pb-2">
                    <div class="w-[75%]">
                        <div class="flex">
                            <b>Requests by cities</b>&nbsp;&nbsp;
                            <span
                                title="This table provides a breakdown of website traffic by city and country, highlighting the volume of requests, successful requests, and overall success rate for each location. It helps in identifying top performing cities and countries, as well as potential issues based on geographic location."
                                class="self-center rounded-full border border-gray-200 text-gray-600 px-[4px] py-[0px] text-[10px]">?
                            </span>
                        </div>
                        <div class="text-[11px] pb-2">
                            <button onclick="getCitiesData('all', true)"><span id="locationSpan-all">All</span>&nbsp;&nbsp;
                            <button onclick="getCitiesData('today', true)"><span id="locationSpan-today">Today</span>&nbsp;&nbsp;
                            <button onclick="getCitiesData('week', true)"><span id="locationSpan-week">7 Days</span>&nbsp;&nbsp;
                            <button onclick="getCitiesData('month', true)"><span id="locationSpan-month">30 Days</span>&nbsp;&nbsp;
                        </div>
                    </div>
                    <div class="w-[25%] text-[11px] text-right">
                        <div id="divDownloadCities" style="display: none">
                            <a href="{{route('statamic.cp.download',['cities'])}}"><u>Download</u></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body text-[13px]">
                <div id="divCitiesEmpty" style="display: none" class="text-center text-[12px]">
                    No data
                </div>
                <div id="divTableCity" style="display: none">
                    <table id="tableCity" class="display">
                        <thead>
                        </thead>
                        <tbody class="text-[12px]">
                        </tbody>
                    </table>
                </div>
                <div class="text-[12px] pt-4">
                    <p><i id="citiesDatesMessage"></i></p>
                </div>
            </div>
        </div>
    </div>

    <div class="pb-4">
        <div class="mt-3 card">
            <div class="card-header pb-4">
                <div class="flex flex-row pb-2">
                    <div class="w-[75%]">
                        <div class="flex">
                            <b>Requests by hour</b>&nbsp;&nbsp;
                            <span
                                title="This table provides a granular view of website traffic by hour, highlighting the volume of requests, successful requests, and overall success rate for each hour. It helps in identifying peak traffic times, success rates, and potential performance issues."
                                class="self-center rounded-full border border-gray-200 text-gray-600 px-[4px] py-[0px] text-[10px]">?
                            </span>
                        </div>
                        <div class="text-[11px] pb-2">
                            <button onclick="getDatesData('all', true)"><span id="dateSpan-all">All</span></button>&nbsp;&nbsp;
                            <button onclick="getDatesData('today', true)"><span id="dateSpan-today">Today</span></button>&nbsp;&nbsp;
                            <button onclick="getDatesData('week', true)"><span id="dateSpan-week">7 Days</span></button>&nbsp;&nbsp;
                            <button onclick="getDatesData('month', true)"><span id="dateSpan-month">30 Days</span></button>&nbsp;&nbsp;
                        </div>
                    </div>
                    <div class="w-[25%] text-[11px] text-right">
                        <div id="divDownloadDates" style="display: none">
                            <a href="{{route('statamic.cp.download',['dates'])}}" title="Download all data"><u>Download</u></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body text-[13px]">
                <div id="divDatesEmpty" style="display: none" class="text-center text-[12px]">
                    No data
                </div>
                <div id="divTableDate" style="display: none">
                    <table id="tableDate" class="display">
                        <thead>
                        </thead>
                        <tbody class="text-[12px]">
                        </tbody>
                    </table>
                </div>
                <div class="text-[12px] pt-4">
                    <p><i id="dateDatesMessage"></i></p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 card p-0">

        <div class="p-4 bg-gray-200 dark:bg-dark-700 border-t dark:border-dark-900">
            <div class="card-body pb-4">
                <p><b>Addon Information</b></p>
            </div>
            <div class="flex justify-between items-center">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h2 class="font-bold">Export</h2>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">Download your data to extend your analysis.</p>
                </div>
                <div class="flex">
                    <a href="{{route('statamic.cp.download',['full'])}}">
                        <button class="btn">Download</button>
                    </a>
                </div>
            </div>
        </div>

        <div class="p-4 border-t dark:border-dark-900">
            <div class="flex justify-between items-center">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h2 class="font-bold">IP Cache</h2>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">The IP cache stores previously fetched IP information to optimize performance and reduce load on the IP service provider. This behavior can be modified to disable caching if needed.</p>
                </div>
                <form method="POST" action="{{route('statamic.cp.clearIpCache')}}">
                    @csrf
                    <button class="btn">Clear</button>
                </form>
            </div>
            <div class="text-sm text-gray dark:text-dark-150 flex">
                <div class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                    <span class="text-gray-800 dark:text-dark-150 font-medium">Records:</span>
                    <span id="ipCacheRecords"></span>
                </div>
                <div class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                    <span class="text-gray-800 dark:text-dark-150 font-medium">Size:</span>
                    <span id="ipCacheSize"></span>

                </div>
            </div>
        </div>
        <div class="p-4 bg-gray-200 dark:bg-dark-700 border-t dark:border-dark-900">
            <div class="flex justify-between items-center">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h2 class="font-bold">Log Cache</h2>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">The log cache represents user request information unprocessed or already processed and used to populate the application data used in the dashboard. Deleting it will not impact the insights currently displayed.</p>
                </div>
                <div class="flex">
                    <form method="POST" action="{{route('statamic.cp.clearLogCache')}}">
                        @csrf
                        <button class="btn">Clear</button>
                    </form>
                </div>
            </div>
            <div class="text-sm text-gray dark:text-dark-150 flex">
                <div class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                    <span class="text-gray-800 dark:text-dark-150 font-medium">Records:</span>
                    <span id="logCacheRecords"></span>
                </div>
                <div class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                    <span class="text-gray-800 dark:text-dark-150 font-medium">Size:</span>
                    <span id="logCacheSize"></span>
                </div>
            </div>
        </div>

        <div class="p-4">
            <div class="flex justify-between items-center">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h2 class="font-bold">Application cache</h2>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">The application cache stores essential data used to generate the dashboard's analytics. Deleting it will permanently erase all historical data and insights.</p>
                </div>
                <div class="flex">
                    <button onclick="getConfirmationReset('prompt')" class="btn btn-danger">Reset</button>
                </div>
                <br>
                <div class="pl-2 flex text-[12px]">
                    <div id="confirmationResetMsg" style="display: none">
                        Are you sure? <a href="{{ route('statamic.cp.resetAppData') }}"><u>Yes</u></a>&nbsp;&nbsp; <button onclick="getConfirmationReset('deny')"><u>No</u></button>
                    </div>
                </div>
            </div>
            <div class="text-sm text-gray dark:text-dark-150 flex">
                <div class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                    <span class="text-gray-800 dark:text-dark-150 font-medium">Records:</span>
                    <span id="appCacheRecords"></span>
                </div>
                <div class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                    <span class="text-gray-800 dark:text-dark-150 font-medium">Size:</span>
                    <span id="appCacheSize"></span>
                </div>
            </div>
        </div>


        <div class="p-4 bg-gray-200 dark:bg-dark-700 border-t dark:border-dark-900">
            <div class="flex justify-between items-center">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h2 class="font-bold">Preferences</h2>
                </div>
                <div class="flex">
                    <button id="editIpProvider" onclick="preferences('edit')" style="display: block" class="btn">Edit</button>
                    <button id="updateIpProvider" onclick="preferences('save')" style="display: none" class="btn">Save</button>
                </div>
            </div>
            <div class="text-sm text-gray dark:text-dark-150 flex flex-col">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h3 class="font-bold text-[14px]">Geo IP Provider</h3>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">The Geo IP Provider is responsible for sending IP information.</p>
                </div>
                <div class="text-gray dark:text-dark-150 text-sm my-2">
                    <input onclick="displayToken('',['ip-api.com'])" type="radio" id="ip-api.com" name="ipProvider" checked disabled>
                    <span class="text-gray-800 dark:text-dark-150"><a class="!text-[14px]" target="_blank" href="https://ip-api.com/"><u>ip-api.com</u></a></span>
                    <span class="rtl:ml-4 ltr:mr-0 badge-pill-sm">
                        <span class="text-gray-800 dark:text-dark-150 !text-[12px]">45 req/min free</span>
                    </span>
                    <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                        <span class="text-gray-800 dark:text-dark-150 !text-[12px]">No SSL</span>
                    </span>
                </div>
                <div class="text-gray dark:text-dark-150 text-sm my-2">
                    <div class="">
                        <input onclick="displayToken('block',['apiip.net'])" type="radio" id="apiip.net" name="ipProvider" disabled>
                        <span class="text-gray-800 dark:text-dark-150"><a class="!text-[14px]" target="_blank" href="https://apiip.net/"><u>apiip.net</u></a></span>
                        <span class="rtl:ml-4 ltr:mr-0 badge-pill-sm">
                            <span class="text-gray-800 dark:text-dark-150 !text-[12px]">1,000 req/mo free</span>
                        </span>
                        <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                            <span class="text-gray-800 dark:text-dark-150 !text-[12px]">SSL</span>
                        </span>
                    </div>
                    <div class="w-full px-4 text-[10px]">
                        <label id="apiip.netToken" class="" style="display: none">
                            <span class="block text-[11px] text-slate-700">Token (apiip.net)</span>
                            <input type="text" id="apiip.netTokenInput" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-[11px] shadow-sm placeholder-slate-400
                              focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500
                              disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none
                              invalid:border-pink-500 invalid:text-pink-600
                              focus:invalid:border-pink-500 focus:invalid:ring-pink-500
                            "/>
                        </label>
                    </div>
                </div>
                <div class="text-gray dark:text-dark-150 text-sm my-2">
                    <div class="">
                        <input onclick="displayToken('block',['ip2location.io'])" type="radio" id="ip2location.io" name="ipProvider" disabled>
                        <span class="text-gray-800 dark:text-dark-150"><a class="!text-[14px]" target="_blank" href="https://www.ip2location.io/"><u>ip2location.io</u></a></span>
                        <span class="rtl:ml-4 ltr:mr-0 badge-pill-sm">
                            <span class="text-gray-800 dark:text-dark-150 !text-[12px]">30,000 req/mo free</span>
                        </span>
                        <span class="rtl:ml-4 ltr:mr-4 badge-pill-sm">
                            <span class="text-gray-800 dark:text-dark-150 !text-[12px]">SSL</span>
                        </span>
                    </div>
                    <div class="w-full px-4 text-[10px]">
                        <label id="ip2location.ioToken" class="" style="display: none">
                            <span class="block text-[11px] text-slate-700">Token (ip2location.io)</span>
                            <input type="text" id="ip2location.ioTokenInput" class="mt-1 block w-full px-3 py-2 bg-white border border-slate-300 rounded-md text-[11px] shadow-sm placeholder-slate-400
                              focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500
                              disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none
                              invalid:border-pink-500 invalid:text-pink-600
                              focus:invalid:border-pink-500 focus:invalid:ring-pink-500
                            "/>
                        </label>
                    </div>
                </div>
            </div>

            <div class="text-sm text-gray dark:text-dark-150 flex flex-col pt-6">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h3 class="font-bold text-[14px]">IP Cache</h3>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">To avoid unnecessary checks for IP information from previously seen IP addresses, it is highly recommended to enable IP caching.</p>
                    <input onclick="changeIpCache()" type="checkbox" id="ipCacheStatus" disabled><span class="pl-2" id="ipCacheStatusMsg"></span>
                </div>
            </div>
            <div class="text-sm text-gray dark:text-dark-150 flex flex-col pt-6">
                <div class="rtl:pl-8 ltr:pr-8">
                    <h3 class="font-bold text-[14px]">Addon status</h3>
                    <p class="text-gray dark:text-dark-150 text-sm my-2">You can pause tracking of new requests without uninstalling the addon, or resume monitoring whenever you choose.</p>
                    <input onclick="changeAddonStatus()" type="checkbox" id="addonStatus" disabled><span class="pl-2" id="addonStatusMsg"></span>
                </div>
            </div>
        </div>
    </div>

    <script>
        function preferences(action){
            btnEditEl = document.getElementById('editIpProvider');
            btnSaveEl = document.getElementById('updateIpProvider');
            inptIpApiComEl = document.getElementById('ip-api.com');
            inptApiIpNetEl = document.getElementById('apiip.net');
            inptIp2LocationIoEl = document.getElementById('ip2location.io');
            inptApiIpNetTokenEl = document.getElementById('apiip.netToken');
            inptIp2LocationIoTokenEl = document.getElementById('ip2location.ioToken');
            ipCacheStatusEl = document.getElementById('ipCacheStatus');

            if(action == "edit"){
                btnEditEl.style.display = "none";
                btnSaveEl.style.display = "block";
                inptIpApiComEl.disabled = false;
                inptApiIpNetEl.disabled = false;
                inptIp2LocationIoEl.disabled = false;
                ipCacheStatusEl.disabled = false;
                addonStatusEl.disabled = false;

                if(inptApiIpNetEl.checked){
                    inptApiIpNetTokenEl.style.display = "block";
                }

                if(inptIp2LocationIoEl.checked){
                    inptIp2LocationIoTokenEl.style.display = "block";
                }
            }

            if(action == "save"){
                btnEditEl.style.display = "block";
                btnSaveEl.style.display = "none";
                inptIpApiComEl.disabled = true;
                inptApiIpNetEl.disabled = true;
                inptIp2LocationIoEl.disabled = true;

                inptApiIpNetTokenEl.style.display = "none";
                inptIp2LocationIoTokenEl.style.display = "none";
                ipCacheStatusEl.disabled = true;
                addonStatusEl.disabled = true;

                //Post preferences
                ipCacheStatusEl = document.getElementById('ipCacheStatus');
                addonStatusEl = document.getElementById('addonStatus');

                inptApiIpNetTokenInputEl = document.getElementById('apiip.netTokenInput');
                inptIp2LocationIoTokenInputEl = document.getElementById('ip2location.ioTokenInput');

                inptIpApiComEl = document.getElementById('ip-api.com');
                inptApiIpNetEl = document.getElementById('apiip.net');
                inptip2locationIoEl = document.getElementById('ip2location.io');

                if(inptIpApiComEl.checked == true){
                    ip_provider_alias = 'ip-api.com';
                    ip_provider_token = '';
                }
                if(inptApiIpNetEl.checked == true){
                    ip_provider_alias = 'apiip.net';
                    ip_provider_token = inptApiIpNetTokenInputEl.value;
                }
                if(inptip2locationIoEl.checked == true){
                    ip_provider_alias = 'ip2location.io';
                    ip_provider_token = inptIp2LocationIoTokenInputEl.value;
                }

                axios.post('{{ route("statamic.cp.updatePreferences") }}', {
                    status: addonStatusEl.checked,
                    store_ips: ipCacheStatusEl.checked,
                    ip_provider_alias: ip_provider_alias,
                    ip_provider_token: ip_provider_token
                })
                .then(function (response) {
                    if(response.data == true){
                        window.location.reload();
                    }
                })
                .catch(function (error) {
                    console.log(error);
                });
            }
        }

        function displayToken(action, providers){
            inptApiIpNetTokenEl = document.getElementById('apiip.netToken');
            inptIp2LocationIoTokenEl = document.getElementById('ip2location.ioToken');
            for (const provider of providers) {
                if(provider == 'ip-api.com'){
                    inptIp2LocationIoTokenEl.style.display = "none";
                    inptApiIpNetTokenEl.style.display = "none";
                }
                if(provider == 'apiip.net'){
                    inptApiIpNetTokenEl.style.display = action;
                    if(action == "block"){
                        inptIp2LocationIoTokenEl.style.display = "none";
                    } else {
                        inptIp2LocationIoTokenEl.style.display = "block";
                    }

                }
                if(provider == 'ip2location.io'){
                    inptIp2LocationIoTokenEl.style.display = action;
                    if(action == "block"){
                        inptApiIpNetTokenEl.style.display = "none";
                    } else {
                        inptApiIpNetTokenEl.style.display = "block";
                    }
                }
            }
        }

        function changeAddonStatus(){
            addonStatusEl = document.getElementById('addonStatus');
            addonStatusMsgEl = document.getElementById('addonStatusMsg');
            addonStatusHeaderMsgEl = document.getElementById('statusGeoAnalytics');

            if(addonStatusEl.checked){
                addonStatusMsgEl.innerHTML = "Enabled";
                addonStatusHeaderMsgEl.innerHTML = `<b>Status</b>: Enabled`;
            } else {
                addonStatusMsgEl.innerHTML = "Disabled";
                addonStatusHeaderMsgEl.innerHTML = `<b>Status</b>: Disabled`;
            }

        }

        function changeIpCache(){
            ipCacheStatusEl = document.getElementById('ipCacheStatus');
            ipCacheStatusMsgEl = document.getElementById('ipCacheStatusMsg');
            if(ipCacheStatusEl.checked){
                ipCacheStatusMsgEl.innerHTML = "Enabled";
            } else {
                ipCacheStatusMsgEl.innerHTML = "Disabled";
            }

        }

        function getConfirmationReset(action){
            if(action == "prompt"){
                msgEl = document.getElementById('confirmationResetMsg');
                msgEl.style.display = "block";
            }

            if(action == "deny"){
                msgEl = document.getElementById('confirmationResetMsg');
                msgEl.style.display = "none";
            }
        }

        function getProfile(){

            axios.get("{{route('statamic.cp.profile')}}")
                .then(function (response) {
                    inptIpApiComEl = document.getElementById('ip-api.com');
                    inptApiIpNetEl = document.getElementById('apiip.net');
                    inptApiIpNetTokenInputEl = document.getElementById('apiip.netTokenInput');

                    inptIp2LocationIoEl = document.getElementById('ip2location.io');
                    inptIp2LocationIoTokenInputEl = document.getElementById('ip2location.ioTokenInput');


                    if(response.data.profile != null){
                        if(response.data.profile.ip_provider.alias == 'ip-api.com'){
                            inptIpApiComEl.checked = true;
                            inptApiIpNetEl.checked = false;
                            inptIp2LocationIoEl.checked = false;
                        }

                        if(response.data.profile.ip_provider.alias == 'apiip.net'){
                            inptIpApiComEl.checked = false;
                            inptApiIpNetEl.checked = true;
                            inptIp2LocationIoEl.checked = false;
                            inptApiIpNetTokenInputEl.value = response.data.profile.ip_provider.token;
                        }

                        if(response.data.profile.ip_provider.alias == 'ip2location.io'){
                            inptIpApiComEl.checked = false;
                            inptApiIpNetEl.checked = false;
                            inptIp2LocationIoEl.checked = true;
                            inptIp2LocationIoTokenInputEl.value = response.data.profile.ip_provider.token;
                        }

                        ipCacheStatusEl = document.getElementById('ipCacheStatus');
                        if(response.data.profile.store_ips){
                            ipCacheStatusEl.checked = true;
                        } else {
                            ipCacheStatusEl.checked = false;
                        }

                        addonStatusEl = document.getElementById('addonStatus');
                        if(response.data.profile.status){
                            addonStatusEl.checked = true;
                        } else {
                            addonStatusEl.checked = false;
                        }
                        changeIpCache();
                        changeAddonStatus();
                    } else {
                        inptIpApiComEl.checked = true;
                        inptApiIpNetEl.checked = false;
                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        function getCardsData(){
            axios.get("{{ route('statamic.cp.cardsData') }}")
                .then(function (response) {
                    if(response.data.cards != null){
                        cardsEl = document.getElementById('divCards');
                        cardsEl.style.display = "block";

                        totalRequestsAbbreviatedEl = document.getElementById('totalRequestsAbbreviated');
                        totalRequestsAbbreviatedEl.innerText = response.data.cards.requests_all_abbreviated;

                        totalRequestsTotalEl = document.getElementById('totalRequestsTotal');
                        totalRequestsTotalEl.innerText = response.data.cards.requests_all;

                        totalRequestsSuccessEl = document.getElementById("totalRequestsSuccess");
                        totalRequestsSuccessEl.innerText = response.data.cards.requests_success;

                        totalCountriesEl = document.getElementById('totalCountries');
                        totalCountriesEl.innerText = response.data.cards.unique_countries;

                        topCountryEl = document.getElementById('topCountry');
                        topCountryEl.innerText = response.data.cards.top_country;

                        topCityEl = document.getElementById('topCity');
                        topCityEl.innerText = response.data.cards.top_city;

                        totalUrisEl = document.getElementById('totalUris');
                        totalUrisEl.innerText = response.data.cards.unique_uris;

                        topUriEl = document.getElementById('topUri');
                        topUriEl.innerText = response.data.cards.top_uri;

                        totalIpsEl = document.getElementById('totalIps');
                        totalIpsEl.innerText = response.data.cards.unique_ips;

                        topIpEl = document.getElementById('topIp');
                        topIpEl.innerText = response.data.cards.top_ip;
                    } else {
                        cardsEl = document.getElementById('divCardsEmpty');
                        cardsEl.style.display = "block";
                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        function getCache(){

            axios.get("{{route('statamic.cp.cache')}}")
                .then(function (response) {
                    if(response.data.cache != null){
                        latestUpdateEl = document.getElementById('latestUpdate');
                        latestUpdateEl.innerHTML = `<b>Last update</b>: ${response.data.cache.latest_update}` ;

                        ipCacheRecordsEl = document.getElementById('ipCacheRecords');
                        ipCacheRecordsEl.innerHTML = response.data.cache.cache_ips_records;

                        ipCacheSizeEl = document.getElementById('ipCacheSize');
                        ipCacheSizeEl.innerHTML = response.data.cache.cache_ips_str;

                        logCacheRecordsEl = document.getElementById('logCacheRecords');
                        logCacheRecordsEl.innerHTML = response.data.cache.cache_log_records;

                        logCacheSizeEl = document.getElementById('logCacheSize');
                        logCacheSizeEl.innerHTML = response.data.cache.cache_log_str;

                        appCacheRecordsEl = document.getElementById('appCacheRecords');
                        appCacheRecordsEl.innerHTML = response.data.cache.cache_app_records;

                        appCacheSizeEl = document.getElementById('appCacheSize');
                        appCacheSizeEl.innerHTML = response.data.cache.cache_app_str;

                    } else {
                        latestUpdateEl = document.getElementById('latestUpdate');
                        latestUpdateEl.innerHTML = `<b>Last update</b>: -` ;

                        ipCacheRecordsEl = document.getElementById('ipCacheRecords');
                        ipCacheRecordsEl.innerHTML = '-';

                        ipCacheSizeEl = document.getElementById('ipCacheSize');
                        ipCacheSizeEl.innerHTML = '-';

                        logCacheRecordsEl = document.getElementById('logCacheRecords');
                        logCacheRecordsEl.innerHTML = '-';

                        logCacheSizeEl = document.getElementById('logCacheSize');
                        logCacheSizeEl.innerHTML = '-';

                        appCacheRecordsEl = document.getElementById('appCacheRecords');
                        appCacheRecordsEl.innerHTML = '-';

                        appCacheSizeEl = document.getElementById('appCacheSize');
                        appCacheSizeEl.innerHTML = '-';

                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        function getUriData(timerange, destroy){
            if(timerange == 'all'){
                route = "{{ route('statamic.cp.uriData',['all']) }}"
            } else if(timerange == 'today') {
                route = "{{ route('statamic.cp.uriData',['today']) }}"
            } else if(timerange == 'week') {
                route = "{{ route('statamic.cp.uriData',['week']) }}"
            } else if(timerange == 'month') {
                route = "{{ route('statamic.cp.uriData',['month']) }}"
            }
            timerangeArr = ['all','today','week','month'];
            timerangeArr.forEach(el => {
                span = document.getElementById(`uriSpan-${el}`)
                if(el == timerange){
                    span.style.fontWeight = "bold"
                } else {
                    span.style.fontWeight = "normal"
                }
            });
            axios.get(route)
                .then(function (response) {
                    if(response.data.download == true){
                        if(timerange == "all"){
                            downUriEl = document.getElementById('divDownloadUri');
                            downUriEl.style.display = "block";
                        } else {
                            downUriEl = document.getElementById('divDownloadUri');
                            downUriEl.style.display = "none";
                        }
                    } else {
                        downUriEl = document.getElementById('divDownloadUri');
                        downUriEl.style.display = "none";
                    }

                    if(response.data.uris != null){
                        tableUriEmptyEl = document.getElementById('divUrisEmpty');
                        tableUriEmptyEl.style.display = "none";

                        tableUriEl = document.getElementById('divTableUri');
                        tableUriEl.style.display = "block";

                        dateUriEl = document.getElementById('uriDatesMessage');
                        dateUriEl.innerHTML = `Data from <b>${response.data.dates.start}</b> to <b>${response.data.dates.end}</b>`;

                        if(destroy){
                            tableURI.clear().destroy()
                            tableURI = new DataTable('#tableUri', {
                                columns: response.data.uris.columns,
                                data: response.data.uris.data,
                                scrollX: true,
                                order: response.data.uris.sorting
                            });

                        } else {
                            tableURI = new DataTable('#tableUri', {
                                columns: response.data.uris.columns,
                                data: response.data.uris.data,
                                scrollX: true,
                                order: response.data.uris.sorting
                            });
                        }
                    } else {
                        tableUriEmptyEl = document.getElementById('divUrisEmpty');
                        tableUriEmptyEl.style.display = "block";

                        tableUriEl = document.getElementById('divTableUri');
                        tableUriEl.style.display = "none";

                        dateUriEl = document.getElementById('uriDatesMessage');
                        dateUriEl.innerHTML = "";

                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        function getCitiesData(timerange, destroy){
            if(timerange == 'all'){
                route = "{{ route('statamic.cp.citiesData',['all']) }}"
            } else if(timerange == 'today') {
                route = "{{ route('statamic.cp.citiesData',['today']) }}"
            } else if(timerange == 'week') {
                route = "{{ route('statamic.cp.citiesData',['week']) }}"
            } else if(timerange == 'month') {
                route = "{{ route('statamic.cp.citiesData',['month']) }}"
            }
            timerangeArr = ['all','today','week','month'];
            timerangeArr.forEach(el => {
                span = document.getElementById(`locationSpan-${el}`)
                if(el == timerange){
                    span.style.fontWeight = "bold"
                } else {
                    span.style.fontWeight = "normal"
                }
            });

            axios.get(route)
                .then(function (response) {
                    if(response.data.download == true){
                        if(timerange == "all"){
                            downCitiesEl = document.getElementById('divDownloadCities');
                            downCitiesEl.style.display = "block";
                        } else {
                            downCitiesEl = document.getElementById('divDownloadCities');
                            downCitiesEl.style.display = "none";
                        }
                    } else {
                        downCitiesEl = document.getElementById('divDownloadCities');
                        downCitiesEl.style.display = "none";
                    }

                    if(response.data.cities != null){
                        tableCitiesEmptyEl = document.getElementById('divCitiesEmpty');
                        tableCitiesEmptyEl.style.display = "none";

                        tableCitiesEl = document.getElementById('divTableCity');
                        tableCitiesEl.style.display = "block";

                        dateCitiesEl = document.getElementById('citiesDatesMessage');
                        dateCitiesEl.innerHTML = `Data from <b>${response.data.dates.start}</b> to <b>${response.data.dates.end}</b>`;
                        if(destroy){
                            tableCities.clear().destroy()
                            tableCities = new DataTable('#tableCity', {
                                columns: response.data.cities.columns,
                                data: response.data.cities.data,
                                scrollX: true,
                                order: response.data.cities.sorting
                            });

                        } else {
                            tableCities = new DataTable('#tableCity', {
                                columns: response.data.cities.columns,
                                data: response.data.cities.data,
                                scrollX: true,
                                order: response.data.cities.sorting
                            });
                        }
                    } else {
                        tableCitiesEmptyEl = document.getElementById('divCitiesEmpty');
                        tableCitiesEmptyEl.style.display = "block";

                        tableCitiesEl = document.getElementById('divTableCity');
                        tableCitiesEl.style.display = "none";

                        dateCitiesEl = document.getElementById('citiesDatesMessage');
                        dateCitiesEl.innerHTML = "";
                    }

                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        function getDatesData(timerange, destroy){
            if(timerange == 'all'){
                route = "{{ route('statamic.cp.datesData',['all']) }}"
            } else if(timerange == 'today') {
                route = "{{ route('statamic.cp.datesData',['today']) }}"
            } else if(timerange == 'week') {
                route = "{{ route('statamic.cp.datesData',['week']) }}"
            } else if(timerange == 'month') {
                route = "{{ route('statamic.cp.datesData',['month']) }}"
            }
            timerangeArr = ['all','today','week','month'];
            timerangeArr.forEach(el => {
                span = document.getElementById(`dateSpan-${el}`)
                if(el == timerange){
                    span.style.fontWeight = "bold"
                } else {
                    span.style.fontWeight = "normal"
                }
            });

            axios.get(route)
                .then(function (response) {
                    if(response.data.download == true){
                        downDatesEl = document.getElementById('divDownloadDates');
                        downDatesEl.style.display = "block";

                    } else {
                        downDatesEl = document.getElementById('divDownloadDates');
                        downDatesEl.style.display = "none";
                    }

                    if(response.data.dates != null){
                        tableDateEmptyEl = document.getElementById('divDatesEmpty');
                        tableDateEmptyEl.style.display = "none";

                        tableDateEl = document.getElementById('divTableDate');
                        tableDateEl.style.display = "block";

                        dateMessageEl = document.getElementById('dateDatesMessage');
                        dateMessageEl.innerHTML = `Data from <b>${response.data.datesRange.start}</b> to <b>${response.data.datesRange.end}</b>`;
                        if(destroy){
                            tableDate.clear().destroy();
                            tableDate = new DataTable('#tableDate', {
                                columns: response.data.dates.columns,
                                data: response.data.dates.data,
                                scrollX: true,
                                order: response.data.dates.sorting
                            });

                        } else {
                            tableDate = new DataTable('#tableDate', {
                                columns: response.data.dates.columns,
                                data: response.data.dates.data,
                                scrollX: true,
                                order: response.data.dates.sorting
                            });
                        }
                    } else {
                        tableDateEmptyEl = document.getElementById('divDatesEmpty');
                        tableDateEmptyEl.style.display = "block";

                        tableDateEl = document.getElementById('divTableDate');
                        tableDateEl.style.display = "none";

                        dateMessageEl = document.getElementById('dateDatesMessage');
                        dateMessageEl.innerHTML = "";
                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        function filterTimeseries(){
            var start = "default";
            var end = "default";

            startDateTimeseriesEl = document.getElementById('startDateTimeseries');
            endDateTimeseriesEl = document.getElementById('endDateTimeseries');
            if(startDateTimeseriesEl.value != ''){

                var dateArray = startDateTimeseriesEl.value.split("-");
                var dateStart = new Date(dateArray[0], parseInt(dateArray[1], 10) - 1, dateArray[2]);
                var yearS = dateStart.getFullYear();
                var monthS = (dateStart.getMonth() + 1).toString().padStart(2, '0');
                var dayS = dateStart.getDate().toString().padStart(2, '0');
                start = `${yearS}-${monthS}-${dayS}`;
            }

            if(endDateTimeseriesEl.value != ''){
                var dateArray = endDateTimeseriesEl.value.split("-");
                var dateEnd = new Date(dateArray[0], parseInt(dateArray[1], 10) - 1, dateArray[2]);
                var yearE = dateEnd.getFullYear();
                var monthE = (dateEnd.getMonth() + 1).toString().padStart(2, '0');
                var dayE = dateEnd.getDate().toString().padStart(2, '0');
                end = `${yearE}-${monthE}-${dayE}`;
            }

            getTimeseries(start,end, true);
        }

        function getTimeseries(startFilter,endFilter,destroy){
            axios.post("{{ route('statamic.cp.timeseriesData') }}", {
                startFilter: startFilter,
                endFilter: endFilter
            })
                .then(function (response) {
                    if(response.data.dates != null){
                        filterDatesEl = document.getElementById('divFilterDates');
                        filterDatesEl.style.display = "block";

                        var dataset = response.data.traces;
                        (async function() {
                            const config = {
                                type: 'line',
                                data: dataset,
                                options: {
                                    responsive: true
                                },
                            };
                            if(destroy){
                                chartTimeseries.destroy();
                            }
                            chartTimeseries = new Chart(document.getElementById('dailyRequests'),config);
                        })();
                    } else {
                        divTimeseriesEl = document.getElementById('divTimeseriesEmpty');
                        divTimeseriesEl.style.display = "block";

                        filterDatesEl = document.getElementById('divFilterDates');
                        filterDatesEl.style.display = "none";
                    }

                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );

        }

        function plotMap(timerange){
            if(timerange == 'all'){
                route = "{{ route('statamic.cp.geojsonDates',['all']) }}"
                routeIframe = "{{route('statamic.cp.geoMap',['all'])}}"
            } else if(timerange == 'today') {
                route = "{{ route('statamic.cp.geojsonDates',['today']) }}"
                routeIframe = "{{route('statamic.cp.geoMap',['today'])}}"
            } else if(timerange == 'week') {
                route = "{{ route('statamic.cp.geojsonDates',['week']) }}"
                routeIframe = "{{route('statamic.cp.geoMap',['week'])}}"
            } else if(timerange == 'month') {
                route = "{{ route('statamic.cp.geojsonDates',['month']) }}"
                routeIframe = "{{route('statamic.cp.geoMap',['month'])}}"
            }

            timerangeArr = ['all','today','week','month'];
            timerangeArr.forEach(el => {
                span = document.getElementById(`plotMapSpan-${el}`)
                if(el == timerange){
                    span.style.fontWeight = "bold"
                } else {
                    span.style.fontWeight = "normal"
                }
            });

            axios.get(route)
                .then(function (response) {
                    if(response.data.dates != null){
                        divMapEmptyEl = document.getElementById('divMapEmpty');
                        divMapEmptyEl.style.display = "none";

                        divMapEl = document.getElementById('divMapIframe');
                        divMapEl.style.display = "block";

                        mapMessageEl = document.getElementById('mapDatesMessage');
                        mapMessageEl.innerHTML = `Data from <b>${response.data.dates.start}</b> to <b>${response.data.dates.end}</b>`;

                        mapEl = document.getElementById('geoMapIframe');
                        mapEl.src = routeIframe;
                    } else {
                        divMapEmptyEl = document.getElementById('divMapEmpty');
                        divMapEmptyEl.style.display = "block";

                        divMapEl = document.getElementById('divMapIframe');
                        divMapEl.style.display = "none";

                        mapMessageEl = document.getElementById('mapDatesMessage');
                        mapMessageEl.innerHTML = "";

                    }
                })
                .catch(function (error) {
                    console.error(error);
                })
                .finally(function () {}
            );
        }

        $(document).ready(function() {
            getCardsData();
            getTimeseries('default','default',false);
            plotMap('all');
            getUriData('all', false);
            getCitiesData('all', false);
            getDatesData('all', false);
            getCache();
            getProfile();
        });
    </script>
@stop
