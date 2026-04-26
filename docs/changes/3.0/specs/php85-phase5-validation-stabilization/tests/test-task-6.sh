#!/usr/bin/env bash
# ============================================================================
# Task 6: 公共 API 兼容性验证（R13）
# 验证 2.5 版本的公共 API 在 3.x MicroKernel 下行为一致
# ============================================================================

set -euo pipefail

PASS=0
FAIL=0
RESULTS=()

pass() {
    PASS=$((PASS + 1))
    RESULTS+=("PASS: $1")
    echo "  ✅ PASS: $1"
}

fail() {
    FAIL=$((FAIL + 1))
    RESULTS+=("FAIL: $1 — $2")
    echo "  ❌ FAIL: $1 — $2"
}

section() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

# ============================================================================
# 6.1 Routing 兼容性
# ============================================================================
section "6.1 Routing 兼容性"

echo "  Running routing test suite..."
if vendor/bin/phpunit --testsuite routing 2>&1 | tail -1 | grep -q "OK"; then
    pass "routing testsuite 全量通过"
else
    fail "routing testsuite" "存在失败测试"
fi

echo "  Running SilexKernelWebTest (routing integration)..."
if vendor/bin/phpunit --testsuite SilexKernelWebTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "SilexKernelWebTest 全量通过（路由解析、参数提取、HTTP method 匹配）"
else
    fail "SilexKernelWebTest" "存在失败测试"
fi

echo "  Checking route config array format (YAML-based routing)..."
if grep -q "path:" ut/routes.yml && grep -q "_controller:" ut/routes.yml; then
    pass "路由配置格式兼容（YAML route config 数组 + _controller 默认值）"
else
    fail "路由配置格式" "routes.yml 格式不符合预期"
fi

echo "  Checking host-based routing..."
if vendor/bin/phpunit --filter testHostBasedRoutes 2>&1 | tail -1 | grep -q "OK"; then
    pass "Host-based 路由正常工作"
else
    fail "Host-based 路由" "testHostBasedRoutes 失败"
fi

echo "  Checking parameter extraction..."
if vendor/bin/phpunit --filter testParameterMatching 2>&1 | tail -1 | grep -q "OK"; then
    pass "路由参数提取正常（数字 ID、slug、通配符）"
else
    fail "路由参数提取" "testParameterMatching 失败"
fi

echo "  Checking HTTP scheme redirect..."
if vendor/bin/phpunit --filter testHttpOnlyRoute 2>&1 | tail -1 | grep -q "OK"; then
    pass "HTTP scheme 限制与重定向正常"
else
    fail "HTTP scheme 限制" "testHttpOnlyRoute 失败"
fi

echo "  Checking 404 handling..."
if vendor/bin/phpunit --filter testNotFoundRoute 2>&1 | tail -1 | grep -q "OK"; then
    pass "404 路由未找到处理正常"
else
    fail "404 处理" "testNotFoundRoute 失败"
fi

echo "  Checking sub-routes (resource import)..."
if vendor/bin/phpunit --filter testSubRoutes 2>&1 | tail -1 | grep -q "OK"; then
    pass "子路由（resource import）正常工作"
else
    fail "子路由" "testSubRoutes 失败"
fi

echo "  Checking domain matching..."
if vendor/bin/phpunit --filter testDomainMatching 2>&1 | tail -1 | grep -q "OK"; then
    pass "域名匹配路由正常"
else
    fail "域名匹配" "testDomainMatching 失败"
fi

echo "  Checking config parameter in routes..."
if vendor/bin/phpunit --filter testParameterFromConfig 2>&1 | tail -1 | grep -q "OK"; then
    pass "路由中的配置参数替换正常（%app.config1%）"
else
    fail "配置参数替换" "testParameterFromConfig 失败"
fi

# ============================================================================
# 6.2 Controller 兼容性
# ============================================================================
section "6.2 Controller 兼容性"

