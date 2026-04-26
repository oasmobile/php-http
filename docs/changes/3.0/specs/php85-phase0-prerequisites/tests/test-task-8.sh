#!/usr/bin/env bash
# =============================================================================
# Manual Test Script — Task 8: 手工测试
# Spec: php85-phase0-prerequisites
# =============================================================================
set -euo pipefail

PASS=0
FAIL=0
DETAILS=""
PROJECT_ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"

report() {
    local status="$1"
    local task="$2"
    local detail="${3:-}"
    if [ "$status" = "PASS" ]; then
        PASS=$((PASS + 1))
        DETAILS="${DETAILS}\n  ✅ PASS: ${task}"
    else
        FAIL=$((FAIL + 1))
        DETAILS="${DETAILS}\n  ❌ FAIL: ${task} — ${detail}"
    fi
}

echo "========================================"
echo " Task 8: Manual Test Execution"
echo " Project root: ${PROJECT_ROOT}"
echo "========================================"
echo ""

# -------------------------------------------------------
# 8.1 验证 phpunit.xml 中 14 个 suite 定义完整
# -------------------------------------------------------
echo "--- 8.1: Verify 14 suite definitions in phpunit.xml ---"

EXPECTED_SUITES="all exceptions cors security twig aws error-handlers configuration views routing cookie middlewares misc integration"
FOUND_SUITES=$(grep -o 'name="[^"]*"' "${PROJECT_ROOT}/phpunit.xml" | sed 's/name="//;s/"//' | sort)
EXPECTED_SORTED=$(echo "$EXPECTED_SUITES" | tr ' ' '\n' | sort)
FOUND_COUNT=$(echo "$FOUND_SUITES" | wc -l | tr -d ' ')

MISSING=""
for suite in $EXPECTED_SUITES; do
    if ! echo "$FOUND_SUITES" | grep -qx "$suite"; then
        MISSING="${MISSING} ${suite}"
    fi
done

EXTRA=""
for suite in $FOUND_SUITES; do
    if ! echo "$EXPECTED_SORTED" | grep -qx "$suite"; then
        EXTRA="${EXTRA} ${suite}"
    fi
done

if [ "$FOUND_COUNT" -eq 14 ] && [ -z "$MISSING" ] && [ -z "$EXTRA" ]; then
    report "PASS" "8.1"
    echo "  Found all 14 suites: $(echo $FOUND_SUITES | tr '\n' ' ')"
else
    detail="count=${FOUND_COUNT}"
    [ -n "$MISSING" ] && detail="${detail}, missing:${MISSING}"
    [ -n "$EXTRA" ] && detail="${detail}, extra:${EXTRA}"
    report "FAIL" "8.1" "$detail"
    echo "  $detail"
fi
echo ""

# -------------------------------------------------------
# 8.2 验证 all suite 使用 <directory>ut</directory>
# -------------------------------------------------------
echo "--- 8.2: Verify 'all' suite directory coverage ---"

ALL_SUITE_DIR=$(sed -n '/<testsuite name="all">/,/<\/testsuite>/p' "${PROJECT_ROOT}/phpunit.xml" | grep -c '<directory>ut</directory>' || true)

if [ "$ALL_SUITE_DIR" -ne 1 ]; then
    report "FAIL" "8.2" "all suite does not use <directory>ut</directory>"
    echo "  all suite does not use <directory>ut</directory>"
else
    # List all *Test.php files under ut/
    ALL_TEST_FILES=$(find "${PROJECT_ROOT}/ut" -name '*Test.php' -type f | sed "s|${PROJECT_ROOT}/||" | sort)

    # Known test files from original all suite (before directory migration):
    ORIGINAL_FILES="ut/AwsTests/ElbTrustedProxyTest.php
