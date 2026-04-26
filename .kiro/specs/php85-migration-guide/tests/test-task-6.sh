#!/usr/bin/env bash
# =============================================================================
# Manual Test Script — Task 6: Migration Guide & Check Script 手工测试
# Spec: .kiro/specs/php85-migration-guide
#
# Covers:
#   6.1 Migration Guide 结构验证
#   6.2 Check Script CLI 交互验证
#   6.3 Check Script 端到端扫描验证
# =============================================================================

set -euo pipefail

PASS_COUNT=0
FAIL_COUNT=0
TOTAL_COUNT=0

pass() {
    PASS_COUNT=$((PASS_COUNT + 1))
    TOTAL_COUNT=$((TOTAL_COUNT + 1))
    echo "  ✅ PASS: $1"
}

fail() {
    FAIL_COUNT=$((FAIL_COUNT + 1))
    TOTAL_COUNT=$((TOTAL_COUNT + 1))
    echo "  ❌ FAIL: $1"
    if [ -n "${2:-}" ]; then
        echo "          Detail: $2"
    fi
}

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"
MIGRATION_GUIDE="$PROJECT_ROOT/docs/manual/migration-v3.md"
CHECK_SCRIPT="$PROJECT_ROOT/bin/oasis-http-migrate-v3-check"

echo "========================================"
echo " Task 6: Manual Test Execution"
echo "========================================"
echo ""
echo "Project root: $PROJECT_ROOT"
echo ""

# =============================================================================
# 6.1 Migration Guide 结构验证
# =============================================================================
echo "--- 6.1 Migration Guide 结构验证 ---"
echo ""

# 6.1.1 验证文件存在且非空
if [ -f "$MIGRATION_GUIDE" ] && [ -s "$MIGRATION_GUIDE" ]; then
    SIZE=$(wc -c < "$MIGRATION_GUIDE" | tr -d ' ')
    pass "migration-v3.md exists and is non-empty (${SIZE} bytes)"
else
    fail "migration-v3.md does not exist or is empty"
fi

# 6.1.2 验证 TOC 中所有锚点链接可解析到文档内 heading
echo ""
echo "  Checking TOC anchor links..."
TOC_ERRORS=0
# Extract TOC anchor links: lines matching "- [text](#anchor)"
while IFS= read -r line; do
    # Extract anchor from the link
    anchor=$(echo "$line" | sed -n 's/.*](#\([^)]*\)).*/\1/p')
    if [ -z "$anchor" ]; then
        continue
    fi

    # Generate expected heading pattern: ## N. Title → anchor = n-title
    # Search for a heading whose generated anchor matches
    # Markdown anchor generation: lowercase, spaces→hyphens, remove special chars except hyphens
    # We search for a heading line that would produce this anchor
    found=0
    while IFS= read -r heading; do
        # Generate anchor from heading: strip leading #s, trim, lowercase ASCII,
        # replace spaces with -, remove punctuation but keep unicode (Chinese etc.)
        heading_text=$(echo "$heading" | sed 's/^#* *//')
        # Use perl for proper unicode-aware anchor generation (GitHub-style):
        # lowercase, spaces→hyphens, strip chars that are not alphanumeric/hyphen/unicode-letters
        generated_anchor=$(echo "$heading_text" | perl -CSD -pe '$_ = lc($_); s/\s+/-/g; s/[^\w\-]//g; s/-+/-/g; s/-$//')
        if [ "$generated_anchor" = "$anchor" ]; then
            found=1
            break
        fi
    done < <(grep -E '^#{1,6} ' "$MIGRATION_GUIDE")

    if [ "$found" -eq 1 ]; then
        : # anchor resolved
    else
        TOC_ERRORS=$((TOC_ERRORS + 1))
        echo "    ⚠ Unresolved anchor: #$anchor"
    fi
done < <(grep -E '^\- \[.*\]\(#' "$MIGRATION_GUIDE")

if [ "$TOC_ERRORS" -eq 0 ]; then
    pass "All TOC anchor links resolve to document headings"
else
    fail "TOC has $TOC_ERRORS unresolved anchor links"
fi

