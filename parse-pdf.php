<?php

include(__DIR__ . '/Helper.php');
$fp = fopen(__DIR__ . '/village-log.csv', 'r');
$cols = fgetcsv($fp);
while ($rows = fgetcsv($fp)) {
    $values = array_combine($cols, $rows);
    $target = __DIR__ . "/html/{$values['village_id']}-{$values['type']}";
    if (file_exists($target . '/page-html.html')) {
        continue;
    }

    if (strpos($values['url'], '.jpg')) {
        mkdir($target);
        copy(Helper::http($values['url'], true),  $target . '/image.jpg');
        file_put_contents($target . '/page-html.html', '<img src="image.jpg" style="width: 100%;">');
        continue;
    }

    $page = $values['note'];
    if (preg_match('#^\d+$#', $page)) {
        $cmd = sprintf("pdftohtml -c %s -f %d -l %d -s %s",
            escapeshellarg(Helper::http($values['url'], true)),
            $page,
            $page,
            escapeshellarg($target . '/page')
        );
    } elseif (preg_match('#^rar:(.*):(\d+)$#', $page, $matches)) {
        $rarfile = Helper::http($values['url'], true);
        $rar = RarArchive::open($rarfile);
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
            if ($entry->getName() == $matches[1]) {
                $tmp_file = __DIR__ . '/tmp.pdf';
                $entry->extract(false, $tmp_file);
                $cmd = sprintf("pdftohtml -c tmp.pdf -f %d -l %d -s %s",
                    $matches[2],
                    $matches[2],
                    escapeshellarg($target . '/page')
                );
            }
        }
    } elseif (preg_match('#^zip:(\d+)$#', $page, $matches)) { // zip 裡面圖片
        $zipfile = Helper::http($values['url'], true);
        $zip = new ZipArchive;
        $zip->open($zipfile);
        $idx = $matches[1];
        $name = $zip->getNameIndex($idx);
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        mkdir($target);
        $content = $zip->getFromIndex($idx);
        file_put_contents($target . '/image.' . $ext, $content);
        file_put_contents($target . '/page-html.html', '<img src="image.' . $ext . '?v" style="width: 100%;">');
        continue;
    } else {
        try {
        $cmd = sprintf("pdftohtml -c %s -s %s",
            escapeshellarg(Helper::http($values['url'], true)),
            escapeshellarg($target . '/page')
        );
        } catch (Exception $e) {
            error_log($values['url']);
            continue;
        }
    }
    mkdir($target);
    error_log($cmd);
    exec($cmd, $output, $return_var);
    if ($return_var !== 0) {
        echo "Error: {$values['village_id']}\n";
        exit;
    }

}
