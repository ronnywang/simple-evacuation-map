<?php

$fp = fopen('stat.csv', 'r');
$cols = fgetcsv($fp);
$stats = [];
$files = [];
while ($rows = fgetcsv($fp)) {
    $values = array_combine($cols, $rows);
    if ($values['level'] == 'all') {
        continue;
    }
    if ($values['level'] == 'county') {
        $target = "index.html";
    } else { 
        $target = substr($values['id'], 0, 5) . '.html';
    }

    if (!array_key_exists($target, $files)){ 
        $files[$target] = true;
        file_put_contents(__DIR__ . "/page/$target", <<<EOT
        <html><head><meta charset="utf-8"></head><body>
        <table border="1">
            <tr>
                <th>代碼</th>
                <th>行政區</th>
                <th>子行政區數</th>
                <th>已完成數</th>
            </tr>
EOT);
    }
    file_put_contents(__DIR__ . "/page/$target", <<<EOT
        <tr>
            <td>{$values['id']}</td>
            <td><a href="{$values['id']}.html">{$values['name']}</a></td>
            <td>{$values['total']}</td>
            <td>{$values['hit']}</td>
        </tr>
        EOT, FILE_APPEND);
}

foreach ($files as $file => $v) {
    file_put_contents(__DIR__ . "/page/$file", <<<EOT
        </table>
        </body>
        </html>
        EOT, FILE_APPEND);
}
$fp = fopen('village.txt', 'r');
$villages = [];
while ($rows = fgetcsv($fp)) {
    list($id, $townid, $name) = $rows;
    $villages[$id] = $name;
}
fclose($fp);

$fp = fopen('village-log.csv', 'r');
$cols = fgetcsv($fp);
$current_id = null;
while ($rows = fgetcsv($fp)) {
    $values = array_combine($cols, $rows);
    $town_id = substr($values['village_id'], 0, 8);

    if ($town_id != $current_id) {
        $current_id = $town_id;
        file_put_contents(__DIR__ . "/page/$current_id.html", <<<EOT
<html><head><meta charset="utf-8"></head><body>
<table border="1">
    <tr>
        <th>代碼</th>
        <th>村里</th>
        <th>種類</th>
        <th>單網頁</th>
        <th>原始位置</th>
    </tr>
EOT);
    }
    file_put_contents(__DIR__ . "/page/$current_id.html", <<<EOT
    <tr>
        <td>{$values['village_id']}</td>
        <td>{$villages[$values['village_id']]}</td>
        <td>{$values['type']}</td>
        <td><a href="../html/{$values['village_id']}-{$values['type']}/page-html.html">單網頁</a></td>
        <td><a href="{$values['url']}">原始</a></td>
    </tr>
EOT, FILE_APPEND);
};
