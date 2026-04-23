# CORS

`oasis/http` 的跨域资源共享（Cross-Origin Resource Sharing）配置说明。

---

## 基本用法

```php
$config = [
    "cors" => [
        [
            'pattern' => '^/cors/.*',
            'origins' => ["my.second.domain.tld"],
        ],
    ],
];
```

---

## CORS Strategy 配置项

每个 CORS 策略可以是 `CrossOriginResourceSharingStrategy` 对象，或一个关联数组：

| 字段 | 类型 | 说明 |
|------|------|------|
| `pattern` | string / RequestMatcher | 匹配请求的模式 |
| `origins` | string / array | 允许的来源域 |
| `headers` | array | 允许的自定义请求头 |
| `headers_exposed` | array | 允许浏览器访问的响应头 |
| `max_age` | int | preflight 缓存时间，默认 86400 秒 |
| `credentials_allowed` | bool | 是否允许携带 cookie |

数组形式会自动转换为 `CrossOriginResourceSharingStrategy` 对象。

---

## 自定义策略

继承 `CrossOriginResourceSharingStrategy` 实现更复杂的逻辑，例如根据请求身份动态决定允许的 origin：

```php
class CustomCorsStrategy extends CrossOriginResourceSharingStrategy
{
    public function isOriginAllowed($origin)
    {
        if ($this->request && $this->request->attributes->has('sender')) {
            $this->originsAllowed = $sender->getOriginsAllowed();
        }

        return parent::isOriginAllowed($origin);
    }
}
```