ut/Configuration/CacheableRouterConfigurationTest.php
ut/Configuration/ConfigurationValidationTraitTest.php
ut/Configuration/CrossOriginResourceSharingConfigurationTest.php
ut/Configuration/HttpConfigurationTest.php
ut/Configuration/SecurityConfigurationTest.php
ut/Configuration/SimpleAccessRuleConfigurationTest.php
ut/Configuration/SimpleFirewallConfigurationTest.php
ut/Configuration/TwigConfigurationTest.php
ut/Cookie/ResponseCookieContainerTest.php
ut/Cookie/SimpleCookieProviderTest.php
ut/Cors/CrossOriginResourceSharingAdvancedTest.php
ut/Cors/CrossOriginResourceSharingTest.php
ut/ErrorHandlers/ExceptionWrapperTest.php
ut/ErrorHandlers/JsonErrorHandlerTest.php
ut/ErrorHandlers/WrappedExceptionInfoTest.php
ut/FallbackViewHandlerTest.php
ut/HttpExceptionTest.php
ut/Integration/BootstrapConfigurationIntegrationTest.php
ut/Integration/SecurityAuthenticationFlowIntegrationTest.php
ut/Integration/SilexKernelCrossCommunityIntegrationTest.php
ut/Middlewares/AbstractMiddlewareTest.php
ut/Misc/ChainedParameterBagDataProviderTest.php
ut/Misc/ExtendedArgumentValueResolverTest.php
ut/Misc/ExtendedExceptionListnerWrapperTest.php
ut/Misc/UniquenessViolationHttpExceptionTest.php
ut/Routing/CacheableRouterProviderTest.php
ut/Routing/CacheableRouterTest.php
ut/Routing/CacheableRouterUrlMatcherWrapperTest.php
ut/Routing/GroupUrlGeneratorTest.php
ut/Routing/GroupUrlMatcherTest.php
ut/Routing/InheritableRouteCollectionTest.php
ut/Routing/InheritableYamlFileLoaderTest.php
ut/Security/NullEntryPointTest.php
ut/Security/SecurityServiceProviderConfigurationTest.php
ut/Security/SecurityServiceProviderTest.php
ut/SilexKernelTest.php
ut/SilexKernelWebTest.php
ut/Twig/TwigServiceProviderConfigurationTest.php
ut/Twig/TwigServiceProviderTest.php
ut/Views/AbstractSmartViewHandlerTest.php
ut/Views/DefaultHtmlRendererTest.php
ut/Views/JsonApiRendererTest.php
ut/Views/JsonViewHandlerTest.php
ut/Views/PrefilightResponseTest.php
ut/Views/RouteBasedResponseRendererResolverTest.php"

    ORIGINAL_SORTED=$(echo "$ORIGINAL_FILES" | sort)
    ORIG_COUNT=$(echo "$ORIGINAL_SORTED" | wc -l | tr -d ' ')

    # Check all original files are found
    MISSING_FROM_DIR=""
    while IFS= read -r f; do
        if ! echo "$ALL_TEST_FILES" | grep -qx "$f"; then
            MISSING_FROM_DIR="${MISSING_FROM_DIR} ${f}"
        fi
    done <<< "$ORIGINAL_SORTED"

    # Check for extra test files
    EXTRA_IN_DIR=""
    while IFS= read -r f; do
        if ! echo "$ORIGINAL_SORTED" | grep -qx "$f"; then
            EXTRA_IN_DIR="${EXTRA_IN_DIR} ${f}"
        fi
    done <<< "$ALL_TEST_FILES"

    if [ -z "$MISSING_FROM_DIR" ] && [ -z "$EXTRA_IN_DIR" ]; then
        report "PASS" "8.2"
        echo "  all suite covers all ${ORIG_COUNT} original test files exactly, no extras"
    elif [ -z "$MISSING_FROM_DIR" ] && [ -n "$EXTRA_IN_DIR" ]; then
        # Extra files are acceptable — new tests added during this spec
        report "PASS" "8.2"
        echo "  all suite covers all ${ORIG_COUNT} original files. Extra test files (acceptable):${EXTRA_IN_DIR}"
    else
        detail=""
        [ -n "$MISSING_FROM_DIR" ] && detail="missing:${MISSING_FROM_DIR}"
        [ -n "$EXTRA_IN_DIR" ] && detail="${detail} extra:${EXTRA_IN_DIR}"
        report "FAIL" "8.2" "$detail"
        echo "  $detail"
    fi
fi
echo ""

# -------------------------------------------------------
# 8.3 验证 composer.json 版本约束
# -------------------------------------------------------
echo "--- 8.3: Verify composer.json version constraints ---"