# 6.1.3 验证所有 breaking change 条目包含 severity marker
echo ""
echo "  Checking severity markers on breaking change entries..."
# Breaking change entries are ### headings that start with a severity emoji
# Count ### headings that have severity markers
TOTAL_BC_HEADINGS=$(grep -cE '^### ' "$MIGRATION_GUIDE" || true)
SEVERITY_HEADINGS=$(grep -cE '^### (🔴|🟡|🟢) ' "$MIGRATION_GUIDE" || true)
# Some ### headings are not breaking change entries (e.g., "公共 API 方法列表", "Bootstrap_Config Key 参考表", etc.)
# We check that all ### headings with severity markers are well-formed
NON_BC_HEADINGS=$(grep -cE '^### [^🔴🟡🟢]' "$MIGRATION_GUIDE" || true)

if [ "$SEVERITY_HEADINGS" -gt 0 ]; then
    pass "Found $SEVERITY_HEADINGS breaking change entries with severity markers (out of $TOTAL_BC_HEADINGS ### headings, $NON_BC_HEADINGS non-BC headings)"
else
    fail "No breaking change entries with severity markers found"
fi

# 6.1.4 验证所有 breaking change 条目包含 before/after 代码块
echo ""
echo "  Checking before/after code blocks..."
# For each severity-marked ### section, check it contains **Before** and **After**
BC_WITH_CODE=0
BC_WITHOUT_CODE=0
BC_MISSING_LIST=""

# Use awk to extract sections between severity ### headings
# and check each section for Before/After patterns
CURRENT_HEADING=""
HAS_BEFORE=0
HAS_AFTER=0

while IFS= read -r line; do
    if echo "$line" | grep -qE '^### (🔴|🟡|🟢) '; then
        # Process previous heading if any
        if [ -n "$CURRENT_HEADING" ]; then
            if [ "$HAS_BEFORE" -eq 1 ] && [ "$HAS_AFTER" -eq 1 ]; then
                BC_WITH_CODE=$((BC_WITH_CODE + 1))
            else
                BC_WITHOUT_CODE=$((BC_WITHOUT_CODE + 1))
                BC_MISSING_LIST="${BC_MISSING_LIST}    ⚠ Missing before/after: ${CURRENT_HEADING}\n"
            fi
        fi
        CURRENT_HEADING="$line"
        HAS_BEFORE=0
        HAS_AFTER=0
    elif echo "$line" | grep -qiE '^\*\*Before\*\*'; then
        HAS_BEFORE=1
    elif echo "$line" | grep -qiE '^\*\*After\*\*'; then
        HAS_AFTER=1
    fi
done < "$MIGRATION_GUIDE"

# Process last heading
if [ -n "$CURRENT_HEADING" ]; then
    if [ "$HAS_BEFORE" -eq 1 ] && [ "$HAS_AFTER" -eq 1 ]; then
        BC_WITH_CODE=$((BC_WITH_CODE + 1))
    else
        BC_WITHOUT_CODE=$((BC_WITHOUT_CODE + 1))
        BC_MISSING_LIST="${BC_MISSING_LIST}    ⚠ Missing before/after: ${CURRENT_HEADING}\n"
    fi
fi

if [ "$BC_WITHOUT_CODE" -eq 0 ] && [ "$BC_WITH_CODE" -gt 0 ]; then
    pass "All $BC_WITH_CODE breaking change entries have before/after code blocks"
else
    fail "$BC_WITHOUT_CODE breaking change entries missing before/after code blocks"
    echo -e "$BC_MISSING_LIST"
fi

# 6.1.5 统计各 severity 级别的条目数量
echo ""
echo "  Severity summary:"
RED_COUNT=$(grep -cE '^### 🔴 ' "$MIGRATION_GUIDE" || true)
YELLOW_COUNT=$(grep -cE '^### 🟡 ' "$MIGRATION_GUIDE" || true)
GREEN_COUNT=$(grep -cE '^### 🟢 ' "$MIGRATION_GUIDE" || true)
echo "    🔴 必须改: $RED_COUNT entries"
echo "    🟡 建议改: $YELLOW_COUNT entries"
echo "    🟢 可选:   $GREEN_COUNT entries"
echo "    Total:     $((RED_COUNT + YELLOW_COUNT + GREEN_COUNT)) entries"
if [ "$((RED_COUNT + YELLOW_COUNT + GREEN_COUNT))" -gt 0 ]; then
    pass "Severity statistics collected successfully"
else
    fail "No severity-marked entries found"
fi

echo ""

# =============================================================================
# 6.2 Check Script CLI 交互验证
# =============================================================================
echo "--- 6.2 Check Script CLI 交互验证 ---"
echo ""

