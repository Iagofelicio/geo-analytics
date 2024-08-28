<?php

if (! function_exists('geo_storage_path')) {
    /**
     * Get the path to the addon geo storage folder.
     *
     * @param  string  $path
     * @return string
     */
    function geo_storage_path($path = '')
    {
        return __DIR__ . '/../storage/' . $path;
    }
}

