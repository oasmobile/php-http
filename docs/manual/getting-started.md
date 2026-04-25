# Getting Started

`oasis/http` 的安装与基本使用说明。

---

## 环境要求

- PHP >= 8.5

---

## 安装

```bash
composer require oasis/http
```

---

## Web Server 配置

所有示例依赖正确配置的 Web Server。请参考 [Symfony Web Server Configuration](https://symfony.com/doc/current/setup/web_server_configuration.html)。

---

## 基本用法

```php
<?php

use Oasis\Mlib\Http\MicroKernel;
use Symfony\Component\HttpFoundation\Response;

$config = [
    'routing' => [
        'path' => 'routes.yml',
        'namespaces' => ['App\\Controllers\\'],
    ],
];
$isDebug = true;
$kernel = new MicroKernel($config, $isDebug);

$kernel->run();
```

`$config` 是 bootstrap 配置数组，空数组即可运行。详细配置参见 [Bootstrap Configuration](bootstrap-configuration.md)。

---

## 前置知识

`MicroKernel` 基于 [Symfony HttpKernel](https://symfony.com/doc/current/components/http_kernel.html)，建议先了解 Symfony 的基本概念。