PHP_CONSTRAINT=$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["require"]["php"];' "${PROJECT_ROOT}/composer.json")
LOGGING_CONSTRAINT=$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["require"]["oasis/logging"];' "${PROJECT_ROOT}/composer.json")
UTILS_CONSTRAINT=$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["require"]["oasis/utils"];' "${PROJECT_ROOT}/composer.json")
PHPUNIT_CONSTRAINT=$(php -r '$j=json_decode(file_get_contents($argv[1]),true); echo $j["require-dev"]["phpunit/phpunit"];' "${PROJECT_ROOT}/composer.json")

ERRORS_83=""

# R1 AC1: php >= 8.5
if [ "$PHP_CONSTRAINT" != ">=8.5" ]; then
    ERRORS_83="${ERRORS_83} php=${PHP_CONSTRAINT}(expected>=8.5)"
fi

# R2 AC1-AC2: oasis/logging and oasis/utils use ^ constraint
case "$LOGGING_CONSTRAINT" in
    ^*) ;;
    *) ERRORS_83="${ERRORS_83} oasis/logging=${LOGGING_CONSTRAINT}(expected ^x.y)" ;;
esac
case "$UTILS_CONSTRAINT" in
    ^*) ;;
    *) ERRORS_83="${ERRORS_83} oasis/utils=${UTILS_CONSTRAINT}(expected ^x.y)" ;;
esac

# R3 AC1: phpunit ^13.0
if [ "$PHPUNIT_CONSTRAINT" != "^13.0" ]; then
    ERRORS_83="${ERRORS_83} phpunit=${PHPUNIT_CONSTRAINT}(expected ^13.0)"
fi

# Verify composer validate passes
COMPOSER_VALIDATE=$(cd "${PROJECT_ROOT}" && composer validate 2>&1) || true
if echo "$COMPOSER_VALIDATE" | grep -qi "error"; then
    ERRORS_83="${ERRORS_83} composer-validate-failed"
fi

# Verify phpunit version is 13.x (filter out deprecation warnings)
PHPUNIT_VERSION=$("${PROJECT_ROOT}/vendor/bin/phpunit" --version 2>/dev/null | grep "PHPUnit" | head -1)
if [ -z "$PHPUNIT_VERSION" ]; then
    PHPUNIT_VERSION=$("${PROJECT_ROOT}/vendor/bin/phpunit" --version 2>&1 | grep "PHPUnit" | head -1)
fi
if ! echo "$PHPUNIT_VERSION" | grep -q "PHPUnit 13\."; then
    ERRORS_83="${ERRORS_83} phpunit-version=${PHPUNIT_VERSION}"
fi

if [ -z "$ERRORS_83" ]; then
    report "PASS" "8.3"
    echo "  php=${PHP_CONSTRAINT}, oasis/logging=${LOGGING_CONSTRAINT}, oasis/utils=${UTILS_CONSTRAINT}, phpunit=${PHPUNIT_CONSTRAINT}"
    echo "  ${PHPUNIT_VERSION}"
    echo "  composer validate: OK"
else
    report "FAIL" "8.3" "$ERRORS_83"
    echo "  $ERRORS_83"
fi
echo ""

# -------------------------------------------------------
# 8.4 验证 ut/bootstrap.php 在 PHP 8.5 下加载无错误
# -------------------------------------------------------
echo "--- 8.4: Verify ut/bootstrap.php loads without errors under PHP 8.5 ---"

PHP_VERSION=$(php -v | head -1)
echo "  PHP version: ${PHP_VERSION}"

# Test 1: bootstrap.php loads without errors
# Note: PHP deprecation warnings from third-party dependencies (e.g. guzzlehttp)
# are expected and not in scope for this spec. We check that bootstrap completes
# successfully (outputs "OK") regardless of deprecation notices.
BOOTSTRAP_OUTPUT=$(php -r 'chdir($argv[1]); ob_start(); require "ut/bootstrap.php"; ob_end_clean(); echo "OK";' "${PROJECT_ROOT}" 2>&1)

if echo "$BOOTSTRAP_OUTPUT" | grep -q "^OK$\|OK$"; then
    echo "  bootstrap.php loads: OK"
    # Check for fatal errors (not deprecation warnings)
    if echo "$BOOTSTRAP_OUTPUT" | grep -qi "fatal error"; then
        echo "  WARNING: Fatal error detected in bootstrap output"
        BOOTSTRAP_OK=0
    else
        BOOTSTRAP_OK=1
        if echo "$BOOTSTRAP_OUTPUT" | grep -qi "deprecated"; then
            echo "  NOTE: Deprecation warnings from third-party deps (not in scope)"
        fi
    fi
