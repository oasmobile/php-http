<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 16:53
 */

$pattern = "#^(https?://)?(([a-z0-9\\.-]+)+\\.[a-z]+(:\\d+)?)(/.*)?\$#";
$urls    = [
    "https://abc.com/ddd",
    "http://abc.com/ddd",
    "abc.com/ddd",
    "http://abc.com/ddd?a=9",
    "http://abc.com.cn",
    "http://ab99c.com/ddd",
    "http://ab-c.com/ddd",
    "http://abc.com:8888/ddd",
];

foreach ($urls as $url) {
    preg_match($pattern, $url, $matches);
    var_dump($matches[2]);
}