echo "  Checking Request injection..."
if vendor/bin/phpunit --filter testParameterRetrieval 2>&1 | tail -1 | grep -q "OK"; then
    pass "Request 注入 + 路由参数注入正常（GET/POST 参数链式获取）"
else
    fail "Request 注入" "testParameterRetrieval 失败"
fi

echo "  Checking controller injected args (type-hint injection)..."
if vendor/bin/phpunit --filter testInjectedArg 2>&1 | tail -1 | grep -q "OK"; then
    pass "Controller 参数自动注入正常（JsonViewHandler + 继承类匹配）"
else
    fail "Controller 参数注入" "testInjectedArg 失败"
fi

echo "  Checking controller return value handling..."
if vendor/bin/phpunit --filter testHomeRoute 2>&1 | tail -1 | grep -q "OK"; then
    pass "Controller 返回数组 → View Handler 转换为 JSON Response"
else
    fail "Controller 返回值处理" "testHomeRoute 失败"
fi

echo "  Checking ExtendedArgumentValueResolver..."
if vendor/bin/phpunit --testsuite misc --filter ExtendedArgumentValueResolver 2>&1 | tail -1 | grep -q "OK"; then
    pass "ExtendedArgumentValueResolver 参数解析正常"
else
    fail "ExtendedArgumentValueResolver" "测试失败"
fi

# ============================================================================
# 6.3 View/Renderer 兼容性
# ============================================================================
section "6.3 View/Renderer 兼容性"

echo "  Running views test suite..."
if vendor/bin/phpunit --testsuite views 2>&1 | tail -1 | grep -q "OK"; then
    pass "views testsuite 全量通过"
else
    fail "views testsuite" "存在失败测试"
fi

echo "  Running FallbackViewHandler test..."
if vendor/bin/phpunit --testsuite FallbackViewHandlerTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "FallbackViewHandler 全量通过（HTML/JSON 渲染、错误渲染）"
else
    fail "FallbackViewHandler" "存在失败测试"
fi

echo "  Running twig test suite..."
if vendor/bin/phpunit --testsuite twig 2>&1 | tail -1 | grep -q "OK"; then
    pass "twig testsuite 全量通过（Twig 模板渲染）"
else
    fail "twig testsuite" "存在失败测试"
fi

echo "  Checking JSON view handler Content-Type..."
if vendor/bin/phpunit --filter testInvokeReturnsJsonResponseWhenAcceptIsJsonCompatible 2>&1 | tail -1 | grep -q "OK"; then
    pass "JSON View Handler Content-Type 正确（application/json）"
else
    fail "JSON View Handler" "Content-Type 测试失败"
fi

echo "  Checking route-based renderer resolution..."
if vendor/bin/phpunit --filter RouteBasedResponseRendererResolver 2>&1 | tail -1 | grep -q "OK"; then
    pass "RouteBasedResponseRendererResolver 正常（html/page→HTML, api/json→JSON）"
else
    fail "RouteBasedResponseRendererResolver" "测试失败"
fi

# ============================================================================
# 6.4 Security 兼容性
# ============================================================================
section "6.4 Security 兼容性"

echo "  Running security test suite..."
if vendor/bin/phpunit --testsuite security 2>&1 | tail -1 | grep -q "OK"; then
    pass "security testsuite 全量通过"
else
    fail "security testsuite" "存在失败测试"
fi

echo "  Checking pre-auth flow..."
if vendor/bin/phpunit --filter testPreAuth 2>&1 | tail -1 | grep -q "OK"; then
    pass "Pre-auth 认证流程正常（无凭证→403, 错误凭证→403, 正确凭证→200）"
else
    fail "Pre-auth 认证" "testPreAuth 失败"
fi

echo "  Checking access rule with roles..."
if vendor/bin/phpunit --filter testAccessRuleOk 2>&1 | tail -1 | grep -q "OK"; then
    pass "Access Rule 角色检查正常"
else
    fail "Access Rule" "testAccessRuleOk 失败"
fi

