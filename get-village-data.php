<?php

// https://www.ris.gov.tw/documents/html/5/1/167.html
// https://www.ris.gov.tw/documents/html/5/1/168.html
system("curl https://www.ris.gov.tw/documents/data/5/1/RSCD0102.txt | iconv -f big5 > county.txt");
system("curl https://www.ris.gov.tw/documents/data/5/1/RSCD0103.txt | iconv -f big5 > town.txt");
system("wget -O village.txt https://www.ris.gov.tw/documents/data/5/1/11109VillageUTF8.txt");