# 6.2.1 验证 --help 输出 usage 信息
HELP_OUTPUT=$(php "$CHECK_SCRIPT" --help 2>&1)
HELP_EXIT=$?
if [ "$HELP_EXIT" -eq 0 ] && echo "$HELP_OUTPUT" | grep -qi "usage"; then
    pass "--help outputs usage information (exit code $HELP_EXIT)"
else
    fail "--help did not output usage or had wrong exit code (exit code $HELP_EXIT)"
fi

# 6.2.2 验证对不存在的目录输出错误信息且 exit code 为 2
set +e
NONEXIST_OUTPUT=$(php "$CHECK_SCRIPT" /tmp/nonexistent-dir-xyz-12345 2>&1)
NONEXIST_EXIT=$?
set -e
if [ "$NONEXIST_EXIT" -eq 2 ] && echo "$NONEXIST_OUTPUT" | grep -qi "error"; then
    pass "Non-existent directory → error message + exit code 2"
else
    fail "Non-existent directory → exit code $NONEXIST_EXIT (expected 2)"
fi

# 6.2.3 验证对空目录输出提示信息且 exit code 为 0
EMPTY_DIR=$(mktemp -d)
set +e
EMPTY_OUTPUT=$(php "$CHECK_SCRIPT" "$EMPTY_DIR" 2>&1)
EMPTY_EXIT=$?
set -e
rmdir "$EMPTY_DIR"
if [ "$EMPTY_EXIT" -eq 0 ] && echo "$EMPTY_OUTPUT" | grep -qi "no php files"; then
    pass "Empty directory → info message + exit code 0"
else
    fail "Empty directory → exit code $EMPTY_EXIT (expected 0), output: $EMPTY_OUTPUT"
fi

# 6.2.4 验证 --format=json 输出有效 JSON
# Create a temp dir with a simple PHP file that has a known finding
JSON_TEST_DIR=$(mktemp -d)
cat > "$JSON_TEST_DIR/test.php" << 'PHPEOF'
<?php
use Oasis\Mlib\Http\SilexKernel;
PHPEOF

set +e
JSON_OUTPUT=$(php "$CHECK_SCRIPT" --format=json "$JSON_TEST_DIR" 2>/dev/null)
JSON_EXIT=$?
set -e
rm -rf "$JSON_TEST_DIR"

# Validate JSON
if echo "$JSON_OUTPUT" | php -r 'json_decode(file_get_contents("php://stdin")); exit(json_last_error() === JSON_ERROR_NONE ? 0 : 1);' 2>/dev/null; then
    pass "--format=json outputs valid JSON"
else
    fail "--format=json output is not valid JSON"
fi

# 6.2.5 验证 --format=invalid 输出错误信息且 exit code 为 2
set +e
INVALID_FMT_OUTPUT=$(php "$CHECK_SCRIPT" --format=invalid /tmp 2>&1)
INVALID_FMT_EXIT=$?
set -e
if [ "$INVALID_FMT_EXIT" -eq 2 ] && echo "$INVALID_FMT_OUTPUT" | grep -qi "unsupported format"; then
    pass "--format=invalid → error message + exit code 2"
else
    fail "--format=invalid → exit code $INVALID_FMT_EXIT (expected 2)"
fi

echo ""

# =============================================================================
# 6.3 Check Script 端到端扫描验证
# =============================================================================
echo "--- 6.3 Check Script 端到端扫描验证 ---"
echo ""

# Create a temporary test directory for end-to-end tests
E2E_DIR=$(mktemp -d)

# 6.3.1 创建包含已知 Removed_API 引用的测试 PHP 文件
cat > "$E2E_DIR/removed_api.php" << 'PHPEOF'
<?php
use Oasis\Mlib\Http\SilexKernel;
use Silex\Application;
use Pimple\Container;

$kernel = new SilexKernel($config);
PHPEOF

set +e
REMOVED_OUTPUT=$(php "$CHECK_SCRIPT" "$E2E_DIR" 2>/dev/null)
REMOVED_EXIT=$?
set -e

if echo "$REMOVED_OUTPUT" | grep -q "removed-silex-kernel" && \
   echo "$REMOVED_OUTPUT" | grep -q "removed-silex-app" && \
   echo "$REMOVED_OUTPUT" | grep -q "removed-pimple-container"; then
    pass "Removed_API references detected (SilexKernel, Silex\\Application, Pimple\\Container)"
