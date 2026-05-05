# Changelog v3.6.2

提取 CloudFront IP 获取逻辑为可测试工厂方法 + 补充 HTTP 路径测试覆盖。

---

## Changed

- 提取 `createAwsIpRangesClient()` 工厂方法，使 CloudFront 可信代理逻辑可测试
- 覆盖率 lines 95.05%

## Added

- CloudFront HTTP 路径（非 HTTPS）测试覆盖
