<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 16:53
 */

/** @noinspection PhpIncludeInspection */
require_once __DIR__ . "/bootstrap.php";

$replacements = [
    'app.data' => 'abc',
    'job.nice' => 'great',
];
$value        = "%app.data%%%%job.nice%%app.data%%%%%%%";
$offset       = 0;
while (preg_match('#(%([^%].*?)%)#', $value, $matches, 0, $offset)) {
    $key = $matches[2];
    if (!array_key_exists($key, $replacements)) {
        $offset += strlen($key + 2);
        continue;
    }
    $value = preg_replace("/" . preg_quote($matches[1], '/') . "/", $replacements[$key], $value, 1);
    var_dump($value);
}
$value = preg_replace('#%%#', '%', $value);
var_dump($value);
//var_dump($matches);