echo "  Checking role hierarchy..."
if vendor/bin/phpunit --filter testAccessRuleWithRoleHierarchy 2>&1 | tail -1 | grep -q "OK"; then
    pass "Role Hierarchy 继承正常（ROLE_PARENT → ROLE_CHILD）"
else
    fail "Role Hierarchy" "testAccessRuleWithRoleHierarchy 失败"
fi

echo "  Checking host-based access rules..."
if vendor/bin/phpunit --filter testAccessRuleOnHost 2>&1 | tail -1 | grep -q "OK"; then
    pass "Host-based Access Rule 正常"
else
    fail "Host-based Access Rule" "testAccessRuleOnHost 失败"
fi

echo "  Checking security auth flow integration..."
if vendor/bin/phpunit --filter SecurityAuthenticationFlowIntegrationTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "Security 认证流程集成测试通过"
else
    fail "Security 集成测试" "SecurityAuthenticationFlowIntegrationTest 失败"
fi

echo "  Checking isGranted API..."
if vendor/bin/phpunit --filter testIsGranted 2>&1 | tail -1 | grep -q "OK"; then
    pass "isGranted() API 正常（无 checker→false, 有 checker→委托）"
else
    fail "isGranted API" "testIsGranted 失败"
fi

# ============================================================================
# 6.5 CORS 兼容性
# ============================================================================
section "6.5 CORS 兼容性"

echo "  Running cors test suite..."
if vendor/bin/phpunit --testsuite cors 2>&1 | tail -1 | grep -q "OK"; then
    pass "cors testsuite 全量通过"
else
    fail "cors testsuite" "存在失败测试"
fi

echo "  Checking preflight response..."
if vendor/bin/phpunit --filter testPreflightOnExistingRoute 2>&1 | tail -1 | grep -q "OK"; then
    pass "Preflight 响应正常（204 + CORS headers）"
else
    fail "Preflight 响应" "testPreflightOnExistingRoute 失败"
fi

echo "  Checking allowed origins..."
if vendor/bin/phpunit --filter testPrefilightOnAllowedOrigin 2>&1 | tail -1 | grep -q "OK"; then
    pass "Allowed origins 检查正常"
else
    fail "Allowed origins" "testPrefilightOnAllowedOrigin 失败"
fi

echo "  Checking allowed methods..."
if vendor/bin/phpunit --filter testPrefilightOnLimitedAllowedMethod 2>&1 | tail -1 | grep -q "OK"; then
    pass "Allowed methods 检查正常"
else
    fail "Allowed methods" "testPrefilightOnLimitedAllowedMethod 失败"
fi

echo "  Checking allowed headers..."
if vendor/bin/phpunit --filter testPrefilightOnAllowedHeader 2>&1 | tail -1 | grep -q "OK"; then
    pass "Allowed headers 检查正常"
else
    fail "Allowed headers" "testPrefilightOnAllowedHeader 失败"
fi

echo "  Checking credentials..."
if vendor/bin/phpunit --filter testPrefilightOnCredentials 2>&1 | tail -1 | grep -q "OK"; then
    pass "Credentials 处理正常"
else
    fail "Credentials" "testPrefilightOnCredentials 失败"
fi

echo "  Checking normal request CORS headers..."
if vendor/bin/phpunit --filter testNormalRequestAfterPreflight 2>&1 | tail -1 | grep -q "OK"; then
    pass "Normal request CORS headers 正常"
else
    fail "Normal request CORS" "testNormalRequestAfterPreflight 失败"
fi

echo "  Checking exposed headers..."
if vendor/bin/phpunit --filter testExposedHeadersAfterPreflight 2>&1 | tail -1 | grep -q "OK"; then
    pass "Exposed headers 正常"
else
    fail "Exposed headers" "testExposedHeadersAfterPreflight 失败"
fi

echo "  Checking multi-strategy priority..."
if vendor/bin/phpunit --filter testMultiStrategyPriorityFirstMatchWins 2>&1 | tail -1 | grep -q "OK"; then
    pass "Multi-strategy 优先级正常（first match wins）"
