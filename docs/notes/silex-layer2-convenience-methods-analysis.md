# Silex Layer-2 Convenience Methods Analysis

第二层（`Silex\Application` 继承）便捷方法恢复可行性分析。已在 v3.6.0 实现。

---

## 方法清单与评估

| # | 方法 | Silex 签名 | 实现复杂度 | 恢复价值 | 建议 |
|---|------|-----------|-----------|---------|------|
| 1 | `view` | `view(callable $callback, int $priority = 0): void` | 低 | **高** | ✅ 恢复 |
| 2 | `abort` | `abort(int $statusCode, string $message = '', array $headers = []): void` | 极低 | **高** | ✅ 恢复 |
| 3 | `redirect` | `redirect(string $url, int $status = 302): RedirectResponse` | 极低 | **中** | ✅ 恢复 |
| 4 | `json` | `json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse` | 极低 | **中** | ✅ 恢复 |
| 5 | `stream` | `stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse` | 极低 | **低** | ⚠️ 可选 |
| 6 | `sendFile` | `sendFile(string\|SplFileInfo $file, int $status = 200, array $headers = [], ?string $contentDisposition = null): BinaryFileResponse` | 低 | **低** | ⚠️ 可选 |
| 7 | `escape` | `escape(string $text, int $flags = ENT_COMPAT, ?string $charset = 'UTF-8'): string` | 极低 | **极低** | ❌ 不恢复 |
| 8 | `on` | `on(string $eventName, callable $callback, int $priority = 0): void` | 低 | **低** | ❌ 不恢复 |
| 9 | `off` | `off(string $eventName, callable $callback): void` | 低 | **极低** | ❌ 不恢复 |
| 10 | `subscribe` | `subscribe(EventSubscriberInterface $subscriber): void` | 低 | **极低** | ❌ 不恢复 |
| 11 | `mount` | `mount(string $prefix, callable\|ControllerCollection $controllers): void` | 中 | **低** | ❌ 不恢复 |
| 12 | `get/post/put/patch/delete/options/match` | 流式路由定义 | 高 | **低** | ❌ 不恢复 |
| 13 | `flush` | `flush(): void` | — | **无** | ❌ 不适用 |

---

## 逐项分析

### ✅ 建议恢复

#### 1. `view(callable $callback, int $priority = 0): void`

**原始行为**：注册 view handler，当控制器返回非 Response 值时被调用，将其转换为 Response。

**恢复理由**：
- 与 `before()` / `after()` / `error()` 同属"注册回调"类便捷方法，缺它不对称
- 当前替代方案是 Bootstrap Config `view_handlers`，但 boot 后无法动态追加
- 实现成本极低：追加到 `$this->viewHandlers[]`，boot 后直接注册到 ViewHandlerSubscriber

**实现**：

```php
public function view(callable $callback): void
{
    $this->viewHandlers[] = $callback;
}
```

> 注：Silex 的 `$priority` 参数控制 view handler 的调用顺序。当前 `ViewHandlerSubscriber` 按数组顺序遍历，不支持 priority。恢复时可忽略 priority（按注册顺序），或在 docblock 中说明。

#### 2. `abort(int $statusCode, string $message = '', array $headers = []): never`

**原始行为**：抛出 `HttpException`，终止请求处理。

**恢复理由**：
- 控制器中最常用的错误快捷方式之一（`$app->abort(404)`）
- 替代方案 `throw new HttpException(404)` 需要 use 声明 + 更长的代码
- 实现成本极低

**实现**：

```php
public function abort(int $statusCode, string $message = '', array $headers = []): never
{
    throw new HttpException($statusCode, $message, null, $headers);
}
```

#### 3. `redirect(string $url, int $status = 302): RedirectResponse`

**原始行为**：创建重定向响应。

**恢复理由**：
- 控制器中常用，比 `new RedirectResponse($url)` 更简洁
- 与 `path()` / `url()` 配合使用频率高：`return $kernel->redirect($kernel->path('login'))`

