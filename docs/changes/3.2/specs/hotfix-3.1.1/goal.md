# Spec Goal: 路由子系统编程式注入与 Boot 后冻结

## 来源

- 分支: `hotfix/3.1.1`
- 需求文档:
  - `issues/ISS-3.0-L01-no-programmatic-route-injection.md`
  - `issues/ISS-3.0-L02-route-add-after-boot-silently-ineffective.md`

## 背景摘要

`oasis/http` v3.0 迁移到 Symfony MicroKernel 架构后，路由注册的唯一途径是通过 bootstrap config 中 `routing.path` 指向的 YAML 文件。MicroKernel 已有 `addMiddleware()`、`addControllerInjectedArg()` 等 boot 前编程式注入方法，但路由缺少对应的 API。

Symfony `Router::getMatcher()` 首次调用时编译 RouteCollection 并缓存 matcher，后续调用直接返回。`registerRouting()` 在 boot 过程中调用 `getMatcher()`，此后通过 `getRouter()->getRouteCollection()->add()` 添加的路由对已编译的 matcher 不可见，但调用不报错——这是一个静默失败的陷阱。

## 目标

- 在 `MicroKernel` 上提供 `addRoute(string $name, Route $route)` 和 `addRoutes(RouteCollection $routes)` 方法，允许 boot 前编程式注入路由
- 编程式路由在 `registerRouting()` 中、`getMatcher()` 调用前合并到 RouteCollection，覆盖同名 YAML 路由
- boot 完成后冻结路由表：`addRoute()` / `addRoutes()` 抛 `LogicException`；RouteCollection 层面通过 `FrozenRouteCollection` 包装器拦截所有写操作并抛异常

## 不做的事情（Non-Goals）

- 不支持 boot 后动态添加或修改路由
- 不改变现有 YAML 路由加载机制
- 不引入 RouteLoader 接口或 compiler pass 级别的路由扩展点

## Clarification 记录

### Q1: 编程式路由的 API 形态

- 选项: A) bootstrap config `routing.extra_routes` key / B) `routing` 下新增 key 接受 LoaderInterface / C) 顶层 key `routes` / D) 补充说明
- 回答: D — 直接在 MicroKernel 上加 `addRoute()` 方法，与 `addMiddleware()` 等一致

### Q2: boot 后的冻结粒度

- 选项: A) 仅 `addRoute()` 层面检查 / B) 同时在 `addRoute()` 和 RouteCollection 层面冻结（FrozenRouteCollection） / C) 仅 `addRoute()` 层面，`getRouter()` 不管 / D) 补充说明
- 回答: B — 双层冻结

### Q3: 是否支持批量添加

- 选项: A) 仅 `addRoute()` 单条 / B) 同时提供 `addRoutes(RouteCollection)` / C) 补充说明
- 回答: B — 同时提供

### Q4: 编程式路由与 YAML 路由的合并顺序

- 选项: A) 编程式覆盖 YAML（后注册优先） / B) YAML 优先，同名抛异常 / C) 交给 Symfony 默认行为 / D) 补充说明
- 回答: C — 不做特殊处理，合并顺序为 YAML 先、编程式后，Symfony RouteCollection 默认"后入覆盖先入"

## 约束与决策

- API 风格与现有 `addMiddleware()` 一致：boot 前调用，暂存到内部属性，boot 时消费
- 编程式路由在 YAML 路由之后合并，同名行为由 Symfony RouteCollection 默认语义决定（后入覆盖先入）
- boot 后冻结双层实施：MicroKernel 方法级 + RouteCollection 包装器级
- `FrozenRouteCollection` 拦截 `add()`、`addCollection()`、`remove()` 等写操作，抛出 `LogicException`
- 不需要兼容旧数据或旧行为——这是新增 API
