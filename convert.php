<?php

if ($argc < 4) {
    echo "Please specify paths to the import.xml, offers.xml and the result csv.\n";
    die(1);
}

$importPath = $argv[1];
$offersPath = $argv[2];
$csvPath = $argv[3];

$offersDOM = new DOMDocument('1.0', 'UTF-8');
$offersDOM->load($offersPath);

$importDOM = new DOMDocument('1.0', 'UTF-8');
$importDOM->load($importPath);

$offersXpath = new DOMXPath($offersDOM);
$offersXpath->registerNamespace('o', 'urn:1C.ru:commerceml_2');

$importXpath = new DOMXPath($importDOM);
$importXpath->registerNamespace('o', 'urn:1C.ru:commerceml_2');

$imports = $importXpath->query('//o:Товар', $importDOM);

$rows = [];

foreach ($imports as $importNode) {
    /** @var DOMElement $node */

    $images = extractImages($importXpath, $importNode);

    $id = $importXpath->query('./o:Ид', $importNode)[0]->nodeValue;
    $name = $importXpath->query('./o:Наименование', $importNode)[0]->nodeValue;
    $title = $name;
    $keywords = $importXpath->query('./o:Описание', $importNode)[0]->nodeValue;
    $article = $importXpath->query('./o:Артикул', $importNode)[0]->nodeValue;
    $pageDescription = $keywords;
    $annotation = $keywords;
    $description = $keywords;
    $imagesList = implode(', ', $images);

    $offerNodes = findOffersById($offersXpath, $id);

    if ($offerNodes === null) {
        $price = 0;
        $amount = 0;
        $variant = '';

        $rows[] = [
            '1C',
            $name,
            $price,
            $article,
            0,
            0,
            '',
            $variant,
            '',
            '',
            $amount,
            $title,
            $keywords,
            $pageDescription,
            $annotation,
            $description,
            $imagesList,
        ];
    } else {
        foreach ($offerNodes as $offerNode) {
            $price = (int)$offersXpath->query('.//o:ЦенаЗаЕдиницу', $offerNode)[0]->nodeValue;
            $amount = (int)$offersXpath->query('./o:Количество', $offerNode)[0]->nodeValue;
            $offerId = $offersXpath->query('.//o:Ид', $offerNode)[0]->nodeValue;
            $offerName = $offersXpath->query('./o:Наименование', $offerNode)[0]->nodeValue;

            $withVariant = strpos($offerId, '#') !== false;
            $variant = $withVariant ? extractVariant($offerName) : '';

            $rows[] = [
                '1C',
                $name,
                $price,
                $article,
                1,
                0,
                '',
                $variant,
                '',
                '',
                $amount,
                $title,
                $keywords,
                $pageDescription,
                $annotation,
                $description,
                $imagesList,
            ];
        }
    }
}

writeCsv($csvPath, $rows);

/**
 * @return DOMNodeList
 */
function findOffersById(DOMXPath $xpath, $id)
{
    $item = $xpath->query(sprintf('//o:Предложение[./o:Ид[. = "%s"]]', $id));
    if ($item->length > 0) {
        return $item;
    }

    $item = $xpath->query(sprintf('//o:Предложение[./o:Ид[starts-with(., "%s")]]', $id));
    if ($item->length > 0) {
        return $item;
    }

    return null;
}

function extractVariant($title)
{
    preg_match('~.*\(([^)]+)\)~', $title, $matches);
    return $matches[1];
}

function extractTitle($title)
{
    preg_match('~^[^(]+~', $title, $matches);
    return trim($matches[0]);
}

function extractImages(DOMXPath $xpath, DOMElement $importNode)
{
    $imageNodes = $xpath->query('./o:Картинка', $importNode);

    $images = [];

    foreach ($imageNodes as $imageNode) {
        /** @var DOMElement $imageNode */

        $images[] = basename($imageNode->nodeValue);

    }

    return $images;
}

function writeCsv($path, array $data)
{
    $fp = fopen($path, 'wb');

    $header = "Категория;Товар;Цена;Адрес;Видим;Хит;Бренд;Вариант;Старая цена;Артикул;Склад;Заголовок страницы;Ключевые слова;Описание страницы;Аннотация;Описание;Изображения\n";
    fwrite($fp, utfToCp1251($header));

    foreach ($data as $row) {
        foreach ($row as $i => $v) {
            $row[$i] = utfToCp1251($v);
        }

        fputcsv($fp, $row, ';');
    }

    fclose($fp);
}

function utfToCp1251($v)
{
    if (!is_string($v)) {
        return $v;
    }

    return iconv('utf-8', 'cp1251//TRANSLIT', $v);
}
