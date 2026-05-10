# ISS-3.7.0-L01 ErrorHandlerTrait HTTP Status Code Mismatch

| 字段 | 值 |
|------|-----|
| Severity | `[P1] major` |
| Status | `closed` |
| Found In | `v3.7.0` |
| Fixed In | `v3.8.0` |
| Related Test | `tests/ErrorHandlers/ErrorHandlerStatusCodeRegressionTest.php` |

---

## Description

`ErrorHandlerTrait` 中 `registerErrorHandlers()` 和 `registerSingleErrorHandler()` 的 view handler 分支，使用 handler 调用前计算的原始 `$code` 作为 HTTP 响应状态码，忽略了 error handler 返回对象中通过 `getCode()` 提供的实际状态码。

当 `ExceptionWrapper` 处理非 `HttpExceptionInterface` 异常时，会通过 `WrappedExceptionInfo::setCode()` 设置正确的 HTTP 状态码（400、404 等），但最终 HTTP 响应状态码仍为 500。

---

## Fix

在 `src/Kernel/ErrorHandlerTrait.php` 的两处 view handler 分支中，将 `$viewResponse->setStatusCode($code)` 改为：

```php
$statusCode = (\is_object($response) && \method_exists($response, 'getCode'))
    ? $response->getCode()
    : $code;
$viewResponse->setStatusCode($statusCode);
```

如果 error handler 返回的对象提供了 `getCode()` 方法，使用其返回值；否则回退到原始 `$code`。

---

## History

- `2026-05-10T06:40Z` `v3.7.0` [发现] 下游用户报告 HTTP 500 但 body code 400；编写 failing tests
- `2026-05-10T07:00Z` `v3.8.0` [修复] 修改 `ErrorHandlerTrait` 两处 view handler 分支的 status code 取值逻辑
