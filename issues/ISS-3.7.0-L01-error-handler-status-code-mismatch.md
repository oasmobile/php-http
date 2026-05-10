# ErrorHandlerTrait HTTP Status Code Mismatch

issues 层：记录已确认的线上 bug。

---

| 字段 | 值 |
|------|------|
| Severity | `[P1] major` |
| Status | `closed` |
| Found In | `v3.7.0` |
| Fixed In | `v3.8.0` |
| Related Test | `tests/ErrorHandlers/ErrorHandlerStatusCodeRegressionTest.php` |

---

## Description

`ErrorHandlerTrait` 中 `registerErrorHandlers` 和 `registerSingleErrorHandler` 的 view handler 分支，使用 handler 调用前计算的原始 `$code` 作为 HTTP 响应状态码，忽略了 error handler 返回对象中通过 `getCode()` 提供的实际状态码。

当 `ExceptionWrapper` 处理非 `HttpExceptionInterface` 异常（如 `DataValidationException`、`ExistenceViolationException`）时，会通过 `WrappedExceptionInfo::setCode()` 设置正确的 HTTP 状态码（400、404 等），但最终 HTTP 响应状态码仍为 500。

---

## Steps to Reproduce

1. 配置 `ExceptionWrapper` 作为 error handler，配合任意 view handler
2. 触发一个非 `HttpExceptionInterface` 的异常（如 `DataValidationException`）
3. 观察 HTTP 响应状态码

---

## Expected Behavior

HTTP 响应状态码应与 `WrappedExceptionInfo::getCode()` 一致（如 400）。

---

## Actual Behavior

HTTP 响应状态码为 500（原始 `$code`），而响应体中 `"code": 400`。

---

## Analysis

`src/Kernel/ErrorHandlerTrait.php` 中两处相同逻辑：

```php
$code = $exception instanceof HttpExceptionInterface
    ? $exception->getStatusCode()
    : 500;

$response = $handler($exception, $request, $code);

// ...
} elseif ($response !== null) {
    foreach ($kernel->getViewHandlers() as $viewHandler) {
        $viewResponse = $viewHandler($response, $request);
        if ($viewResponse instanceof Response) {
            $viewResponse->setStatusCode($code);  // ← 使用原始 $code，应使用 $response->getCode()
            // ...
        }
    }
}
```

影响范围：所有通过 `ExceptionWrapper` 处理的非 `HttpExceptionInterface` 异常，包括：
- `DataValidationException` → 应返回 400，实际返回 500
- `ExistenceViolationException` → 应返回 404，实际返回 500

受影响方法：
- `registerErrorHandlers()`（配置阶段注册的 error handler）
- `registerSingleErrorHandler()`（boot 后通过 `error()` 注册的 error handler）

---

## History

- `2026-05-10T06:40Z` `v3.7.0` [发现] 下游用户报告 `/panel/api/sp-count/list` 缺少 mandatory 参数时 HTTP 500 但 body code 400；确认为 `ErrorHandlerTrait` 通用问题，编写 failing tests 验证
- `2026-05-10T07:00Z` `v3.8.0` [修复] 修改 `ErrorHandlerTrait` 两处 view handler 分支，status code 取自 handler 返回对象的 `getCode()`
