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

$offers = $offersXpath->query('//o:Предложение', $offersDOM);

$rows = [];

foreach ($offers as $node) {
    /** @var DOMElement $node */

    $id = $offersXpath->query('./o:Ид', $node)[0]->nodeValue;
    $name = $offersXpath->query('./o:Наименование', $node)[0]->nodeValue;
    $price = (int)$offersXpath->query('.//o:ЦенаЗаЕдиницу', $node)[0]->nodeValue;
    $article = $offersXpath->query('./o:Артикул', $node)[0]->nodeValue;
    $amount = (int)$offersXpath->query('./o:Количество', $node)[0]->nodeValue;

    $withVariant = strpos($id, '#') !== false;
    $variant = $withVariant ? extractVariant($name) : '';
    $name = $withVariant ? extractTitle($name) : $name;

    $importNode = findItemById($importXpath, $id);
    $images = extractImages($importXpath, $importNode);

    $title = $importXpath->query('./o:Наименование', $importNode)[0]->nodeValue;
    $keywords = $importXpath->query('./o:Описание', $importNode)[0]->nodeValue;
    $pageDescription = $keywords;
    $annotation = $keywords;
    $description = $keywords;
    $imagesList = implode(', ', $images);

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
}

writeCsv($csvPath, $rows);

/**
 * @return DOMElement
 */
function findItemById(DOMXPath $xpath, $id)
{
    $id = preg_replace('~#.*$~', '', $id);

    $item = $xpath->query(sprintf('//o:Товар[./o:Ид[. = "%s"]]', $id));
    return $item[0];
}

function extractVariant($title)
{
    preg_match('~\(([^)]+)\)~', $title, $matches);
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

    return iconv('utf-8', 'cp1251', $v);
}
