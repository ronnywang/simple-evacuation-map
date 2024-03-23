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
        $crawls['09020'] = function() { // 金門縣
            $entry = 'https://kmfb.kinmen.gov.tw/News_Photo.aspx?n=A6CCA2F56E78991C&sms=59691E739AAFFB67';
            $doc = new DOMDocument();
            @$doc->loadHTML(Helper::http($entry));
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                $text = $a_dom->nodeValue;
                if (preg_match('/(.*)簡易疏散避難地圖/', $text, $matches)) {
                    $townname = $matches[1];
                } elseif (strpos($text, 'Township') !== false) {
                    $townname = '';
                } else {
                    continue;
                }
                $url = 'https://kmfb.kinmen.gov.tw/' . $a_dom->getAttribute('href');
                $doc2 = new DOMDocument;
                @$doc2->loadHTML(Helper::http($url));
                foreach ($doc2->getElementsByTagName('a') as $a_dom) {
                    if (preg_match('#^\d+年(.*)#u', $a_dom->getAttribute('data-alt'), $matches)) {
                        $village_name = trim($townname . $matches[1]);
                        $village_id = Helper::getVillageIdByFullName($village_name);
                        self::addLog($village_id, "tw.all", $a_dom->getAttribute('href'), '');
                    } elseif (preg_match('#(金門縣.*)簡易疏散避難圖$#', $a_dom->getAttribute('data-alt'), $matches)) {
                        if (strpos($matches[1], '金門縣烈嶼鄉鄉') !== false) {
                            $matches[1] = str_replace('金門縣烈嶼鄉鄉', '金門縣烈嶼鄉', $matches[1]);
                        }
                        $village_id = Helper::getVillageIdByFullName($matches[1]);
                        self::addLog($village_id, "tw.all", $a_dom->getAttribute('href'), '');
                    } elseif (preg_match('#金門縣(.*)簡易疏散避難圖-英文_(page-|p)(\d+)#', $a_dom->getAttribute('data-alt'), $matches)) {
                        $town_id = Helper::getTownId('09020', $matches[1]);
                        $village_id = $town_id . sprintf("%03d", intval($matches[3]));
                        self::addLog($village_id, "en.all", $a_dom->getAttribute('href'), '');
                    } elseif (preg_match('#防災避難地圖-(.*)各里\d+_page-(\d+)#', $a_dom->getAttribute('data-alt'), $matches)) {
                        $town_id = Helper::getTownId('09020', $matches[1]);
                        $village_id = $town_id . sprintf("%03d", intval($matches[2]));
                        self::addLog($village_id, "tw.all", $a_dom->getAttribute('href'), '');
                    }
                }
            }
        };
        $crawls['10007'] = function() { // 彰化縣
            $entry = 'https://dpcwh.chfd.gov.tw/info.aspx?Type=2';
            $doc = new DOMDocument();
            $content = Helper::http($entry);
            $content = str_replace('<head>', '<head><meta charset="utf-8">', $content);
            @$doc->loadHTML($content);
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                if (!preg_match('#(.*)村里簡易疏散避難圖.pdf#u', $a_dom->nodeValue, $matches)) {
                    continue;
                }
                $town_id = Helper::getTownId('10007', $matches[1]);

                $pdf_url = $a_dom->getAttribute('href');
                $pdf_url = preg_replace_callback('#/([^/]+).pdf#', function($matches) {
                    return '/' . urlencode($matches[1]) . '.pdf';
                }, $pdf_url);
                $pdf_url = "https://dpcwh.chfd.gov.tw/" . $pdf_url;
                $pdf_content = Helper::http($pdf_url);
                file_put_contents('tmp.pdf', $pdf_content);
                $content = `pdftotext tmp.pdf /dev/stdout`;
                preg_match_all('#(彰化縣.*)(水災|地震)簡易疏散避難圖#u', $content, $matches);
                foreach ($matches[1] as $idx => $village_name) {
                    error_log($village_name);
                    // 彰化縣鹿港鎮頂厝里、鹿和里、鹿東里
                    if (strpos($village_name, '、')) {
                        preg_match('#(彰化縣.*[鄉鎮市區])(.*)#u', $village_name, $matches2);
                        foreach (explode('、', $matches2[2]) as $village_name) {
                            $village_id = Helper::getVillageIdByFullName($matches2[1] . $village_name);
                            $type = $matches[2][$idx] == '水災' ? 'flood' : 'earthquake';
                            self::addLog($village_id, "tw.{$type}", $pdf_url, $idx + 1);
                        }
                        continue;
                    }
                    if (strpos($village_name, '縣庄村') === false) {
                        $village_name = str_replace('彰化縣芬園鄉縣', '彰化縣芬園鄉', $village_name);
                    }
                    $village_id = Helper::getVillageIdByFullName($village_name);
                    $type = $matches[2][$idx] == '水災' ? 'flood' : 'earthquake';
                    self::addLog($village_id, "tw.{$type}", $pdf_url, $idx + 1);
                }
            }

        };

        $crawls['09007'] = function() { // 連江縣
            for ($page = 1; ; $page ++) {
                $cache_file = __DIR__ . '/cache/09007-' . $page . '.json';
                if (!file_exists($cache_file)) {
                    system("curl 'https://www.lcfd.gov.tw/disaster/wp-admin/admin-ajax.php' -XPOST -d'action=sf-search&data%5Bsearch-id%5D=datamap&data%5Bpage%5D=$page' > $cache_file");
                }
                $json = json_decode(file_get_contents($cache_file));
                if (!$json or !$json->result) {
                    break;
                }
                foreach ($json->result as $record) {
                    //         <a href="https://www.lcfd.gov.tw/disaster/wp-content/uploads/2021/07/003連江縣北竿鄉橋仔村簡易疏散避難地圖EN-scaled.jpg" >Qiaozi Village, Beigan Township Simple Evacuation Map(PDF)</a>
                    //         // 連江縣南竿鄉介壽村簡易疏散避難地圖
                    if (!preg_match('#<a href="([^"]+)"#u', $record, $matches)) {
                        continue;
                    }
                    $url = $matches[1];
                    if (!preg_match('#(連江縣.*)簡易疏散避難地圖(EN)?#', $url, $matches)) {
                        continue;
                    }
                    $village_id = Helper::getVillageIdByFullName($matches[1]);
                    if (count($matches) > 2 and $matches[2] == 'EN') {
                        self::addLog($village_id, 'en.all', $url);
                    } else {
                        self::addLog($village_id, 'tw.all', $url);
                    }
                }
            }
        };
        $crawls['64000'] = function() { // 高雄市
            $entry = 'https://precaution.kcg.gov.tw/main/index.aspx';
            $doc = new DOMDocument();
            $content = Helper::http($entry);
            $content = str_replace('<head>', '<head><meta charset="utf-8">', $content);

            @$doc->loadHTML($content);
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                if (!preg_match('#area\/(\d+)\.aspx#', $a_dom->getAttribute('href'), $matches)) {
                    continue;
                }
                $name = $a_dom->nodeValue . '區';
                $town_id = Helper::getTownId('64000', $name);

                $url = 'https://precaution.kcg.gov.tw/area/' . $matches[1] . '.aspx';
                $doc2 = new DOMDocument();
                $content = Helper::http($url);
                $content = str_replace('<head>', '<head><meta charset="utf-8">', $content);
                @$doc2->loadHTML($content);
                foreach ($doc2->getElementsByTagName('a') as $a_dom2) {
                    if (!preg_match('#.*里$#', $a_dom2->nodeValue)) {
                        continue;
                    }
                    $name = trim($a_dom2->nodeValue);
                    $url = $a_dom2->getAttribute('href');
                    $url = str_replace('../', 'https://precaution.kcg.gov.tw/', $url);
                    $village_id = Helper::getVillageId($town_id, $name);
                    self::addLog($village_id, 'tw.all', $url);
                }
            }
        };

        $crawls['10018'] = function() { // 新竹市
            $cache_file = __DIR__ . '/cache/10018.html';
            if (!file_exists($cache_file)) {
                system(sprintf("curl -dpageSize=1000 -XPOST 'https://119.hccg.gov.tw/chhcfd/app/data/query?module=evacuationMap&id=45' > %s", $cache_file));
            }
            $doc = new DOMDocument();
            @$doc->loadHTML(file_get_contents($cache_file));
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                if (!preg_match('#(新竹市.*里)簡易疏散避難地圖#u', $a_dom->getAttribute('title'), $matches)) {
                    continue;
                }
                $url = 'https://119.hccg.gov.tw' . $a_dom->getAttribute('href');
                $url = str_replace("\n", "", $url);
                $url = str_replace("\r", "", $url);

                $village_id = Helper::getVillageIdByFullName($matches[1]);
                self::addLog($village_id, 'tw.all', $url, 1);
                self::addLog($village_id, 'en.all', $url, 2);
            }

            
        };
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
