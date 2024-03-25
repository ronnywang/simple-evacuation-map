<?php

class Helper
{
    protected static $_towns = null;
    protected static $_villages = null;
    protected static $_village_full_name = null;

    public static function http($url, $return_file = false)
    {
        $cache_file = __DIR__ . '/cache/' . str_replace('/', '_', $url);
        if (strlen(basename($cache_file)) > 255) {
            $cache_file = __DIR__ . '/cache/' . substr(basename($cache_file), 0, 200) . '-' . md5($url);
        }

        if (file_exists($cache_file) and filesize($cache_file) > 0) {
            if ($return_file) {
                return $cache_file;
            }
            return file_get_contents($cache_file);
        }
        // encode https://foo.com/中文中文/中文中文 to https://foo.com/%E4%B8%AD%E6%96%87%E4%B8%AD%E6%96%87/%E4%B8%AD%E6%96%87%E4%B8%AD%E6%96%87
        $url = preg_replace_callback('/[^:?=&%\/A-Za-z0-9_\-\.!~\*\'\(\)]/', function($matches) {
            return rawurlencode($matches[0]);
        }, $url);
        $ch = curl_init($url);
        // useragent chrome
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // output to $cache file
        curl_setopt($ch, CURLOPT_FILE, fopen($cache_file, 'w'));
        if (strpos($url, 'dpcwh.chfd.gov.tw') or strpos($url, 'www.taichung.gov.tw')) {
            // ssl verify disable
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        $html = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($info['http_code'] != 200) {
            unlink($cache_file);
            throw new Exception("HTTP code: {$info['http_code']} $url");
        }
        curl_close($ch);
        if ($return_file) {
            return $cache_file;
        }
        return file_get_contents($cache_file);
    }

    protected static function getTowns()
    {
        if (!is_null(self::$_towns)) {
            return;
        }
        $fp = fopen(__DIR__ . '/town.txt', 'r');
        $map = [];
        $fullname = [];
        while ($line = fgets($fp)) {
            $line = trim($line);
            list($id, $name) = explode('=', $line, 2);
            $fullname[$id] = str_replace('臺灣省', '', $name);
            $fullname[$id] = str_replace('福建省', '', $fullname[$id]);
            if (substr($id, -3) != '000') {
                $parent_id = sprintf("%05d000", substr($id, 0, 5));
                if (isset($map[$parent_id])) {
                    $name = str_replace($map[$parent_id], '', $name);
                }
            }
            $map[$id] = $name;
        }
        self::$_towns = $map;

        $fp = fopen(__DIR__ . '/village.txt', 'r');
        self::$_villages = [];
        self::$_village_full_name = [];
        while ($values = fgetcsv($fp)) {
            list($village_id, $town_id, $name) = $values;
            self::$_villages[$village_id] = $name;

            self::$_village_full_name[$fullname[$town_id] . $name] = $village_id;
        }
    }

    public static function getTownId($county_id, $town_name)
    {
        self::getTowns();
        foreach (self::$_towns as $id => $name) {
            if (substr($id, 0 , 5) != $county_id) {
                continue;
            }
            if ($name === $town_name) {
                return $id;
            }
        }
        throw new Exception("Town not found: $county_id, $town_name");
    }

    public static function getVillageIdByFullName($name)
    {
        self::getTowns();
        if (isset(self::$_village_full_name[$name])) {
            return self::$_village_full_name[$name];
        }
        throw new Exception("Village not found: $name");
    }

    public static function getVillageId($town_id, $village_name)
    {
        self::getTowns();
        foreach (self::$_villages as $id => $name) {
            if (strpos($id, $town_id) !== 0) {
                continue;
            }
            if ($name === $village_name) {
                return $id;
            }
        }
        throw new Exception("Village not found: $town_id, $village_name");
    }
}
