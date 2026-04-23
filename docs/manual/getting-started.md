# Getting Started

`oasis/http` 的安装与基本使用说明。

---

## 安装

```bash
composer require oasis/http
```

---

## Web Server 配置

所有示例依赖正确配置的 Web Server。请参考 [Silex Web Server Configuration](http://silex.sensiolabs.org/doc/master/web_servers.html)。

---

## 基本用法

```php
<?php

use Oasis\Mlib\Http\SilexKernel;
use Symfony\Component\HttpFoundation\Response;

$config = [];
$isDebug = true;
$kernel = new SilexKernel($config, $isDebug);

$kernel->get('/', function() {
    return new Response("Hello world!");
});

$kernel->run();
```

`$config` 是 bootstrap 配置数组，空数组即可运行。详细配置参见 [Bootstrap Configuration](bootstrap-configuration.md)。

---

## 前置知识

`SilexKernel` 继承自 [Silex](http://silex.sensiolabs.org/)，建议先了解 Silex 的基本概念。
