# Project

`oasis/http` — 基于 Silex 微框架的 HTTP 组件，提供路由、安全、CORS、模板、中间件等 Web 应用基础能力。

---

## 技术栈

| 层 | 技术 |
|----|------|
| 语言 | PHP ≥ 7.0 |
| 框架 | Silex 2.x（已 archived，基于 Symfony 4 组件） |
| DI | Pimple |
| 模板 | Twig 1.x |
| HTTP | Symfony HttpFoundation 4.x |
| 路由 | Symfony Routing 4.2.x（YAML 配置 + 缓存） |
| 安全 | Symfony Security 4.x |
| HTTP 客户端 | Guzzle 6.x |
| 测试 | PHPUnit 5.x |
| 包管理 | Composer |

---

## 命名空间

- 源码：`Oasis\Mlib\Http\` → `src/`
- 测试：`Oasis\Mlib\Http\Test\` → `ut/`

---

## 核心入口

- `src/SilexKernel.php` — 核心类，继承 `Silex\Application`，通过 bootstrap config 数组初始化

---

## 构建与测试命令

```bash
# 安装依赖
composer install

# 运行全量测试
./vendor/bin/phpunit

# 运行指定 suite
./vendor/bin/phpunit --testsuite cors
./vendor/bin/phpunit --testsuite security
./vendor/bin/phpunit --testsuite twig
./vendor/bin/phpunit --testsuite aws
./vendor/bin/phpunit --testsuite exceptions

# 对重复失败的 suite，用 --log-junit 输出日志以缩小定位
./vendor/bin/phpunit --testsuite <suite> --log-junit build/junit-<suite>.xml
```

---

## 测试 Suite

| Suite | 文件 |
|-------|------|
| all | `SilexKernelTest`, `SilexKernelWebTest`, `FallbackViewHandlerTest` |
| exceptions | `HttpExceptionTest` |
| cors | `CrossOriginResourceSharingTest`, `CrossOriginResourceSharingAdvancedTest` |
| security | `SecurityServiceProviderTest`, `SecurityServiceProviderConfigurationTest` |
| twig | `TwigServiceProviderTest`, `TwigServiceProviderConfigurationTest` |
| aws | `ElbTrustedProxyTest` |

---

## 版本号位置

- `composer.json` → `version` 字段（当前未显式声明，由 Packagist / tag 管理）

---

## 敏感文件

- 无 `.env` 文件
- 测试中的密码为硬编码示例值，非真实凭据
