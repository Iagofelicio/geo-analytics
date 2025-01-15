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
        if(!function_exists('posix_getpwuid')){
            throw new \Exception("The required function 'posix_getpwuid' is missing. Please search for a solution to enable it if possible. For Windows users, consider using WSL (Windows Subsystem for Linux).");
        }

        $user = posix_getpwuid(fileowner(storage_path()));
        chown($path, $user['name']);

        $group = posix_getpwuid(filegroup(storage_path()));
        if(is_array($group) && array_key_exists('name', $group)) {
            chgrp($path, $group['name']);
        }
    }
}