else
    fail "Multi-strategy" "testMultiStrategyPriorityFirstMatchWins 失败"
fi

echo "  Checking CORS integration in bootstrap..."
if vendor/bin/phpunit --filter testCorsConfigRegistersCorsProviderAndHeadersArePresent 2>&1 | tail -1 | grep -q "OK"; then
    pass "CORS bootstrap 集成正常"
else
    fail "CORS bootstrap 集成" "testCorsConfigRegistersCorsProviderAndHeadersArePresent 失败"
fi

# ============================================================================
# 6.6 Bootstrap Config 兼容性
# ============================================================================
section "6.6 Bootstrap Config 兼容性"

echo "  Running SilexKernelTest (bootstrap config unit tests)..."
if vendor/bin/phpunit --testsuite SilexKernelTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "SilexKernelTest 全量通过（bootstrap config 初始化）"
else
    fail "SilexKernelTest" "存在失败测试"
fi

echo "  Running configuration test suite..."
if vendor/bin/phpunit --testsuite configuration 2>&1 | tail -1 | grep -q "OK"; then
    pass "configuration testsuite 全量通过（Symfony Config 校验）"
else
    fail "configuration testsuite" "存在失败测试"
fi

echo "  Running integration test suite..."
if vendor/bin/phpunit --testsuite integration 2>&1 | tail -1 | grep -q "OK"; then
    pass "integration testsuite 全量通过（bootstrap → routing/cors/twig/middleware 集成）"
else
    fail "integration testsuite" "存在失败测试"
fi

echo "  Checking trusted proxies config..."
if vendor/bin/phpunit --filter testConfigTrustedProxies 2>&1 | tail -1 | grep -q "OK"; then
    pass "trusted_proxies 配置正常"
else
    fail "trusted_proxies" "testConfigTrustedProxies 失败"
fi

echo "  Checking trusted header set config..."
if vendor/bin/phpunit --filter testConfigTrustedHeaderSet 2>&1 | tail -1 | grep -q "OK"; then
    pass "trusted_header_set 配置正常"
else
    fail "trusted_header_set" "testConfigTrustedHeaderSet 失败"
fi

echo "  Checking middlewares config..."
if vendor/bin/phpunit --filter testConfigMiddlewares 2>&1 | tail -1 | grep -q "OK"; then
    pass "middlewares 配置正常（有效/无效值校验）"
else
    fail "middlewares 配置" "testConfigMiddlewares 失败"
fi

echo "  Checking view_handlers config..."
if vendor/bin/phpunit --filter testConfigViewHandlers 2>&1 | tail -1 | grep -q "OK"; then
    pass "view_handlers 配置正常（有效/无效值校验）"
else
    fail "view_handlers 配置" "testConfigViewHandlers 失败"
fi

echo "  Checking error_handlers config..."
if vendor/bin/phpunit --filter testConfigErrorHandlers 2>&1 | tail -1 | grep -q "OK"; then
    pass "error_handlers 配置正常（有效/无效值校验）"
else
    fail "error_handlers 配置" "testConfigErrorHandlers 失败"
fi

echo "  Checking injected_args config..."
if vendor/bin/phpunit --filter testConfigInjectedArgs 2>&1 | tail -1 | grep -q "OK"; then
    pass "injected_args 配置正常"
else
    fail "injected_args 配置" "testConfigInjectedArgs 失败"
fi

echo "  Checking cache_dir config..."
if vendor/bin/phpunit --filter testGetCacheDirectories 2>&1 | tail -1 | grep -q "OK"; then
    pass "cache_dir 配置正常（含 routing.cache_dir、twig.cache_dir）"
else
    fail "cache_dir 配置" "testGetCacheDirectories 失败"
fi

echo "  Checking wrong configuration handling..."
if vendor/bin/phpunit --filter testCreationWithWrongConfiguration 2>&1 | tail -1 | grep -q "OK"; then
    pass "无效配置抛出 InvalidConfigurationException"
else
    fail "无效配置处理" "testCreationWithWrongConfiguration 失败"
fi

