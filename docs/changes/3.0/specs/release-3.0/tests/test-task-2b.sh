#!/usr/bin/env bash
# =============================================================
# Test Task 2b — Check Script 手工验证
# Spec: .kiro/specs/release-3.0/
# Ref: Requirement 3, AC 1–5
# =============================================================
set -euo pipefail

PASS=0
FAIL=0
TOTAL=0

pass() { PASS=$((PASS + 1)); TOTAL=$((TOTAL + 1)); echo "  ✅ PASS: $1"; }
fail() { FAIL=$((FAIL + 1)); TOTAL=$((TOTAL + 1)); echo "  ❌ FAIL: $1"; }

SCRIPT="bin/oasis-http-migrate-v3-check"
TMPDIR=""

cleanup() {
    if [[ -n "$TMPDIR" && -d "$TMPDIR" ]]; then
        rm -rf "$TMPDIR"
    fi
}
trap cleanup EXIT

echo "=== Check Script 手工验证 ==="
echo ""

# ─── 2.3.1 --help 输出 usage 信息 ───
echo "--- 2.3.1 --help 输出验证 ---"

HELP_OUTPUT=$(php "$SCRIPT" --help 2>&1)
HELP_EXIT=$?

if [[ $HELP_EXIT -ne 0 ]]; then
    fail "--help 退出码非 0 (实际: $HELP_EXIT)"
else
    pass "--help 退出码为 0"
fi

if echo "$HELP_OUTPUT" | grep -qi "usage"; then
    pass "--help 输出包含 usage 信息"
else
    fail "--help 输出不包含 usage 信息"
    echo "  实际输出: $HELP_OUTPUT"
fi

echo ""

# ─── 创建临时测试目录和文件 ───
TMPDIR=$(mktemp -d)

# 每个测试场景使用独立子目录（check script 接受目录参数）
mkdir -p "$TMPDIR/removed-api"
mkdir -p "$TMPDIR/pimple-access"
mkdir -p "$TMPDIR/clean"

# 2.3.2 测试文件：包含已知 Removed API 引用
cat > "$TMPDIR/removed-api/test-removed-api.php" << 'PHPEOF'
<?php
use Oasis\Mlib\Http\SilexKernel;

$kernel = new SilexKernel($config);
PHPEOF

# 2.3.3 测试文件：包含 Pimple 访问模式
cat > "$TMPDIR/pimple-access/test-pimple-access.php" << 'PHPEOF'
<?php
$logger = $app['logger'];
$twig = $app['twig'];
PHPEOF

# 无问题的测试文件（用于 exit code 0 验证）
cat > "$TMPDIR/clean/test-clean.php" << 'PHPEOF'
<?php
use Oasis\Mlib\Http\MicroKernel;

$kernel = new MicroKernel($config, true);
PHPEOF

# ─── 2.3.2 对包含已知 Removed API 引用的测试 PHP 文件，验证正确检测到 finding ───
echo "--- 2.3.2 Removed API 检测验证 ---"

REMOVED_OUTPUT=$(php "$SCRIPT" "$TMPDIR/removed-api" 2>&1) || true

if echo "$REMOVED_OUTPUT" | grep -q "SilexKernel"; then
    pass "正确检测到 SilexKernel (Removed API) 引用"
else
    fail "未检测到 SilexKernel 引用"
    echo "  实际输出:"
    echo "$REMOVED_OUTPUT" | head -20
fi

echo ""

# ─── 2.3.3 对包含 Pimple 访问模式的测试 PHP 文件，验证正确检测到 finding ───
echo "--- 2.3.3 Pimple 访问模式检测验证 ---"

PIMPLE_OUTPUT=$(php "$SCRIPT" "$TMPDIR/pimple-access" 2>&1) || true

if echo "$PIMPLE_OUTPUT" | grep -qi "pimple"; then
    pass "正确检测到 Pimple 访问模式"
else
    fail "未检测到 Pimple 访问模式"
    echo "  实际输出:"
    echo "$PIMPLE_OUTPUT" | head -20
fi

echo ""

# ─── 2.3.4 --format=json 输出有效 JSON ───
echo "--- 2.3.4 --format=json 输出验证 ---"

JSON_OUTPUT=$(php "$SCRIPT" --format=json "$TMPDIR/removed-api" 2>/dev/null) || true

# 验证是有效 JSON
if echo "$JSON_OUTPUT" | php -r 'json_decode(file_get_contents("php://stdin")); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);'; then
    pass "--format=json 输出有效 JSON"
else
    fail "--format=json 输出无效 JSON"
    echo "  实际输出:"
    echo "$JSON_OUTPUT" | head -10
fi

# 验证 JSON 是数组且包含 finding
if echo "$JSON_OUTPUT" | php -r '
    $data = json_decode(file_get_contents("php://stdin"), true);
    exit(is_array($data) && count($data) > 0 ? 0 : 1);
'; then
    pass "JSON 输出包含 finding 数组"
else
    fail "JSON 输出不包含 finding 数组"
fi

echo ""

# ─── 2.3.5 exit code 验证 ───
echo "--- 2.3.5 Exit code 验证 ---"

# 存在 🔴 finding 时 exit code 为 1
set +e
php "$SCRIPT" "$TMPDIR/removed-api" > /dev/null 2>&1
RED_EXIT=$?
set -e

if [[ $RED_EXIT -eq 1 ]]; then
    pass "存在 🔴 finding 时 exit code 为 1"
else
    fail "存在 🔴 finding 时 exit code 应为 1 (实际: $RED_EXIT)"
fi

# 无 🔴 finding 时 exit code 为 0（使用干净文件）
set +e
php "$SCRIPT" "$TMPDIR/clean" > /dev/null 2>&1
CLEAN_EXIT=$?
set -e

if [[ $CLEAN_EXIT -eq 0 ]]; then
    pass "无 🔴 finding 时 exit code 为 0"
else
    fail "无 🔴 finding 时 exit code 应为 0 (实际: $CLEAN_EXIT)"
fi

echo ""

# ─── 汇总 ───
echo "=== Check Script 验证汇总 ==="
echo "  Total: $TOTAL"
echo "  Pass:  $PASS"
echo "  Fail:  $FAIL"
echo ""

if [[ $FAIL -gt 0 ]]; then
    echo "❌ Check Script 验证未通过"
    exit 1
else
    echo "✅ Check Script 验证全部通过"
    exit 0
fi
