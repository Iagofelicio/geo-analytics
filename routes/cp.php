<?php

use Illuminate\Support\Facades\Route;
use Iagofelicio\GeoAnalytics\Controllers\GeoAnalyticsController;

Route::get('/geo-analytics/map/{timerange}', [GeoAnalyticsController::class, 'map'])->name("geoMap");
Route::get('/geo-analytics/geojsonData/{timerange}', [GeoAnalyticsController::class, 'geojsonData'])->name("geojsonData");
Route::get('/geo-analytics/geojsonDates/{timerange}', [GeoAnalyticsController::class, 'geojsonDates'])->name("geojsonDates");
Route::get('/geo-analytics/cardsData', [GeoAnalyticsController::class, 'cardsData'])->name("cardsData");
Route::get('/geo-analytics/citiesData/{timerange}', [GeoAnalyticsController::class, 'citiesData'])->name("citiesData");
Route::get('/geo-analytics/datesData/{timerange}', [GeoAnalyticsController::class, 'datesData'])->name("datesData");
Route::get('/geo-analytics/uriData/{timerange}', [GeoAnalyticsController::class, 'uriData'])->name("uriData");
Route::post('/geo-analytics/timeseriesData', [GeoAnalyticsController::class, 'timeseriesData'])->name("timeseriesData");
Route::get('/geo-analytics/download/{datasetName}', [GeoAnalyticsController::class, 'download'])->name("download");
Route::get('/geo-analytics/cache', [GeoAnalyticsController::class, 'cache'])->name("cache");
Route::get('/geo-analytics/profile', [GeoAnalyticsController::class, 'profile'])->name("profile");
Route::post('/geo-analytics/clearIpCache', [GeoAnalyticsController::class, 'clearIpCache'])->name("clearIpCache");
Route::post('/geo-analytics/clearLogCache', [GeoAnalyticsController::class, 'clearLogCache'])->name("clearLogCache");
Route::get('/geo-analytics/resetAppData', [GeoAnalyticsController::class, 'resetAppData'])->name("resetAppData");
Route::post('/geo-analytics/updatePreferences', [GeoAnalyticsController::class, 'updatePreferences'])->name("updatePreferences");


