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
        $crawls['10004'] = function() { // 新竹縣
            $entry = 'https://odm.hsinchu.gov.tw/DeepGlowing/Maps?page=1';
            $doc = new DOMDocument();
            @$doc->loadHTML(Helper::http($entry));
            $year = null;
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                $title = $a_dom->getAttribute('title');
                if (!preg_match('#新竹縣(\d+)年村里簡易疏散避難地圖-(.*版)#', $title, $matches)) {
                    continue;
                }
                if (is_null($year)) {
                    $year = $matches[1];
                } elseif ($year != $matches[1]) {
                    break;
                }
                error_log($title);
                if ($matches[2] == '中文版') {
                    $type = 'tw.all';
                } elseif ($matches[2] == '英文版') {
                    $type = 'en.all';
                } else {
                    throw new Exception("Unknown type: " . $matches[2]);
                }

                $href = 'https://odm.hsinchu.gov.tw' . $a_dom->getAttribute('href');
                $target = Helper::http($href, true);
                $rar = RarArchive::open($target);
                $entries = $rar->getEntries();
                foreach ($entries as $entry) {
                    $village_name = $entry->getName();
                    if (!strpos($village_name, 'pdf')) {
                        continue;
                    }

                    $tmp_file = __DIR__ . '/tmp.pdf';
                    $entry->extract(false, $tmp_file);
                    $content = `pdftotext $tmp_file /dev/stdout`;
                    if (!$content) {
                        throw new Exception("Failed to extract text from $tmp_file");
                    }

                    $name = $entry->getName();
                    if ($type == 'tw.all') {
                        if (!preg_match('#_(.*)_中文版_(\d+)幅#', $name, $matches)) {
                            throw new Exception("Unknown name: " . $name);
                        }
                        $town_id = Helper::getTownId('10004', $matches[1]);
                        for ($i = 1; $i <= $matches[2]; $i++) {
                            $village_id = $town_id . sprintf("%03d", $i);
                            self::addLog($village_id, $type, $href, "rar:{$name}:{$i}");
                        }
                    } else {
                        preg_match_all('#NO. (\d+)#', $content, $matches);
                        foreach ($matches[1] as $idx => $village_id) {
                            $idx += 1;
                            if (strlen($village_id) == 10) {
                                $village_id = substr($village_id, 0, 8) . '0' . substr($village_id, 8);
                            }
                            self::addLog($village_id, $type, $href, "rar:{$name}:{$idx}");
                        }
                    }
                }
            }
        };

        $crawls['10016'] = function() { // 澎湖縣
            $entry = 'https://www.phfd.gov.tw/home.jsp?id=25';
            $doc = new DOMDocument();
            @$doc->loadHTML(Helper::http($entry));
            foreach ($doc->getElementsByTagName('a') as $a_dom) {
                $href = $a_dom->getAttribute('href');
                if (strpos($href, 'dataserno=') === false) {
                    continue;
                }
                $href = 'https://www.phfd.gov.tw/' . $href;
                $village_name = '澎湖縣' . trim($a_dom->nodeValue);
                $village_id = Helper::getVillageIdByFullName($village_name);

                $doc2 = new DOMDocument();
                @$doc2->loadHTML(Helper::http($href));
                foreach ($doc2->getElementsByTagName('a') as $a_dom2) {
                    $href = $a_dom2->getAttribute('href');
                    if (strpos($href, '#image-') === false) {
                        continue;
                    }
                    if ($a_dom2->getAttribute('class')) {
                        continue;
                    }
                    $url = 'https://www.phfd.gov.tw/' . $a_dom2->getElementsByTagName('img')[0]->getAttribute('src');
                    if (!preg_match('#(.*)\((.*)\)#u', trim($a_dom2->nodeValue), $matches)) {
                        throw new Exception("Unknown village name: " . trim($a_dom2->nodeValue));
                    }
                    $village_name = '澎湖縣' . $matches[1];
                    if ($matches[2] == '中文版') {
                        $type = 'tw.all';
                    } else if ($matches[2] == '英文版') {
                        $type = 'en.all';
                    } else {
                        throw new Exception("Unknown type: " . $matches[2]);
                    }
                    $village_id = Helper::getVillageIdByFullName($village_name);

                    self::addLog($village_id, $type, $url);
                }
            }
        };

        $crawls['10020'] = function() { // 嘉義市
            $entry = 'https://dpinfo.chiayi.gov.tw/info.aspx?Type=1';
            $content = Helper::http($entry);
            preg_match_all('#assets/img/疏散避難地圖/([a-z]+)/([^.]*).jpg#', $content, $matches);
            foreach ($matches[1] as $idx => $type) {
                if ($type[1] == 'e') {
                    $townname = '嘉義市東區';
                } elseif ($type[1] == 'w') {
                    $townname = '嘉義市西區';
                } else {
                    throw new Exception("Unknown type: " . $type);
                }
                $village_name = $matches[2][$idx];
                if ($type[0] == 'w') {
                    $type = 'tw.flood';
                } elseif ($type[0] == 'e') {
                    $type = 'tw.earthquake';
                } elseif ($type[0] == 's') {
                    // 坡災
                    $type = 'tw.slope';
                } elseif ($type[0] == 'p') {
                    $type = 'tw.poison';
                } elseif ($type[0] == 'r') {
                    $type = 'tw.info';
                    $village_name = str_replace('避難資訊', '', $village_name);
                }
                $village_id = Helper::getVillageIdByFullName($townname . $village_name);
                self::addLog($village_id, $type, 'https://dpinfo.chiayi.gov.tw/' . $matches[0][$idx]);
            }
        };
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