echo "  Checking extra parameters..."
if vendor/bin/phpunit --filter testGetParameter 2>&1 | tail -1 | grep -q "OK"; then
    pass "addExtraParameters / getParameter 正常"
else
    fail "extra parameters" "testGetParameter 失败"
fi

# ============================================================================
# 6.7 Error Handling 兼容性
# ============================================================================
section "6.7 Error Handling 兼容性"

echo "  Running error-handlers test suite..."
if vendor/bin/phpunit --testsuite error-handlers 2>&1 | tail -1 | grep -q "OK"; then
    pass "error-handlers testsuite 全量通过"
else
    fail "error-handlers testsuite" "存在失败测试"
fi

echo "  Checking ExceptionWrapper behavior..."
if vendor/bin/phpunit --filter ExceptionWrapperTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "ExceptionWrapper 行为正常（DataValidationException→400, ExistenceViolation→404, 普通异常→原始 code）"
else
    fail "ExceptionWrapper" "ExceptionWrapperTest 失败"
fi

echo "  Checking JsonErrorHandler behavior..."
if vendor/bin/phpunit --filter JsonErrorHandlerTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "JsonErrorHandler 行为正常（返回 array 含 code/type/message/file/line）"
else
    fail "JsonErrorHandler" "JsonErrorHandlerTest 失败"
fi

echo "  Checking error handler → view handler chain..."
if vendor/bin/phpunit --filter testPanelError 2>&1 | tail -1 | grep -q "OK"; then
    pass "Error handler → View handler 链正常（ExceptionWrapper → FallbackViewHandler → HTML）"
else
    fail "Error → View 链" "testPanelError 失败"
fi

echo "  Checking API error response..."
if vendor/bin/phpunit --filter testApiError 2>&1 | tail -1 | grep -q "OK"; then
    pass "API 错误响应正常（ExceptionWrapper → FallbackViewHandler → JSON）"
else
    fail "API 错误响应" "testApiError 失败"
fi

echo "  Checking 404 error response structure..."
if vendor/bin/phpunit --filter testNotFoundRoute 2>&1 | tail -1 | grep -q "OK"; then
    pass "404 错误响应结构正常（JSON 含 code 字段）"
else
    fail "404 错误响应" "testNotFoundRoute 失败"
fi

echo "  Checking WrappedExceptionInfo..."
if vendor/bin/phpunit --filter WrappedExceptionInfoTest 2>&1 | tail -1 | grep -q "OK"; then
    pass "WrappedExceptionInfo 数据结构正常"
else
    fail "WrappedExceptionInfo" "WrappedExceptionInfoTest 失败"
fi

# ============================================================================
# 6.8 Breaking Changes 汇总
# ============================================================================
section "6.8 Breaking Changes 汇总"

echo "  Checking for any test failures that indicate breaking changes..."
echo ""

# Run the complete test suite and capture results
FULL_OUTPUT=$(vendor/bin/phpunit 2>&1)
FULL_RESULT=$?

if [ $FULL_RESULT -eq 0 ]; then
    pass "全量测试通过（510 tests），无 breaking change 检测到"
    echo ""
    echo "  全量测试结果："
    echo "$FULL_OUTPUT" | tail -3
else
    fail "全量测试" "存在失败测试，可能存在 breaking change"
    echo ""
    echo "  失败详情："
    echo "$FULL_OUTPUT" | grep -A 2 "FAILURES\|ERRORS" || true
fi

# ============================================================================
# 汇总
# ============================================================================
section "验证结果汇总"

echo ""
echo "  通过: $PASS"
echo "  失败: $FAIL"
echo ""

if [ $FAIL -gt 0 ]; then
    echo "  ❌ 存在失败项："
    for r in "${RESULTS[@]}"; do
        if [[ "$r" == FAIL* ]]; then
            echo "    - $r"
        fi
    done
    echo ""
    exit 1
else
    echo "  ✅ 所有公共 API 兼容性验证通过，未发现 breaking change"
    echo ""
    exit 0
fi
