#!/usr/bin/env bash
# =============================================================
# Test Task 2a — Migration Guide 手工验证
# Spec: .kiro/specs/release-3.0/
# Ref: Requirement 2, AC 1–4
# =============================================================
set -euo pipefail

PASS=0
FAIL=0
TOTAL=0

pass() { ((PASS++)); ((TOTAL++)); echo "  ✅ PASS: $1"; }
fail() { ((FAIL++)); ((TOTAL++)); echo "  ❌ FAIL: $1"; }

GUIDE="docs/manual/migration-v3.md"

echo "=== Migration Guide 手工验证 ==="
echo ""

# ─── 2.2.1 文件存在且包含全部 12 个模块章节 + PHP 语言适配 + 附录 ───
echo "--- 2.2.1 文件存在且包含全部章节 ---"

if [[ ! -f "$GUIDE" ]]; then
    fail "Migration Guide 文件不存在: $GUIDE"
else
    pass "Migration Guide 文件存在"

    # 检查 12 个模块章节 + PHP 语言适配 + 附录 = 14 个二级标题
    EXPECTED_CHAPTERS=(
        "## 1. PHP Version"
        "## 2. Dependencies"
        "## 3. Kernel API"
        "## 4. DI Container"
        "## 5. Bootstrap Config"
        "## 6. Routing"
        "## 7. Security"
        "## 8. Middleware"
        "## 9. Views"
        "## 10. Twig"
        "## 11. CORS"
        "## 12. Cookie"
        "## 13. PHP 语言适配"
        "## 14. 附录"
    )

    ALL_CHAPTERS_FOUND=true
    for chapter in "${EXPECTED_CHAPTERS[@]}"; do
        if ! grep -qF "$chapter" "$GUIDE"; then
            fail "缺少章节: $chapter"
            ALL_CHAPTERS_FOUND=false
        fi
    done

    if $ALL_CHAPTERS_FOUND; then
        pass "全部 14 个章节（12 模块 + PHP 语言适配 + 附录）均存在"
    fi
fi

echo ""

# ─── 2.2.2 TOC 锚点链接解析到有效 heading ───
echo "--- 2.2.2 TOC 锚点链接验证 ---"

# 提取 TOC 中的锚点链接
TOC_ANCHORS=()
while IFS= read -r line; do
    # 提取 (#xxx) 中的 xxx — 使用 sed 替代 grep -P（macOS 兼容）
    anchor=$(echo "$line" | sed -n 's/.*(\(#[^)]*\)).*/\1/p')
    if [[ -n "$anchor" ]]; then
        TOC_ANCHORS+=("$anchor")
    fi
done < <(sed -n '/^## 目录$/,/^---$/p' "$GUIDE" | grep '^\- \[')