else
    echo "  bootstrap.php load error: ${BOOTSTRAP_OUTPUT}"
    BOOTSTRAP_OK=0
fi

# Test 2: Framework_Independent classes can be loaded
CLASS_LOAD_SCRIPT="${PROJECT_ROOT}/.kiro/specs/php85-phase0-prerequisites/tests/_check_classes.php"
cat > "$CLASS_LOAD_SCRIPT" << 'EOF'
<?php
chdir($argv[1]);
require 'vendor/autoload.php';
$classes = [
    'Oasis\Mlib\Http\Configuration\HttpConfiguration',
    'Oasis\Mlib\Http\Configuration\SecurityConfiguration',
    'Oasis\Mlib\Http\Configuration\CacheableRouterConfiguration',
    'Oasis\Mlib\Http\Configuration\TwigConfiguration',
    'Oasis\Mlib\Http\Configuration\SimpleAccessRuleConfiguration',
    'Oasis\Mlib\Http\Configuration\SimpleFirewallConfiguration',
    'Oasis\Mlib\Http\Configuration\CrossOriginResourceSharingConfiguration',
    'Oasis\Mlib\Http\ErrorHandlers\JsonErrorHandler',
    'Oasis\Mlib\Http\ErrorHandlers\ExceptionWrapper',
    'Oasis\Mlib\Http\ErrorHandlers\WrappedExceptionInfo',
    'Oasis\Mlib\Http\Exceptions\UniquenessViolationHttpException',
    'Oasis\Mlib\Http\ChainedParameterBagDataProvider',
];
$ok = true;
foreach ($classes as $c) {
    if (!class_exists($c) && !trait_exists($c)) {
        echo "FAIL: $c not loadable\n";
        $ok = false;
    }
}
if ($ok) echo 'ALL_OK';
EOF
CLASS_LOAD_OUTPUT=$(php "$CLASS_LOAD_SCRIPT" "${PROJECT_ROOT}" 2>&1)

if echo "$CLASS_LOAD_OUTPUT" | grep -q "ALL_OK"; then
    echo "  Framework_Independent classes loadable: OK"
    CLASS_OK=1
else
    echo "  Class loading issues: ${CLASS_LOAD_OUTPUT}"
    CLASS_OK=0
fi

# Test 3: Verify LocalFileHandler from oasis/logging works
# Same note: deprecation warnings from third-party deps are tolerated
LFH_OUTPUT=$(php -r 'chdir($argv[1]); require "vendor/autoload.php"; (new Oasis\Mlib\Logging\LocalFileHandler("/tmp"))->install(); echo "OK";' "${PROJECT_ROOT}" 2>&1)

if echo "$LFH_OUTPUT" | grep -q "OK"; then
    echo "  LocalFileHandler::install(): OK"
    if echo "$LFH_OUTPUT" | grep -qi "fatal error"; then
        LFH_OK=0
    else
        LFH_OK=1
    fi
else
    echo "  LocalFileHandler error: ${LFH_OUTPUT}"
    LFH_OK=0
fi

# Cleanup helper script
rm -f "$CLASS_LOAD_SCRIPT"

if [ "$BOOTSTRAP_OK" -eq 1 ] && [ "$CLASS_OK" -eq 1 ] && [ "$LFH_OK" -eq 1 ]; then
    report "PASS" "8.4"
else
    detail=""
    [ "$BOOTSTRAP_OK" -eq 0 ] && detail="${detail} bootstrap-load-failed"
    [ "$CLASS_OK" -eq 0 ] && detail="${detail} class-load-failed"
    [ "$LFH_OK" -eq 0 ] && detail="${detail} LocalFileHandler-failed"
    report "FAIL" "8.4" "$detail"
fi
echo ""

# -------------------------------------------------------
# Summary
# -------------------------------------------------------
echo "========================================"
echo " Summary"
echo "========================================"
echo -e "$DETAILS"
echo ""
echo "  Total: $((PASS + FAIL))  PASS: ${PASS}  FAIL: ${FAIL}"
echo ""

if [ "$FAIL" -gt 0 ]; then
    echo "RESULT: FAIL"
    exit 1
else
    echo "RESULT: ALL PASS"
    exit 0
fi
