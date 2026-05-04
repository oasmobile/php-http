#!/usr/bin/env bash
# Manual Test Script — Task 11: Manual Testing (All Sub-tasks)
#
# Covers:
#   11.1 编程式路由注入端到端验证
#   11.2 Boot 后冻结行为验证
#   11.3 YAML + 编程式路由混合场景验证
#   11.4 无 routing 配置 + 编程式路由场景验证
#   11.5 Closure controller 端到端验证
#   11.6 缓存目录清理后重新 boot 验证
#
# Usage: bash .kiro/specs/hotfix-3.1.1/tests/test-task-11.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../../../.." && pwd)"

echo "═══════════════════════════════════════════════════════════════"
echo "  Task 11: Manual Testing — All Sub-tasks"
echo "═══════════════════════════════════════════════════════════════"
echo ""

cd "${PROJECT_ROOT}"
php "${SCRIPT_DIR}/test-task-11.php"
EXIT_CODE=$?

if [ ${EXIT_CODE} -eq 0 ]; then
    echo "✅ All manual tests passed."
else
    echo "❌ Some manual tests failed (exit code: ${EXIT_CODE})."
fi

exit ${EXIT_CODE}
