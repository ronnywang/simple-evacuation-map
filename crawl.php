<?php

include(__DIR__ . '/Helper.php');

class Crawler
{
    protected static $_log_fp;
    public static function addLog($village_id, $type, $url, $note = '')
    {
        error_log("Add log: $village_id, $type, $url, $note");
        fputcsv(self::$_log_fp, [$village_id, $type, $url, $note]);
    }

    public static function crawl()
    {
        $fp = fopen('village-log.csv', 'w');
        fputcsv($fp, ['village_id', 'type', 'url', 'note']);
        self::$_log_fp = $fp;

        $crawls = [];
        $crawls['10017'] = function() { // 基隆市
            $entry = 'https://www.klfd.klcg.gov.tw/tw/klfd1/2107-106563.html';
            $doc = new DOMDocument();
            @$doc->loadHTML(Helper::http($entry));
            $a_doms = [];
            foreach ($doc->getElementsByTagName('a') as $a) {
                if (!preg_match('#(.*)疏散避難圖_(.*)版#', $a->getAttribute('title'), $matches)) {
                    continue;
                }
                if ($matches[2] == '中文') {
                    $type = 'tw.all';
                } else if ($matches[2] == '英文') {
                    $type = 'en.all';
                } else {
                    throw new Exception("Unknown type: " . $matches[2]);
                }
                $town_id = Helper::getTownId('10017', $matches[1]);
                $pdf_url = $a->getAttribute('href');
                $pdf_url = "https://www.klfd.klcg.gov.tw" . $pdf_url;
                $pdf_content = Helper::http($pdf_url);
                file_put_contents('tmp.pdf', $pdf_content);
                $content = `pdftotext tmp.pdf /dev/stdout`;
                // 1001702-001 七堵區長興里疏散避難圖
                if ($type == 'tw.all') {
                    preg_match_all('#([0-9-]*) (.*區)(.*里)疏散避難圖#u', $content, $matches);
                    foreach ($matches[1] as $page_no => $village_id) {
                        list($town_id, $village_id) = explode('-', $village_id);
                        $village_id = $town_id . sprintf("%04d", $village_id);
                        self::addLog($village_id, $type, $pdf_url, $page_no + 1);
                    }
                } elseif ($type == 'en.all') {
                    preg_match_all('#([0-9-]*) Disaster Evacuation Map#', $content, $matches);
                    foreach ($matches[1] as $page_no => $village_id) {
                        list($town_id, $village_id) = explode('-', $village_id);
                        $village_id = $town_id . sprintf("%04d", $village_id);
                        self::addLog($village_id, $type, $pdf_url, $page_no + 1);
                    }
                }
            }
        };

        $crawls['63000120'] = function(){ // 北投區
            $entry = 'https://btdo.gov.taipei/News_Content.aspx?n=B4DBE8254528B23C&sms=F02C01E8561BEACB&s=1E95B578BB6F4AF2';
            $town_id = '63000120';

            $html = Helper::http($entry);
            $doc = new DOMDocument();
            @$doc->loadHTML($html);
            $a_doms = [];
            foreach ($doc->getElementsByTagName('a') as $a) {
                $a_doms[] = $a;
            }

            while ($a_dom = array_shift($a_doms)) {
                if (!preg_match('#(.*)疏散避難地圖#', $a_dom->getAttribute('title'), $matches)) {
                    continue;
                }
                $village_name = $matches[1];
                if (strpos($village_name, '各里')) {
                    continue;
                }
                if (strpos($village_name, '災害潛勢地區')) {
                    continue;
                }
                $village_id = Helper::getVillageId($town_id, $village_name);
                self::addLog($village_id, 'tw.all', $a_dom->getAttribute('href'));
                $a_dom = array_shift($a_doms);
                if (!preg_match('#Simple Evacuation Map#', $a_dom->getAttribute('title'))) {
                    throw new Exception($a_dom->getAttribute('title') . " is not Simple Evacuation Map");
                }
                self::addLog($village_id, 'en.all', $a_dom->getAttribute('href'));
            }
        };

        foreach ($crawls as $county_id => $crawl) {
            $crawl();
        }
    }
}


Crawler::crawl();
