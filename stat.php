<?php

$fp = fopen('village.txt', 'r');

$set = [];
while ($row = fgetcsv($fp)) {
    list($village_id, $town_id, $name) = $row;
    $county_id = substr($village_id, 0, 5);
    $set[$village_id] = 0;
}
fclose($fp);

$fp = fopen('village-log.csv', 'r');
$cols = fgetcsv($fp); // village_id, type, url, note
while ($rows = fgetcsv($fp)) {
    $village_id = $rows[0];
    if (!isset($set[$village_id])) {
        throw new Exception("{$village_id} not found");
    }
    $set[$village_id]++;
}
fclose($fp);

$stats = [];
foreach ($set as $village_id => $c) {
    $county_id = substr($village_id, 0, 5);
    $town_id = substr($village_id, 0, 8);
    foreach (['all', $county_id, $town_id] as $k) {
        if (!array_key_exists($k, $stats)) {
            $stats[$k] = [
                'hit' => 0,
                'total' => 0,
            ];
        }
        if ($c > 0) {
            $stats[$k]['hit']++;
        }
        $stats[$k]['total']++;
    }
}

$map = [];
foreach(['county', 'town'] as $k) {
    $fp = fopen($k . '.txt', 'r');
    while ($line = fgets($fp)) {
        $line = trim($line);
        list($county_id, $name) = explode('=', $line);
        $map[$county_id] = $name;
    }
}
fclose($fp);

uasort($stats, function($a, $b) {
    $delta = $a['hit'] / $a['total'] - $b['hit'] / $b['total'];
    if ($delta > 0) {
        return -1;
    } elseif ($delta < 0) {
        return 1;
    }

    $delta = $a['total'] - $b['total'];
    if ($delta > 0) {
        return -1;
    } elseif ($delta < 0) {
        return 1;
    }
});

$fp = fopen('stat.csv', 'w');
$map['all'] = '全國';
fputcsv($fp, ['level', 'id', 'name', 'hit', 'total']);
foreach (['all' => 3, 'county' => 5, 'town' => 8] as $level => $len) {
    foreach ($stats as $id => $stat) {
        if (strlen($id) != $len) {
            continue;
        }
        $name = $map[$id];
        fputcsv($fp, [$level, $id, $name, $stat['hit'], $stat['total']]);
    }
}
fclose($fp);