if [[ ${#TOC_ANCHORS[@]} -eq 0 ]]; then
    fail "未找到 TOC 锚点链接"
else
    # 使用 PHP 生成 GitHub 风格锚点（正确处理 Unicode）
    ALL_HEADINGS_ANCHORS_STR=$(grep '^## ' "$GUIDE" | php -r '
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            // 去掉 ## 前缀
            $heading = preg_replace("/^#+\s*/", "", $line);
            // 转小写（支持 Unicode）
            $anchor = mb_strtolower($heading, "UTF-8");
            // 空格和连续空白 → -
            $anchor = preg_replace("/\s+/", "-", $anchor);
            // 移除非字母数字、非中文、非连字符的字符
            $anchor = preg_replace("/[^\p{L}\p{N}_-]/u", "", $anchor);
            // 合并连续连字符
            $anchor = preg_replace("/-{2,}/", "-", $anchor);
            echo "#" . $anchor . "\n";
        }
    ')

    ALL_ANCHORS_VALID=true
    for anchor in "${TOC_ANCHORS[@]}"; do
        if ! echo "$ALL_HEADINGS_ANCHORS_STR" | grep -qF "$anchor"; then
            fail "TOC 锚点无效: $anchor"
            ALL_ANCHORS_VALID=false
        fi
    done

    if $ALL_ANCHORS_VALID; then
        pass "全部 ${#TOC_ANCHORS[@]} 个 TOC 锚点链接均解析到有效 heading"
    fi
fi

echo ""

# ─── 2.2.3 每个 breaking change 条目包含 severity marker、before/after 代码块、action 描述 ───
echo "--- 2.2.3 Breaking change 条目格式验证 ---"

# 提取所有三级标题（breaking change 条目）
# 排除非 breaking change 的标题（如参考表、API 列表、说明性标题）
BREAKING_CHANGE_SECTIONS=()
while IFS= read -r line; do
    BREAKING_CHANGE_SECTIONS+=("$line")
done < <(grep '^### ' "$GUIDE" | grep -E '🔴|🟡|🟢')

if [[ ${#BREAKING_CHANGE_SECTIONS[@]} -eq 0 ]]; then
    fail "未找到带 severity marker 的 breaking change 条目"
else
    # 验证每个带 severity marker 的条目
    BC_ALL_VALID=true

    for section in "${BREAKING_CHANGE_SECTIONS[@]}"; do
        # 检查 severity marker
        HAS_MARKER=false
        if echo "$section" | grep -qE '🔴|🟡|🟢'; then
            HAS_MARKER=true
        fi

        if ! $HAS_MARKER; then
            fail "条目缺少 severity marker: $section"
            BC_ALL_VALID=false
        fi
    done

    # 验证文档中存在 Before/After 代码块对
    BEFORE_COUNT=$(grep -c '^\*\*Before\*\*' "$GUIDE" || true)
    AFTER_COUNT=$(grep -c '^\*\*After\*\*' "$GUIDE" || true)

    if [[ $BEFORE_COUNT -eq 0 ]]; then
        fail "未找到 **Before** 代码块"
        BC_ALL_VALID=false
    fi

    if [[ $AFTER_COUNT -eq 0 ]]; then
        fail "未找到 **After** 代码块"
        BC_ALL_VALID=false
    fi

    if [[ $BEFORE_COUNT -ne $AFTER_COUNT ]]; then
        fail "Before ($BEFORE_COUNT) 和 After ($AFTER_COUNT) 代码块数量不匹配"
        BC_ALL_VALID=false
    fi

    # 验证存在 **操作** 描述
    ACTION_COUNT=$(grep -c '^\*\*操作\*\*' "$GUIDE" || true)
    if [[ $ACTION_COUNT -eq 0 ]]; then
        fail "未找到 **操作** 描述"
        BC_ALL_VALID=false
    fi

    if $BC_ALL_VALID; then
        pass "全部 ${#BREAKING_CHANGE_SECTIONS[@]} 个 breaking change 条目均包含 severity marker"
        pass "Before/After 代码块配对完整 ($BEFORE_COUNT 对)"
        pass "操作描述存在 ($ACTION_COUNT 条)"
    fi
fi

echo ""

# ─── 2.2.4 Bootstrap Config Key 参考表覆盖 architecture.md 中定义的所有 key ───
echo "--- 2.2.4 Bootstrap Config Key 参考表覆盖验证 ---"

ARCH_FILE="docs/state/architecture.md"

if [[ ! -f "$ARCH_FILE" ]]; then
    fail "architecture.md 不存在: $ARCH_FILE"
else
    # 从 architecture.md 提取 Bootstrap Config 表中的 key
    ARCH_KEYS=()
    while IFS= read -r line; do
        key=$(echo "$line" | sed -n 's/^| `\([^`]*\)`.*/\1/p')
        if [[ -n "$key" ]]; then
            ARCH_KEYS+=("$key")
        fi
    done < <(sed -n '/^## Bootstrap Config 结构$/,/^---$/p' "$ARCH_FILE" | grep '^| `')

    # 从 migration-v3.md 提取 Bootstrap Config Key 参考表中的 key
    GUIDE_KEYS=()
    while IFS= read -r line; do
        key=$(echo "$line" | sed -n 's/^| `\([^`]*\)`.*/\1/p')
        if [[ -n "$key" ]]; then
            GUIDE_KEYS+=("$key")
        fi
    done < <(sed -n '/^### Bootstrap_Config Key 参考表$/,/^---$/p' "$GUIDE" | grep '^| `')

    if [[ ${#ARCH_KEYS[@]} -eq 0 ]]; then
        fail "未从 architecture.md 提取到 Bootstrap Config key"
    elif [[ ${#GUIDE_KEYS[@]} -eq 0 ]]; then
        fail "未从 Migration Guide 提取到 Bootstrap Config Key 参考表 key"
    else
        ALL_KEYS_COVERED=true
        for arch_key in "${ARCH_KEYS[@]}"; do
            FOUND=false
            for guide_key in "${GUIDE_KEYS[@]}"; do
                if [[ "$arch_key" == "$guide_key" ]]; then
                    FOUND=true
                    break
                fi
            done
            if ! $FOUND; then
                fail "architecture.md 中的 key '$arch_key' 未在 Migration Guide 参考表中覆盖"
                ALL_KEYS_COVERED=false
            fi
        done

        if $ALL_KEYS_COVERED; then
            pass "Migration Guide 参考表覆盖 architecture.md 全部 ${#ARCH_KEYS[@]} 个 Bootstrap Config key"
        fi
    fi
fi

echo ""

# ─── 汇总 ───
echo "=== Migration Guide 验证汇总 ==="
echo "  Total: $TOTAL"
echo "  Pass:  $PASS"
echo "  Fail:  $FAIL"
echo ""

if [[ $FAIL -gt 0 ]]; then
    echo "❌ Migration Guide 验证未通过"
    exit 1
else
    echo "✅ Migration Guide 验证全部通过"
    exit 0
fi
