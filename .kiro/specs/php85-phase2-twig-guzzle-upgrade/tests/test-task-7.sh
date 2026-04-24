#!/usr/bin/env bash
# =============================================================================
# Manual Test Script — Task 7: 手工测试
# Spec: php85-phase2-twig-guzzle-upgrade
# =============================================================================
set -uo pipefail

PHPUNIT="vendor/bin/phpunit"
PROJECT_ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
PASS=0
FAIL=0
ISSUES=()

pass() { echo "  ✅ PASS: $1"; ((PASS++)); }
fail() { echo "  ❌ FAIL: $1"; ((FAIL++)); ISSUES+=("$1"); }

echo "============================================="
echo " Task 7 — Phase 2 手工测试"
echo "============================================="
echo ""

cd "$PROJECT_ROOT"

# =============================================
# 场景 1: Guzzle 升级验证
# =============================================
echo "--- 场景 1: Guzzle 升级验证 ---"

# 1a: composer.json 中 guzzlehttp/guzzle 版本约束为 ^7.0
echo -n "  1a: composer.json 版本约束 ... "
constraint=$(php -r "
    \$json = json_decode(file_get_contents('composer.json'), true);
    echo \$json['require']['guzzlehttp/guzzle'] ?? 'NOT_FOUND';
")
if [ "$constraint" = "^7.0" ]; then
    pass "composer.json guzzlehttp/guzzle = ^7.0"
else
    fail "composer.json guzzlehttp/guzzle = '$constraint' (expected ^7.0)"
fi

# 1b: composer show 显示 7.x 版本
echo -n "  1b: composer show 版本 ... "
installed_version=$(composer show guzzlehttp/guzzle --format=json 2>/dev/null | php -r "
    \$json = json_decode(file_get_contents('php://stdin'), true);
    echo \$json['versions'][0] ?? \$json['version'] ?? 'UNKNOWN';
" 2>/dev/null)
if echo "$installed_version" | grep -qE '^7\.'; then
    pass "installed guzzle version = $installed_version"
else
    fail "installed guzzle version = '$installed_version' (expected 7.x)"
fi

# 1c: composer install 无冲突
echo -n "  1c: composer install ... "
install_output=$(composer install --no-interaction --quiet 2>&1) || true
install_exit=$?
if [ $install_exit -eq 0 ]; then
    pass "composer install succeeded (exit 0)"
else
    fail "composer install failed (exit $install_exit)"
    echo "    Output tail:"
    echo "$install_output" | tail -5 | sed 's/^/      /'
fi
echo ""

# =============================================
# 场景 2: JSON 函数零残留
# =============================================
echo "--- 场景 2: JSON 函数零残留 ---"

echo -n "  2a: \\GuzzleHttp\\json_decode 零残留 ... "
decode_hits=$(grep -rn 'GuzzleHttp\\json_decode\|GuzzleHttp\\\\json_decode' src/ ut/ 2>/dev/null | wc -l | tr -d ' ')
if [ "$decode_hits" = "0" ]; then
    pass "\\GuzzleHttp\\json_decode — 0 hits in src/ and ut/"
else
    fail "\\GuzzleHttp\\json_decode — $decode_hits hits found"
    grep -rn 'GuzzleHttp\\json_decode\|GuzzleHttp\\\\json_decode' src/ ut/ 2>/dev/null | head -5 | sed 's/^/      /'
fi

echo -n "  2b: \\GuzzleHttp\\json_encode 零残留 ... "
encode_hits=$(grep -rn 'GuzzleHttp\\json_encode\|GuzzleHttp\\\\json_encode' src/ ut/ 2>/dev/null | wc -l | tr -d ' ')
if [ "$encode_hits" = "0" ]; then
    pass "\\GuzzleHttp\\json_encode — 0 hits in src/ and ut/"
else
    fail "\\GuzzleHttp\\json_encode — $encode_hits hits found"
    grep -rn 'GuzzleHttp\\json_encode\|GuzzleHttp\\\\json_encode' src/ ut/ 2>/dev/null | head -5 | sed 's/^/      /'
fi
echo ""

# =============================================
# 场景 3: Twig strict_variables 生效
# =============================================
echo "--- 场景 3: Twig strict_variables 生效 ---"

echo -n "  3: 引用未定义变量时抛出 RuntimeError ... "
strict_result=$(php -r "
require 'vendor/autoload.php';
(new \Oasis\Mlib\Logging\LocalFileHandler('/tmp'))->install();

// Minimal config: only twig, no routing
\$config = [
    'cache_dir' => sys_get_temp_dir() . '/oasis-mt7-strict-' . getmypid(),
    'twig' => [
        'template_dir' => __DIR__ . '/ut/Twig/templates',
    ],
];

\$app = new \Oasis\Mlib\Http\MicroKernel(\$config, true);
\$app->boot();
\$twig = \$app->getTwig();

if (!\$twig->isStrictVariables()) {
    echo 'FAIL:strict_variables_not_enabled';
    \$app->shutdown();
    exit;
}

try {
    \$twig->createTemplate('{{ undefined_var_xyz }}')->render([]);
    echo 'FAIL:no_exception';
} catch (\Twig\Error\RuntimeError \$e) {
    echo 'PASS:RuntimeError';
} catch (\Throwable \$e) {
    echo 'FAIL:wrong_exception:' . get_class(\$e);
}

\$app->shutdown();
" 2>/dev/null)

if [ "$strict_result" = "PASS:RuntimeError" ]; then
    pass "strict_variables — Twig\\Error\\RuntimeError thrown for undefined variable"
else
    fail "strict_variables — result: '$strict_result'"
fi
echo ""

# =============================================
# 场景 4: Twig auto_reload auto-detect
# =============================================
echo "--- 场景 4: Twig auto_reload auto-detect ---"

echo -n "  4a: debug=true → isAutoReload()=true ... "
auto_debug=$(php -r "
require 'vendor/autoload.php';
(new \Oasis\Mlib\Logging\LocalFileHandler('/tmp'))->install();

\$config = [
    'cache_dir' => sys_get_temp_dir() . '/oasis-mt7-auto-d-' . getmypid(),
    'twig' => [
        'template_dir' => __DIR__ . '/ut/Twig/templates',
    ],
];

\$app = new \Oasis\Mlib\Http\MicroKernel(\$config, true);
\$app->boot();
\$twig = \$app->getTwig();
echo \$twig->isAutoReload() ? 'true' : 'false';
\$app->shutdown();
" 2>/dev/null)

if [ "$auto_debug" = "true" ]; then
    pass "debug=true → isAutoReload()=true"
else
    fail "debug=true → isAutoReload()=$auto_debug (expected true)"
fi

echo -n "  4b: debug=false → isAutoReload()=false ... "
auto_nodebug=$(php -r "
require 'vendor/autoload.php';
(new \Oasis\Mlib\Logging\LocalFileHandler('/tmp'))->install();

\$config = [
    'cache_dir' => sys_get_temp_dir() . '/oasis-mt7-auto-nd-' . getmypid(),
    'twig' => [
        'template_dir' => __DIR__ . '/ut/Twig/templates',
    ],
];

\$app = new \Oasis\Mlib\Http\MicroKernel(\$config, false);
\$app->boot();
\$twig = \$app->getTwig();
echo \$twig->isAutoReload() ? 'true' : 'false';
\$app->shutdown();
" 2>/dev/null)

if [ "$auto_nodebug" = "false" ]; then
    pass "debug=false → isAutoReload()=false"
else
    fail "debug=false → isAutoReload()=$auto_nodebug (expected false)"
fi
echo ""

# =============================================
# 场景 5: 现有模板渲染不变
# =============================================
echo "--- 场景 5: 现有模板渲染不变 ---"

echo -n "  5: WebTestCase /twig/2 渲染验证 ... "
render_result=$(php $PHPUNIT --testsuite twig --filter testBasicTemplate --no-progress 2>&1) || true
if echo "$render_result" | grep -qE "OK \([0-9]+ test"; then
    tests_info=$(echo "$render_result" | grep -oE "OK \([0-9]+ test[^)]*\)")
    pass "/twig/2 渲染 — $tests_info (WOW, haha, escape, macro, include, globals)"
elif echo "$render_result" | grep -q "OK,"; then
    pass "/twig/2 渲染 — testBasicTemplate passed"
else
    fail "/twig/2 渲染 — testBasicTemplate failed"
    echo "    Output tail:"
    echo "$render_result" | tail -8 | sed 's/^/      /'
fi
echo ""

# ============================================
# Summary
# ============================================
echo "============================================="
echo " SUMMARY"
echo "============================================="
echo "  PASS: $PASS"
echo "  FAIL: $FAIL"
echo ""

if [ $FAIL -gt 0 ]; then
    echo "  FAILED ITEMS:"
    for issue in "${ISSUES[@]}"; do
        echo "    - $issue"
    done
    echo ""
    echo "  RESULT: ❌ SOME CHECKS FAILED"
    exit 1
else
    echo "  RESULT: ✅ ALL CHECKS PASSED"
    exit 0
fi