**实现**：

```php
public function redirect(string $url, int $status = 302): RedirectResponse
{
    return new RedirectResponse($url, $status);
}
```

#### 4. `json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse`

**原始行为**：创建 JSON 响应，自动序列化数据。

**恢复理由**：
- API 项目中使用频率极高
- 比 `new JsonResponse($data, $status, $headers)` 略短，且语义更清晰
- Silex 版本还支持 `$encodingOptions` 参数

**实现**：

```php
public function json(mixed $data = [], int $status = 200, array $headers = []): JsonResponse
{
    return new JsonResponse($data, $status, $headers);
}
```

### ⚠️ 可选恢复

#### 5. `stream(callable $callback, int $status = 200, array $headers = []): StreamedResponse`

**原始行为**：创建流式响应。

**分析**：
- 使用频率低于 `json` / `redirect`
- `render()` 已支持 StreamedResponse（传入 StreamedResponse 实例即可）
- 替代方案 `new StreamedResponse($callback, $status, $headers)` 差异不大
- 加回来无害，但价值有限

#### 6. `sendFile(string|SplFileInfo $file, ...): BinaryFileResponse`

**原始行为**：发送文件下载响应。

**分析**：
- 文件下载场景使用
- 替代方案 `new BinaryFileResponse($file)` 差异不大
- Silex 版本额外处理了 `$contentDisposition`（attachment/inline），有一定便利性

### ❌ 不建议恢复

#### 7. `escape(string $text): string`

- 就是 `htmlspecialchars()` 的包装，无任何附加逻辑
- 现代 PHP 开发中 Twig 自动转义，手动 escape 场景极少

#### 8–10. `on()` / `off()` / `subscribe()`

- EventDispatcher 的直接代理
- v3.x 架构中，事件监听通过 `MiddlewareInterface`、`error_handlers`、`view_handlers` 等高层抽象注册
- 暴露底层 EventDispatcher 操作会破坏封装，且与 boot 生命周期冲突（boot 前容器未就绪）

#### 11. `mount(string $prefix, callable|ControllerCollection $controllers)`

- Silex 的 ControllerCollection 概念已不存在
- `addRoutes(RouteCollection)` + RouteCollection prefix 已完全覆盖此功能
- 恢复需要引入新的抽象层，成本高收益低

#### 12. `get/post/put/patch/delete/options/match`

- 流式路由定义，依赖 Silex ControllerCollection + Route 对象
- v3.x 路由通过 YAML + `addRoute()` / `addRoutes()` 定义
- 恢复需要重建整个流式路由 DSL，成本极高
- 与 YAML 路由缓存机制冲突

#### 13. `flush()`

- 刷新 Pimple 容器的方法，v3.x 无 Pimple，完全不适用

---

## 建议方案

**恢复 4 个**（`view` + `abort` + `redirect` + `json`）：

- 实现成本：每个方法 1–5 行，总计 < 20 行代码
- 测试成本：每个方法 1–2 个 UT
- 迁移收益：覆盖下游最常用的控制器内便捷方法
- API 膨胀风险：极低，都是无状态的工厂/快捷方法

**可选加 2 个**（`stream` + `sendFile`）：

- 如果下游有文件下载/流式输出场景，加上更完整
- 否则可以不加，替代方案差异很小

**不恢复 7 个**（`escape` + `on/off/subscribe` + `mount` + 流式路由 + `flush`）：

- 要么已过时，要么与 v3.x 架构冲突，要么实现成本远超收益

---

## 实现状态

✅ 全部 6 个方法已在 v3.6.0 实现（hotfix/3.6.0）。

- `view()` / `abort()` / `redirect()` / `json()` / `stream()` / `sendFile()`
- 测试：`tests/Layer2ConvenienceMethodsTest.php`（14 tests, 29 assertions）
- CHANGELOG：`docs/changes/3.6/CHANGELOG.md`