else
    fail "Not all Removed_API references detected"
    echo "    Output: $(echo "$REMOVED_OUTPUT" | head -20)"
fi

# 6.3.2 创建包含 Pimple 访问模式的测试 PHP 文件
cat > "$E2E_DIR/pimple_access.php" << 'PHPEOF'
<?php
$app['logger'] = function() { return new Logger(); };
$service = $app['my.service'];
PHPEOF

set +e
PIMPLE_OUTPUT=$(php "$CHECK_SCRIPT" "$E2E_DIR" 2>/dev/null)
PIMPLE_EXIT=$?
set -e

if echo "$PIMPLE_OUTPUT" | grep -q "pimple-access"; then
    pass "Pimple access pattern (\$app['...']) detected"
else
    fail "Pimple access pattern not detected"
    echo "    Output: $(echo "$PIMPLE_OUTPUT" | head -20)"
fi

# 6.3.3 创建包含旧包引用的测试 composer.json
# Clean up previous PHP files first to isolate this test
rm -f "$E2E_DIR/removed_api.php" "$E2E_DIR/pimple_access.php"

cat > "$E2E_DIR/composer.json" << 'JSONEOF'
{
    "require": {
        "silex/silex": "^2.3",
        "silex/providers": "^2.3",
        "twig/extensions": "^1.3"
    }
}
JSONEOF

set +e
COMPOSER_OUTPUT=$(php "$CHECK_SCRIPT" "$E2E_DIR" 2>/dev/null)
COMPOSER_EXIT=$?
set -e

if echo "$COMPOSER_OUTPUT" | grep -q "old-pkg-silex" && \
   echo "$COMPOSER_OUTPUT" | grep -q "old-pkg-silex-providers" && \
   echo "$COMPOSER_OUTPUT" | grep -q "old-pkg-twig-ext"; then
    pass "Old package references in composer.json detected (silex/silex, silex/providers, twig/extensions)"
else
    fail "Not all old package references detected"
    echo "    Output: $(echo "$COMPOSER_OUTPUT" | head -20)"
fi

# 6.3.4 验证 text 输出中 🔴 findings 排在 🟡 之前
# Create files with mixed severity findings
rm -f "$E2E_DIR/composer.json"

cat > "$E2E_DIR/mixed.php" << 'PHPEOF'
<?php
use Oasis\Mlib\Http\SilexKernel;
$options = ['exceptions' => false];
PHPEOF

set +e
MIXED_OUTPUT=$(php "$CHECK_SCRIPT" "$E2E_DIR" 2>/dev/null)
MIXED_EXIT=$?
set -e

# Check that 🔴 section appears before 🟡 section in output
RED_POS=$(echo "$MIXED_OUTPUT" | grep -n '🔴' | head -1 | cut -d: -f1)
YELLOW_POS=$(echo "$MIXED_OUTPUT" | grep -n '🟡' | head -1 | cut -d: -f1)

if [ -n "$RED_POS" ] && [ -n "$YELLOW_POS" ] && [ "$RED_POS" -lt "$YELLOW_POS" ]; then
    pass "🔴 findings appear before 🟡 findings in text output (line $RED_POS < $YELLOW_POS)"
else
    fail "Severity ordering incorrect: 🔴 at line ${RED_POS:-N/A}, 🟡 at line ${YELLOW_POS:-N/A}"
fi

# 6.3.5 验证存在 🔴 finding 时 exit code 为 1
if [ "$MIXED_EXIT" -eq 1 ]; then
    pass "Exit code is 1 when 🔴 findings exist"
else
    fail "Exit code is $MIXED_EXIT (expected 1) when 🔴 findings exist"
fi

# Cleanup
rm -rf "$E2E_DIR"

echo ""

# =============================================================================
# Summary
# =============================================================================
echo "========================================"
echo " Test Summary"
echo "========================================"
echo "  Total:  $TOTAL_COUNT"
echo "  Passed: $PASS_COUNT"
echo "  Failed: $FAIL_COUNT"
echo "========================================"

if [ "$FAIL_COUNT" -gt 0 ]; then
    echo ""
    echo "❌ SOME TESTS FAILED"
    exit 1
else
    echo ""
    echo "✅ ALL TESTS PASSED"
    exit 0
fi
