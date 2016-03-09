<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-03-09
 * Time: 16:53
 */

$pattern = "#^(https?://)?((((\\d+\\.){3}\\d+)|localhost|([a-z0-9\\.-]+)+\\.[a-z]+)(:\\d+)?)(/.*)?\$#";
$urls    = [
    "https://abc.com/ddd",
    "http://abc.com/ddd",
    "abc.com/ddd",
    "http://abc.com/ddd?a=9",
    "http://abc.com.cn",
    "http://ab99c.com/ddd",
    "http://ab-c.com/ddd",
    "http://abc.com:8888/ddd",
    "localhost/abc?ldld",
    "localhost:7777/abc",
    "http://127.0.0.1/ljl",
    "127.0.0.1:999",
    "7.5:999",
];

foreach ($urls as $url) {
    preg_match($pattern, $url, $matches);
    var_dump($matches[2]);
}
