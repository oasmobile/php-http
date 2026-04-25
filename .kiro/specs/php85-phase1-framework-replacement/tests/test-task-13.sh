#!/usr/bin/env bash
# Manual test runner for Task 13 — PHP 8.5 Phase 1 Framework Replacement.
#
# Executes the PHP test script that verifies all 8 MicroKernel subsystem scenarios.
# Exit code: 0 = all passed, 1 = at least one failure.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR/../../../.."

echo "Running manual tests from: $SCRIPT_DIR/test-task-13.php"
echo "Project root: $PROJECT_ROOT"
echo ""

php "$SCRIPT_DIR/test-task-13.php"
