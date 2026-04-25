# Public API Compatibility Verification Results

Task 6（R13）的公共 API 兼容性验证结果。验证 2.5 版本的公共 API 在 3.x MicroKernel 下行为一致。

---

## 验证概要

| 项目 | 结果 |
|------|------|
| 验证日期 | 2026-04-26 |
| 验证脚本 | `test-task-6.sh` |
| 总检查项 | 56 |
| 通过 | 56 |
| 失败 | 0 |
| 全量测试 | 510 tests, 16499 assertions, OK |

---

## 6.1 Routing 兼容性（R13 AC1）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| routing testsuite | ✅ | 7 个路由测试全部通过 |
| SilexKernelWebTest | ✅ | 路由集成测试全部通过 |
| YAML route config 格式 | ✅ | `path` + `_controller` 默认值格式兼容 |
| Host-based 路由 | ✅ | `host: localhost` / `host: baidu.com` 正常匹配 |
| 路由参数提取 | ✅ | 数字 ID（`\d+`）、slug（`.*`）、通配符正常 |
| HTTP scheme 限制 | ✅ | `schemes: http` 限制 + HTTPS→HTTP 重定向正常 |
| 404 处理 | ✅ | 不存在的路由返回 404 + JSON 错误体 |
| 子路由（resource import） | ✅ | `resource: "subroutes.yml"` 正常加载 |
| 域名匹配 | ✅ | `{game}.baidu.com` 域名参数提取正常 |
| 配置参数替换 | ✅ | `%app.config1%` 在路由默认值中正常替换 |

**结论**：2.5 的路由注册方式（YAML route config 数组）在 3.x MicroKernel 下完全兼容。路由解析、参数提取、HTTP method 匹配、host-based 路由、scheme 限制均行为一致。

---

## 6.2 Controller 兼容性（R13 AC2）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| Request 注入 | ✅ | `Request $request` 参数注入正常 |
| 路由参数注入 | ✅ | `$id`、`$slug` 等路由参数自动注入正常 |
| GET/POST 参数链式获取 | ✅ | `ChainedParameterBagDataProvider` 正常工作 |
| Controller 参数自动注入 | ✅ | `JsonViewHandler` 通过 type-hint 自动注入 |
| 继承类匹配注入 | ✅ | `AbstractSmartViewHandler` type-hint 匹配 `JsonViewHandler` 实例 |
| Controller 返回值处理 | ✅ | 返回数组 → View Handler 转换为 JSON Response |
| `ExtendedArgumentValueResolver` | ✅ | 参数解析器正常工作 |

**结论**：2.5 的 controller 写法（参数注入、返回值类型）在 3.x 下完全兼容。Request 注入、路由参数注入、type-hint 自动注入、非 Response 返回值处理均行为一致。

---

## 6.3 View/Renderer 兼容性（R13 AC3）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| views testsuite | ✅ | 6 个 view 测试文件全部通过 |
| FallbackViewHandler | ✅ | HTML/JSON 渲染、错误渲染全部正常 |
| twig testsuite | ✅ | Twig 模板渲染正常 |
| JSON Content-Type | ✅ | `application/json` Content-Type 正确 |
| `RouteBasedResponseRendererResolver` | ✅ | html/page→HTML, api/json→JSON 路由格式解析正常 |

**结论**：2.5 的 view handler（JSON、HTML、Twig 渲染）在 3.x 下行为一致。Content-Type、响应体格式、模板变量传递均正常。

---

## 6.4 Security 兼容性（R13 AC4）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| security testsuite | ✅ | 5 个安全测试文件全部通过 |
| Pre-auth 认证流程 | ✅ | 无凭证→403, 错误凭证→403, 正确凭证→200 |
| Access Rule 角色检查 | ✅ | `ROLE_PARENT` 匹配正常 |
| Role Hierarchy | ✅ | `ROLE_PARENT` → `ROLE_CHILD` → `ROLE_USER` 继承正常 |
| Host-based Access Rule | ✅ | `bai(du\|da)\.com` host pattern 匹配正常 |
| Security 集成测试 | ✅ | admin/user 角色认证流程端到端通过 |
| `isGranted()` API | ✅ | 无 checker→false, 有 checker→委托, 异常→false |

**结论**：2.5 的 security 配置（firewall、access rule、authenticator、role hierarchy）在 3.x 下完全兼容。认证流程、拦截行为、角色检查均行为一致。

---

