#!/usr/bin/env bash
# Manual Test Script — Task 10: Programmatic Route Injection E2E Verification
# Usage: bash .kiro/specs/hotfix-3.1.1/tests/test-task-10.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"

echo "═══════════════════════════════════════════════════════════════"
echo "  Task 10: Manual Testing — Programmatic Route Injection"
echo "═══════════════════════════════════════════════════════════════"
echo ""

cd "${PROJECT_ROOT}"
php "${SCRIPT_DIR}/test-task-10.php"
EXIT_CODE=$?

if [ ${EXIT_CODE} -eq 0 ]; then
    echo "✅ All manual tests passed."
else
    echo "❌ Some manual tests failed (exit code: ${EXIT_CODE})."
fi

exit ${EXIT_CODE}
