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
        return storage_path("logs/geo-analytics/$path");
    }
}

if (! function_exists('geo_permissions_path')) {
    /**
     * Add the same user and group permissions to a path as in storage_path().
     *
     * @param  string  $path
     * @return string
     */
    function geo_permissions_path($path)
    {

        $user = (posix_getpwuid(fileowner(storage_path())))['name'];
        $group = (posix_getpwuid(filegroup(storage_path())))['name'];

        chown($path, $user);
        chgrp($path, $group);
    }
}