## 6.5 CORS 兼容性（R13 AC5）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| cors testsuite | ✅ | 2 个 CORS 测试文件全部通过 |
| Preflight 响应 | ✅ | 204 + CORS headers 正常 |
| Allowed origins | ✅ | 白名单 origin 匹配正常 |
| Allowed methods | ✅ | 方法限制正常 |
| Allowed headers | ✅ | 自定义 header 白名单正常 |
| Credentials | ✅ | `credentials_allowed` 处理正常 |
| Normal request CORS headers | ✅ | 非 preflight 请求的 CORS headers 正常 |
| Exposed headers | ✅ | `headers_exposed` 正常 |
| Multi-strategy 优先级 | ✅ | first match wins 策略正常 |
| CORS bootstrap 集成 | ✅ | bootstrap config → CORS subscriber 注册正常 |

**结论**：2.5 的 CORS 配置在 3.x 下行为一致。preflight 响应、allowed origins/methods/headers、credentials、exposed headers 均正常。

---

## 6.6 Bootstrap Config 兼容性（R13 AC6）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| SilexKernelTest | ✅ | bootstrap config 初始化测试全部通过 |
| configuration testsuite | ✅ | Symfony Config 校验测试全部通过 |
| integration testsuite | ✅ | bootstrap → routing/cors/twig/middleware 集成通过 |
| `trusted_proxies` | ✅ | IP 数组配置正常 |
| `trusted_header_set` | ✅ | 字符串常量 / 整数值配置正常 |
| `middlewares` | ✅ | `MiddlewareInterface` 实例数组校验正常 |
| `view_handlers` | ✅ | callable 数组校验正常 |
| `error_handlers` | ✅ | callable 数组校验正常 |
| `injected_args` | ✅ | 对象数组注入正常 |
| `cache_dir` | ✅ | 含 `routing.cache_dir`、`twig.cache_dir` 子目录 |
| 无效配置处理 | ✅ | 抛出 `InvalidConfigurationException` |
| `addExtraParameters` / `getParameter` | ✅ | 额外参数存取正常 |

**结论**：2.5 的 bootstrap config 数组在 3.x MicroKernel 下完全兼容。所有配置 key（routing、security、cors、twig、middlewares、providers、view_handlers、error_handlers、injected_args、trusted_proxies、trusted_header_set、behind_elb、trust_cloudfront_ips、cache_dir）均正常初始化，默认值行为一致。

---

## 6.7 Error Handling 兼容性（R13 AC7）

| 检查项 | 结果 | 说明 |
|--------|------|------|
| error-handlers testsuite | ✅ | 3 个错误处理测试文件全部通过 |
| `ExceptionWrapper` | ✅ | `DataValidationException`→400, `ExistenceViolationException`→404, 普通异常→原始 code |
| `JsonErrorHandler` | ✅ | 返回 array 含 `code`/`type`/`message`/`file`/`line` |
| Error → View 链 | ✅ | `ExceptionWrapper` → `FallbackViewHandler` → HTML 渲染正常 |
| API 错误响应 | ✅ | `ExceptionWrapper` → `FallbackViewHandler` → JSON 渲染正常 |
| 404 错误响应结构 | ✅ | JSON 含 `code` 字段 |
| `WrappedExceptionInfo` | ✅ | 数据结构正常 |

**结论**：2.5 的异常处理（`ExceptionWrapper`、`JsonErrorHandler`）在 3.x 下行为一致。HTTP status code、响应体结构、异常映射均正常。Error handler 返回非 Response 值时通过 view handler 链转换的行为也保持一致。

---

## 6.8 Breaking Changes（R13 AC8）

**未发现任何 breaking change。**

全量测试（510 tests, 16499 assertions）全部通过，7 个公共 API 维度的 56 项检查全部通过。2.5 版本的公共 API 在 3.x MicroKernel 下行为完全一致，无需在 PRP-008 迁移指南中记录 API 行为差异。

| 模块 | 2.5 行为 | 3.x 行为 | 影响 | 迁移方式 |
|------|---------|---------|------|---------|
| （无） | — | — | — | — |

> 注：虽然内部实现从 Silex 迁移到了 Symfony MicroKernel，但所有公共 API 的外部行为保持一致。迁移指南（PRP-008）应聚焦于内部依赖版本变更（PHP ≥8.5、Symfony 7.x、oasis/utils ^3.0、oasis/logging ^3.0）和构建工具变更（PHPStan 引入），而非 API 行为差异。
