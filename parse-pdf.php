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

    $pdf_content = Helper::http($values['url']);
    if (strpos($values['url'], '.jpg')) {
        mkdir($target);
        file_put_contents($target . '/image.jpg', $pdf_content);
        file_put_contents($target . '/page-html.html', '<img src="image.jpg" style="width: 100%;">');
        continue;
    }
    file_put_contents(__DIR__ . '/tmp.pdf', $pdf_content);


    if ($page = $values['note']) {
        $cmd = sprintf("pdftohtml -c tmp.pdf -f %d -l %d -s %s",
            $page,
            $page,
            escapeshellarg($target . '/page')
        );
    } else {
        $cmd = sprintf("pdftohtml -c tmp.pdf -s %s",
            escapeshellarg($target . '/page')
        );
    }
    mkdir($target);
    exec($cmd, $output, $return_var);
    if ($return_var !== 0) {
        echo "Error: {$values['village_id']}\n";
        exit;
    }

}
