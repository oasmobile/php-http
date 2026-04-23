---
inclusion: fileMatch
fileMatchPattern: "ut/**,phpunit.xml"
description: 当读取测试文件或 phpunit.xml 时读取，包含测试运行环境信息
---

# Test Environment

## PHPUnit 运行方式

当前环境默认 PHP 版本为 8.5.3，但项目依赖（PHPUnit 5.x、Symfony 4.x 等）仅兼容 PHP 7.x。运行测试时必须使用 PHP 7.1：

```bash
/usr/local/opt/php@7.1/bin/php vendor/bin/phpunit
```

如需安装依赖，也需要绕过平台检查：

```bash
composer install --ignore-platform-reqs
```

## 路由缓存

`ut/cache/` 目录存放 Symfony Router 生成的路由缓存文件。如果测试出现路由相关的异常失败（如参数未替换、路由不匹配），先清除缓存文件再重试：

```bash
rm -f ut/cache/Project*.php ut/cache/Project*.php.meta
```

缓存文件已在 `ut/.gitignore` 中排除，不应提交到版本库。
