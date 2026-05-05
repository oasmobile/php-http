#!/usr/bin/env bash
# Manual Test Script for Task 10 - Release 3.3.0
# Verifies: alpha tag, full test suite, static analysis, scenario test coverage
set -euo pipefail

PASS=0
FAIL=0

report() {
  local status=$1 desc=$2
  if [ "$status" = "PASS" ]; then
    echo "  [PASS] $desc"
    PASS=$((PASS + 1))
  else
    echo "  [FAIL] $desc"
    FAIL=$((FAIL + 1))
  fi
}

echo "=========================================="
echo " Task 10 - Manual Testing for v3.3.0"
echo "=========================================="
echo ""

# --- 10.1 Alpha Tag ---
echo "--- 10.1 Alpha Tag ---"
TAG=$(git tag -l 'v3.3.0-alpha*' | sort -V | tail -1)
if [ -n "$TAG" ]; then
  report PASS "Alpha tag exists: $TAG"
else
  report FAIL "No v3.3.0-alpha* tag found"
fi
echo ""

# --- 10.2 Full Test Suite ---
echo "--- 10.2 Full Test Suite ---"
TEST_OUTPUT=$(php vendor/bin/phpunit 2>&1)
if echo "$TEST_OUTPUT" | grep -q "^OK"; then
  STATS=$(echo "$TEST_OUTPUT" | grep -oE '[0-9]+ tests, [0-9]+ assertions')
  report PASS "All tests passed ($STATS)"
else
  report FAIL "Test suite has failures"
  echo "$TEST_OUTPUT" | tail -20
fi
echo ""

# --- 10.3 Static Analysis ---
echo "--- 10.3 Static Analysis ---"
STAN_OUTPUT=$(php vendor/bin/phpstan analyse 2>&1)
if echo "$STAN_OUTPUT" | grep -q "No errors"; then
  report PASS "PHPStan level 8: zero errors"
else
  report FAIL "PHPStan reported errors"
  echo "$STAN_OUTPUT" | tail -20
fi
echo ""

# --- 10.4 Scenario Test Coverage ---
echo "--- 10.4 Scenario Test Coverage ---"

# 10.4a: 8 ScenarioTest files exist
SCENARIO_FILES=(
  tests/Security/SecurityScenarioTest.php
  tests/Routing/RoutingScenarioTest.php
  tests/Middlewares/MiddlewareScenarioTest.php
  tests/Cors/CorsScenarioTest.php
  tests/ErrorHandlers/ErrorHandlerScenarioTest.php
  tests/Twig/TwigScenarioTest.php
  tests/Cookie/CookieScenarioTest.php
  tests/Integration/MicroKernelAggregationScenarioTest.php
)
ALL_EXIST=true
for f in "${SCENARIO_FILES[@]}"; do
  if [ ! -f "$f" ]; then
    echo "    MISSING: $f"
    ALL_EXIST=false
  fi
done
if [ "$ALL_EXIST" = true ]; then
  report PASS "All 8 ScenarioTest files exist"
else
  report FAIL "Some ScenarioTest files missing"
fi

# 10.4b: ScenarioTestCase base class exists and is inherited
if [ -f "tests/Helpers/ScenarioTestCase.php" ]; then
  INHERIT_OK=true
  for f in "${SCENARIO_FILES[@]}"; do
    if ! grep -q "extends ScenarioTestCase" "$f"; then
      echo "    NOT INHERITING: $f"
      INHERIT_OK=false
    fi
  done
  if [ "$INHERIT_OK" = true ]; then
    report PASS "ScenarioTestCase base class exists and all tests inherit it"
  else
    report FAIL "Some tests do not extend ScenarioTestCase"
  fi
else
  report FAIL "ScenarioTestCase base class missing"
fi

# 10.4c: Audit_Matrix files archived
AUDIT_DIR="docs/changes/3.3/audit"
EXPECTED_AUDITS=(
  security-audit-matrix.md
  routing-audit-matrix.md
  middleware-audit-matrix.md
  cors-audit-matrix.md
  error-handling-audit-matrix.md
  twig-audit-matrix.md
  cookie-audit-matrix.md
  microkernel-aggregation-audit-matrix.md
)
AUDIT_OK=true
for f in "${EXPECTED_AUDITS[@]}"; do
  if [ ! -f "$AUDIT_DIR/$f" ]; then
    echo "    MISSING: $AUDIT_DIR/$f"
    AUDIT_OK=false
  fi
done
if [ "$AUDIT_OK" = true ]; then
  report PASS "All 8 Audit_Matrix files archived in $AUDIT_DIR"
else
  report FAIL "Some Audit_Matrix files missing from $AUDIT_DIR"
fi

echo ""
echo "=========================================="
echo " SUMMARY: $PASS passed, $FAIL failed"
echo "=========================================="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
