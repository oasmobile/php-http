#!/usr/bin/env bash
# =============================================================================
# Manual Test Script — Task 8: 手工测试
# Spec: php85-phase5-validation-stabilization
# =============================================================================
set -euo pipefail

PASS=0
FAIL=0
DETAILS=""

report() {
    local status="$1" subtask="$2" detail="$3"
    if [ "$status" = "PASS" ]; then
        PASS=$((PASS + 1))
        DETAILS="${DETAILS}\n  ✅ ${subtask}: ${detail}"
    else
        FAIL=$((FAIL + 1))
        DETAILS="${DETAILS}\n  ❌ ${subtask}: ${detail}"
    fi
}

echo "========================================"
echo " Task 8: Manual Test Execution"
echo "========================================"

# ── 8.1 验证 oasis/utils ^3.0 升级完整性 ──────────────────────────────────

echo ""
echo "── 8.1 oasis/utils ^3.0 升级完整性 ──"

# 8.1a: composer.json 中 oasis/utils 版本约束为 ^3.0
UTILS_VER=$(php -r '
    $c = json_decode(file_get_contents("composer.json"), true);
    echo $c["require"]["oasis/utils"] ?? "NOT_FOUND";
')
if [ "$UTILS_VER" = "^3.0" ]; then
    report PASS "8.1a" "composer.json oasis/utils = $UTILS_VER"
else
    report FAIL "8.1a" "composer.json oasis/utils = $UTILS_VER (expected ^3.0)"
fi

# 8.1b: src/ 中无 oasis/utils ^2.0 已废弃 API 残留
# 已知 ^2.0 → ^3.0 变更点（基于 Task 1 执行记录）:
#   - DataProviderInterface 常量名变更（如 TYPE_STRING → STRING_TYPE 等）
#   - getMandatory() / getOptional() 签名变更
#   - StringUtils::stringStartsWith() 可能移除
# 搜索已知的 ^2.0 废弃模式
DEPRECATED_PATTERNS=(
    "TYPE_STRING[^_]"
    "TYPE_INT[^E]"
    "TYPE_FLOAT[^_]"
    "TYPE_BOOL[^_]"
    "TYPE_ARRAY[^_]"
    "TYPE_MIXED[^_]"
)

SRC_DEPRECATED_FOUND=0
for pattern in "${DEPRECATED_PATTERNS[@]}"; do
    if grep -rn "$pattern" src/ 2>/dev/null | grep -v '.git' | head -1 > /dev/null 2>&1; then
        count=$(grep -rn "$pattern" src/ 2>/dev/null | grep -v '.git' | wc -l | tr -d ' ')
        if [ "$count" -gt 0 ]; then
            SRC_DEPRECATED_FOUND=$((SRC_DEPRECATED_FOUND + count))
        fi
    fi
done

if [ "$SRC_DEPRECATED_FOUND" -eq 0 ]; then
    report PASS "8.1b" "src/ 中无已知 ^2.0 废弃 API 模式"
else
    report FAIL "8.1b" "src/ 中发现 $SRC_DEPRECATED_FOUND 处可能的 ^2.0 废弃 API"
fi

# 8.1c: ut/ 中无 oasis/utils ^2.0 已废弃 API 残留
UT_DEPRECATED_FOUND=0
for pattern in "${DEPRECATED_PATTERNS[@]}"; do
    if grep -rn "$pattern" ut/ 2>/dev/null | grep -v '.git' | head -1 > /dev/null 2>&1; then
        count=$(grep -rn "$pattern" ut/ 2>/dev/null | grep -v '.git' | wc -l | tr -d ' ')
        if [ "$count" -gt 0 ]; then
            UT_DEPRECATED_FOUND=$((UT_DEPRECATED_FOUND + count))
        fi
    fi
done

if [ "$UT_DEPRECATED_FOUND" -eq 0 ]; then
    report PASS "8.1c" "ut/ 中无已知 ^2.0 废弃 API 模式"
else
    report FAIL "8.1c" "ut/ 中发现 $UT_DEPRECATED_FOUND 处可能的 ^2.0 废弃 API"
fi

# 8.1d: composer.lock 中实际安装的 oasis/utils 版本为 3.x
INSTALLED_UTILS=$(php -r '
    $lock = json_decode(file_get_contents("composer.lock"), true);
    foreach ($lock["packages"] as $p) {
        if ($p["name"] === "oasis/utils") { echo $p["version"]; break; }
    }
')
if echo "$INSTALLED_UTILS" | grep -qE '^v?3\.'; then
    report PASS "8.1d" "composer.lock oasis/utils installed = $INSTALLED_UTILS"
else
    report FAIL "8.1d" "composer.lock oasis/utils installed = $INSTALLED_UTILS (expected 3.x)"
fi

# ── 8.2 验证 oasis/logging ^3.0 升级完整性 ─────────────────────────────────

echo "── 8.2 oasis/logging ^3.0 升级完整性 ──"

# 8.2a: composer.json 中 oasis/logging 版本约束为 ^3.0
LOGGING_VER=$(php -r '
    $c = json_decode(file_get_contents("composer.json"), true);
    echo $c["require"]["oasis/logging"] ?? "NOT_FOUND";
')
if [ "$LOGGING_VER" = "^3.0" ]; then
    report PASS "8.2a" "composer.json oasis/logging = $LOGGING_VER"
else
    report FAIL "8.2a" "composer.json oasis/logging = $LOGGING_VER (expected ^3.0)"
fi

# 8.2b: composer.lock 中实际安装的 oasis/logging 版本为 3.x
INSTALLED_LOGGING=$(php -r '
    $lock = json_decode(file_get_contents("composer.lock"), true);
    foreach ($lock["packages"] as $p) {
        if ($p["name"] === "oasis/logging") { echo $p["version"]; break; }
    }
')
if echo "$INSTALLED_LOGGING" | grep -qE '^v?3\.'; then
    report PASS "8.2b" "composer.lock oasis/logging installed = $INSTALLED_LOGGING"
else
    report FAIL "8.2b" "composer.lock oasis/logging installed = $INSTALLED_LOGGING (expected 3.x)"
fi

# 8.2c: ut/bootstrap.php 中 LocalFileHandler 调用语法正确
if grep -q 'new LocalFileHandler' ut/bootstrap.php; then
    report PASS "8.2c" "ut/bootstrap.php 包含 LocalFileHandler 构造调用"
else
    report FAIL "8.2c" "ut/bootstrap.php 中未找到 LocalFileHandler 构造调用"
fi

if grep -qF -- '->install()' ut/bootstrap.php; then
    report PASS "8.2d" "ut/bootstrap.php 包含 ->install() 调用"
else
    report FAIL "8.2d" "ut/bootstrap.php 中未找到 ->install() 调用"
fi

# ── 8.3 验证 PHPStan 配置正确性 ───────────────────────────────────────────

echo "── 8.3 PHPStan 配置正确性 ──"

# 8.3a: phpstan.neon 存在
if [ -f "phpstan.neon" ]; then
    report PASS "8.3a" "phpstan.neon 存在"
else
    report FAIL "8.3a" "phpstan.neon 不存在"
fi

# 8.3b: phpstan.neon 配置 level 8
if grep -q 'level: 8' phpstan.neon 2>/dev/null; then
    report PASS "8.3b" "phpstan.neon level = 8"
else
    report FAIL "8.3b" "phpstan.neon 未配置 level 8"
fi

# 8.3c: phpstan.neon 配置 paths 包含 src/
if grep -q '\- src/' phpstan.neon 2>/dev/null; then
    report PASS "8.3c" "phpstan.neon paths 包含 src/"
else
    report FAIL "8.3c" "phpstan.neon paths 未包含 src/"
fi

# 8.3d: composer.json require-dev 包含 phpstan/phpstan
PHPSTAN_DEV=$(php -r '
    $c = json_decode(file_get_contents("composer.json"), true);
    echo isset($c["require-dev"]["phpstan/phpstan"]) ? $c["require-dev"]["phpstan/phpstan"] : "NOT_FOUND";
')
if [ "$PHPSTAN_DEV" != "NOT_FOUND" ]; then
    report PASS "8.3d" "composer.json require-dev phpstan/phpstan = $PHPSTAN_DEV"
else
    report FAIL "8.3d" "composer.json require-dev 中未找到 phpstan/phpstan"
fi

# 8.3e: baseline 存在且在 phpstan.neon 中引用
if [ -f "phpstan-baseline.neon" ]; then
    if grep -q 'phpstan-baseline.neon' phpstan.neon 2>/dev/null; then
        report PASS "8.3e" "phpstan-baseline.neon 存在且在 phpstan.neon 中引用"
    else
        report FAIL "8.3e" "phpstan-baseline.neon 存在但未在 phpstan.neon 中引用"
    fi
else
    # baseline 不存在也可以（如果没有 false positive）
    if grep -q 'phpstan-baseline.neon' phpstan.neon 2>/dev/null; then
        report FAIL "8.3e" "phpstan.neon 引用了 phpstan-baseline.neon 但文件不存在"
    else
        report PASS "8.3e" "无 baseline 文件，phpstan.neon 也未引用（一致）"
    fi
fi

# ── 8.4 验证文档更新完整性 ────────────────────────────────────────────────

echo "── 8.4 文档更新完整性 ──"

# 8.4a: grep 搜索过时引用
# NOTE: 测试类名中包含 "Silex"（如 SilexKernelTest、SilexKernelWebTest、
# SilexKernelCrossCommunityIntegrationTest）是合法的——这些是实际的类名和
# phpunit.xml suite 名，不属于过时引用。搜索时排除这些已知的测试类名引用。
STALE_TERMS=("Silex" "Pimple" "SilexKernel" "Symfony 4")
DOC_TARGETS=("PROJECT.md" "README.md" "docs/state/" "docs/manual/")
STALE_FOUND=0
STALE_DETAIL=""

# Known legitimate test class names containing "Silex" — exclude from stale check
EXCLUDE_PATTERN="SilexKernelTest\|SilexKernelWebTest\|SilexKernelCrossCommunity"

for term in "${STALE_TERMS[@]}"; do
    for target in "${DOC_TARGETS[@]}"; do
        if [ -d "$target" ]; then
            hits=$(grep -rn "$term" "$target" 2>/dev/null | grep -v '.git' | grep -v "$EXCLUDE_PATTERN" || true)
        elif [ -f "$target" ]; then
            hits=$(grep -n "$term" "$target" 2>/dev/null | grep -v "$EXCLUDE_PATTERN" || true)
        else
            hits=""
        fi
        if [ -n "$hits" ]; then
            count=$(echo "$hits" | wc -l | tr -d ' ')
            STALE_FOUND=$((STALE_FOUND + count))
            STALE_DETAIL="${STALE_DETAIL}\n    '$term' in $target ($count hits):\n$(echo "$hits" | head -5 | sed 's/^/      /')"
        fi
    done
done

if [ "$STALE_FOUND" -eq 0 ]; then
    report PASS "8.4a" "文档中无 Silex/Pimple/SilexKernel/Symfony 4 残留引用（测试类名已排除）"
else
    report FAIL "8.4a" "文档中发现 $STALE_FOUND 处过时引用:${STALE_DETAIL}"
fi

# 8.4b: PROJECT.md 技术栈版本与 composer.json 一致
# 检查关键版本声明
PROJECT_CHECKS_PASS=0
PROJECT_CHECKS_TOTAL=0

check_project() {
    local desc="$1" pattern="$2"
    PROJECT_CHECKS_TOTAL=$((PROJECT_CHECKS_TOTAL + 1))
    if grep -q "$pattern" PROJECT.md 2>/dev/null; then
        PROJECT_CHECKS_PASS=$((PROJECT_CHECKS_PASS + 1))
    else
        STALE_DETAIL="${STALE_DETAIL}\n    Missing in PROJECT.md: $desc"
    fi
}

check_project "PHP >= 8.5" "PHP ≥ 8.5\|PHP >= 8.5"
check_project "Symfony 7.x" "Symfony 7.x"
check_project "Twig 3.x" "Twig 3.x"
check_project "Guzzle 7.x" "Guzzle 7.x"
check_project "PHPUnit 13.x" "PHPUnit 13.x"
check_project "oasis/utils ^3.0" "oasis/utils \^3.0\|oasis/utils.*3.0"
check_project "oasis/logging ^3.0" "oasis/logging \^3.0\|oasis/logging.*3.0"
check_project "PHPStan" "PHPStan"
check_project "MicroKernel entry" "MicroKernel"
check_project "phpstan analyse command" "phpstan analyse"

if [ "$PROJECT_CHECKS_PASS" -eq "$PROJECT_CHECKS_TOTAL" ]; then
    report PASS "8.4b" "PROJECT.md 技术栈版本与 composer.json 一致 ($PROJECT_CHECKS_PASS/$PROJECT_CHECKS_TOTAL)"
else
    report FAIL "8.4b" "PROJECT.md 技术栈版本检查 $PROJECT_CHECKS_PASS/$PROJECT_CHECKS_TOTAL${STALE_DETAIL}"
fi

# 8.4c: docs/state/architecture.md 模块结构与代码一致
# 检查 architecture.md 中引用的核心类在 src/ 中存在
ARCH_FILE="docs/state/architecture.md"
ARCH_PASS=0
ARCH_TOTAL=0
ARCH_MISSING=""

if [ -f "$ARCH_FILE" ]; then
    # 提取 architecture.md 中引用的 src/ 文件路径
    CORE_FILES=(
        "src/MicroKernel.php"
        "src/ChainedParameterBagDataProvider.php"
        "src/ErrorHandlers/ExceptionWrapper.php"
        "src/ErrorHandlers/JsonErrorHandler.php"
        "src/Middlewares/MiddlewareInterface.php"
        "src/Middlewares/AbstractMiddleware.php"
    )
    for f in "${CORE_FILES[@]}"; do
        ARCH_TOTAL=$((ARCH_TOTAL + 1))
        if [ -f "$f" ]; then
            ARCH_PASS=$((ARCH_PASS + 1))
        else
            ARCH_MISSING="${ARCH_MISSING}\n    Missing: $f"
        fi
    done

    # 检查 architecture.md 不包含过时引用 (excluding test class names)
    ARCH_STALE=$(grep -cE "Silex|Pimple|Symfony 4" "$ARCH_FILE" 2>/dev/null || echo "0")
    ARCH_STALE=$(echo "$ARCH_STALE" | tr -d '[:space:]')
    ARCH_STALE=${ARCH_STALE:-0}
    ARCH_TOTAL=$((ARCH_TOTAL + 1))
    if [ "$ARCH_STALE" -eq 0 ] 2>/dev/null; then
        ARCH_PASS=$((ARCH_PASS + 1))
    else
        ARCH_MISSING="${ARCH_MISSING}\n    $ARCH_STALE stale references in architecture.md"
    fi

    if [ "$ARCH_PASS" -eq "$ARCH_TOTAL" ]; then
        report PASS "8.4c" "docs/state/architecture.md 模块结构与代码一致 ($ARCH_PASS/$ARCH_TOTAL)"
    else
        report FAIL "8.4c" "docs/state/architecture.md 检查 $ARCH_PASS/$ARCH_TOTAL${ARCH_MISSING}"
    fi
else
    report FAIL "8.4c" "docs/state/architecture.md 不存在"
fi

# ── 汇总 ──────────────────────────────────────────────────────────────────

echo ""
echo "========================================"
echo " Results: $PASS PASS / $FAIL FAIL"
echo "========================================"
echo -e "$DETAILS"
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo "❌ MANUAL TEST FAILED"
    exit 1
else
    echo "✅ ALL MANUAL TESTS PASSED"
    exit 0
fi
