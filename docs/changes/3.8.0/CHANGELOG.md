# Changelog v3.8.0

修复 `ErrorHandlerTrait` 中 view handler 分支使用原始 `$code` 而非 error handler 返回对象的 `getCode()` 作为 HTTP 状态码的问题（ISS-3.7.0-L01）。

---

## Fixed

- **ISS-3.7.0-L01**：`ErrorHandlerTrait` 的 `registerErrorHandlers()` 和 `registerSingleErrorHandler()` 在 view handler 分支中，使用 handler 调用前计算的原始 `$code` 作为 HTTP 响应状态码，忽略了 error handler 返回对象通过 `getCode()` 提供的实际状态码。导致 `ExceptionWrapper` 处理非 `HttpExceptionInterface` 异常（如 `DataValidationException`→400、`ExistenceViolationException`→404）时，HTTP 响应状态码始终为 500

---

## Migration Impact

**向后兼容**：修复后 HTTP 状态码将正确反映 error handler 返回对象的 `getCode()` 值。如果下游代码依赖"非 HttpException 异常始终返回 HTTP 500"的行为（属于 bug 依赖），需要调整。
