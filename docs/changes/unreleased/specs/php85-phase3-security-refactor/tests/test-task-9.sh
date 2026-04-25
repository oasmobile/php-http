#!/usr/bin/env bash
#
# Manual test script for Task 9: 手工测试
# Spec: php85-phase3-security-refactor
#
# Covers:
#   9.1 — 认证流程端到端行为 (R9.1, R9.2, R9.3)
#   9.2 — 防火墙和授权行为 (R10.1, R10.2, R10.3, R10.4)
#   9.3 — 旧类废弃标记 (R7.1, R7.2)
#
# All sub-tasks are fully automated — no human intervention required.
# The script runs a PHP helper that boots MicroKernel with the integration
# security bootstrap and exercises the full authentication/authorization chain.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"

PASS=0
FAIL=0
TOTAL=0

pass() {
    PASS=$((PASS + 1))
    TOTAL=$((TOTAL + 1))
    echo "  ✅ PASS: $1"
}

fail() {
    FAIL=$((FAIL + 1))
    TOTAL=$((TOTAL + 1))
    echo "  ❌ FAIL: $1"
    if [ -n "${2:-}" ]; then
        echo "         $2"
    fi
}

echo "============================================"
echo " Task 9: 手工测试 — Security Component"
echo "============================================"
echo ""

# Run the PHP test helper and capture output
PHP_OUTPUT=$(php "$SCRIPT_DIR/manual-test-security.php" "$PROJECT_ROOT" 2>&1) || true

echo "--- 9.1 验证认证流程端到端行为 ---"
echo ""

# 9.1.1: Valid credentials (sig=abcd) → 200 + authenticated token
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.1.1:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.1.1 有效凭证 sig=abcd → 200, token storage 有已认证 token"
else
    detail=$(echo "$result" | sed 's/^TEST:9.1.1://')
    fail "9.1.1 有效凭证 sig=abcd → 200" "$detail"
fi

# 9.1.2: Invalid credentials (sig=invalid) → request not blocked, access rule → 403
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.1.2:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.1.2 无效凭证 sig=invalid → 认证失败但请求继续, access rule 返回 403"
else
    detail=$(echo "$result" | sed 's/^TEST:9.1.2://')
    fail "9.1.2 无效凭证 sig=invalid → 403" "$detail"
fi

# 9.1.3: No credentials → supports() returns false, skip authentication
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.1.3:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.1.3 无凭证 → authenticator 跳过认证 (supports() 返回 false)"
else
    detail=$(echo "$result" | sed 's/^TEST:9.1.3://')
    fail "9.1.3 无凭证 → 跳过认证" "$detail"
fi

echo ""
echo "--- 9.2 验证防火墙和授权行为 ---"
echo ""

# 9.2.1: URL matches firewall pattern → triggers authentication
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.2.1:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.2.1 请求 URL 匹配 firewall pattern 时触发认证流程"
else
    detail=$(echo "$result" | sed 's/^TEST:9.2.1://')
    fail "9.2.1 firewall pattern 匹配触发认证" "$detail"
fi

# 9.2.2: URL does not match any firewall pattern → skip authentication
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.2.2:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.2.2 请求 URL 不匹配任何 firewall pattern 时跳过认证"
else
    detail=$(echo "$result" | sed 's/^TEST:9.2.2://')
    fail "9.2.2 firewall pattern 不匹配跳过认证" "$detail"
fi

# 9.2.3: Access rules match in registration order, first match wins
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.2.3:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.2.3 access rule 按注册顺序匹配，第一个匹配的 rule 生效"
else
    detail=$(echo "$result" | sed 's/^TEST:9.2.3://')
    fail "9.2.3 access rule 注册顺序匹配" "$detail"
fi

# 9.2.4: Role hierarchy inheritance (ROLE_ADMIN → ROLE_USER)
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.2.4:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.2.4 role hierarchy 继承关系正确 (ROLE_ADMIN 可访问 ROLE_USER 资源)"
else
    detail=$(echo "$result" | sed 's/^TEST:9.2.4://')
    fail "9.2.4 role hierarchy 继承" "$detail"
fi

echo ""
echo "--- 9.3 验证旧类废弃标记 ---"
echo ""

# 9.3.1: @deprecated annotation exists
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.3.1:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.3.1 AbstractSimplePreAuthenticator 的 @deprecated 注解存在"
else
    detail=$(echo "$result" | sed 's/^TEST:9.3.1://')
    fail "9.3.1 @deprecated 注解" "$detail"
fi

# 9.3.2: createToken() throws LogicException
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.3.2:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.3.2 createToken() 抛出 LogicException"
else
    detail=$(echo "$result" | sed 's/^TEST:9.3.2://')
    fail "9.3.2 createToken() LogicException" "$detail"
fi

# 9.3.3: authenticateToken() throws LogicException
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.3.3:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.3.3 authenticateToken() 抛出 LogicException"
else
    detail=$(echo "$result" | sed 's/^TEST:9.3.3://')
    fail "9.3.3 authenticateToken() LogicException" "$detail"
fi

# 9.3.4: supportsToken() throws LogicException
result=$(echo "$PHP_OUTPUT" | grep "^TEST:9.3.4:" | head -1)
if echo "$result" | grep -q ":PASS$"; then
    pass "9.3.4 supportsToken() 抛出 LogicException"
else
    detail=$(echo "$result" | sed 's/^TEST:9.3.4://')
    fail "9.3.4 supportsToken() LogicException" "$detail"
fi

echo ""
echo "============================================"
echo " Summary: $PASS passed, $FAIL failed, $TOTAL total"
echo "============================================"

if [ "$FAIL" -gt 0 ]; then
    echo ""
    echo "⚠️  Some manual tests FAILED. See details above."
    exit 1
else
    echo ""
    echo "✅ All manual tests PASSED."
    exit 0
fi
