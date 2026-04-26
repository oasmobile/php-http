<?php

namespace Oasis\Mlib\Http\Test;

use PHPUnit\Framework\TestCase;

/**
 * Document Validation Tests for Migration Guide (migration-v3.md).
 *
 * Validates structural correctness properties of the migration guide document:
 *   - Property 1: TOC anchor completeness
 *   - Property 2: Breaking change coverage completeness
 *   - Property 3: Entry format completeness
 *   - Property 4: Bootstrap_Config key coverage completeness
 *
 * These tests are expected to FAIL (RED) until migration-v3.md is created.
 *
 * Feature: php85-migration-guide
 */
class MigrationGuideValidationTest extends TestCase
{
    private const MIGRATION_GUIDE_PATH = __DIR__ . '/../docs/manual/migration-v3.md';
    private const BREAKING_CHANGE_RECORD_PATH = __DIR__ . '/../docs/changes/unreleased/php85-upgrade.md';
    private const ARCHITECTURE_PATH = __DIR__ . '/../docs/state/architecture.md';

    // ========================================================================
    // Property 1: TOC 锚点完整性
    // Feature: php85-migration-guide, Property 1: TOC anchor completeness
    //
    // For any anchor link in the Migration_Guide TOC section, there SHALL
    // exist a corresponding heading in the document body whose generated
    // anchor matches the link target.
    //
    // Ref: Requirement 1, AC 2; Design Correctness Property 1
    // ========================================================================

    public function testTocAnchorsResolveToHeadings(): void
    {
        $content = $this->loadMigrationGuide();

        // Extract TOC section: from first line containing a markdown link to
        // the first heading that is NOT part of the TOC (i.e. a ## or deeper
        // heading after a blank line following the TOC block).
        $tocAnchors = $this->extractTocAnchors($content);
        $this->assertNotEmpty($tocAnchors, 'Migration guide TOC should contain at least one anchor link');

        $headingAnchors = $this->extractHeadingAnchors($content);

        $missing = [];
        foreach ($tocAnchors as $anchor) {
            if (!in_array($anchor, $headingAnchors, true)) {
                $missing[] = $anchor;
            }
        }

        $this->assertEmpty(
            $missing,
            "TOC contains anchors that do not resolve to any heading:\n  - " . implode("\n  - ", $missing)
        );
    }

    // ========================================================================
    // Property 2: Breaking Change 覆盖完整性
    // Feature: php85-migration-guide, Property 2: Breaking change coverage
    //
    // For any breaking change item recorded in Breaking_Change_Record
    // (docs/changes/unreleased/php85-upgrade.md), the Migration_Guide SHALL
    // contain a section or entry that addresses that item.
    //
    // Ref: Requirement 1, AC 3; Design Correctness Property 2
    // ========================================================================

    public function testBreakingChangeCoverage(): void
    {
        $content = $this->loadMigrationGuide();
        $breakingChangeItems = $this->extractBreakingChangeItems();

        $this->assertNotEmpty(
            $breakingChangeItems,
            'Breaking change record should contain at least one item'
        );

        $uncovered = [];
        foreach ($breakingChangeItems as $item) {
            if (!$this->isItemCoveredInGuide($item, $content)) {
                $uncovered[] = $item;
            }
        }

        $this->assertEmpty(
            $uncovered,
            "The following breaking change items are NOT covered in migration-v3.md:\n  - "
            . implode("\n  - ", $uncovered)
        );
    }

    // ========================================================================
    // Property 3: 条目格式完整性
    // Feature: php85-migration-guide, Property 3: Entry format completeness
    //
    // For any breaking change entry in the Migration_Guide, the entry SHALL
    // contain all three required elements:
    //   (1) a Severity_Level marker (🔴/🟡/🟢)
    //   (2) a before/after code example pair
    //   (3) an action description
    //
    // Ref: Requirement 2, AC 1/2/3; Design Correctness Property 3
    // ========================================================================

    public function testEntryFormatCompleteness(): void
    {
        $content = $this->loadMigrationGuide();
        $entries = $this->extractBreakingChangeEntries($content);

        $this->assertNotEmpty($entries, 'Migration guide should contain at least one breaking change entry');

        $violations = [];
        foreach ($entries as $index => $entry) {
            $title = $entry['title'];
            $body  = $entry['body'];

            // (1) Severity marker
            if (!preg_match('/[🔴🟡🟢]/u', $title)) {
                $violations[] = "Entry \"{$title}\": missing severity marker (🔴/🟡/🟢)";
            }

            // (2) Before/After code blocks — expect at least two fenced code blocks,
            //     or explicit **Before** / **After** labels
            $hasBeforeAfter = (
                preg_match('/\*\*Before\*\*/i', $body) && preg_match('/\*\*After\*\*/i', $body)
            );
            if (!$hasBeforeAfter) {
                $violations[] = "Entry \"{$title}\": missing Before/After code example pair";
            }

            // (3) Action description — look for **操作** or **Action** label
            $hasAction = (bool)preg_match('/\*\*(操作|Action)\*\*/iu', $body);
            if (!$hasAction) {
                $violations[] = "Entry \"{$title}\": missing action description (操作/Action)";
            }
        }

        $this->assertEmpty(
            $violations,
            "Breaking change entry format violations:\n  - " . implode("\n  - ", $violations)
        );
    }

    // ========================================================================
    // Property 4: Bootstrap_Config Key 覆盖完整性
    // Feature: php85-migration-guide, Property 4: Bootstrap_Config key coverage
    //
    // For any Bootstrap_Config key defined in docs/state/architecture.md,
    // the Migration_Guide's Bootstrap_Config key reference table SHALL
    // contain an entry for that key.
    //
    // Ref: Requirement 9, AC 2; Design Correctness Property 4
    // ========================================================================

    public function testBootstrapConfigKeyCoverage(): void
    {
        $content = $this->loadMigrationGuide();
        $architectureKeys = $this->extractBootstrapConfigKeysFromArchitecture();

        $this->assertNotEmpty(
            $architectureKeys,
            'Architecture doc should define at least one Bootstrap Config key'
        );

        $uncovered = [];
        foreach ($architectureKeys as $key) {
            // Look for the key in a markdown table row: | key | ... |
            // or as backtick-quoted `key` in the Bootstrap Config section
            $escaped = preg_quote($key, '/');
            if (!preg_match('/[`|]\s*' . $escaped . '\s*[`|]/u', $content)) {
                $uncovered[] = $key;
            }
        }

        $this->assertEmpty(
            $uncovered,
            "The following Bootstrap_Config keys from architecture.md are NOT in the migration guide reference table:\n  - "
            . implode("\n  - ", $uncovered)
        );
    }

    // ========================================================================
    // Helper methods
    // ========================================================================

    /**
     * Load migration guide content. Fails the test if the file does not exist.
     */
    private function loadMigrationGuide(): string
    {
        $this->assertFileExists(
            self::MIGRATION_GUIDE_PATH,
            'Migration guide file does not exist: docs/manual/migration-v3.md'
        );

        $content = file_get_contents(self::MIGRATION_GUIDE_PATH);
        $this->assertNotEmpty($content, 'Migration guide file is empty');

        return $content;
    }

    /**
     * Extract anchor targets from TOC links: [text](#anchor) → anchor
     *
     * The TOC is identified as the block of consecutive lines containing
     * markdown links with fragment anchors, starting from the top of the file
     * (after the title heading).
     */
    private function extractTocAnchors(string $content): array
    {
        $anchors = [];
        // Match all [text](#anchor) patterns in the document's TOC area.
        // TOC links use the pattern [visible text](#anchor-slug)
        if (preg_match_all('/\[([^\]]+)\]\(#([^)]+)\)/', $content, $matches)) {
            $anchors = $matches[2];
        }

        return array_unique($anchors);
    }

    /**
     * Extract heading anchors from the document body.
     *
     * Converts each markdown heading to its GitHub-style anchor:
     *   - lowercase
     *   - spaces → hyphens
     *   - remove non-alphanumeric characters (except hyphens and CJK)
     *   - collapse consecutive hyphens
     */
    private function extractHeadingAnchors(string $content): array
    {
        $anchors = [];
        if (preg_match_all('/^(#{1,6})\s+(.+)$/mu', $content, $matches)) {
            foreach ($matches[2] as $headingText) {
                $anchors[] = $this->headingToAnchor($headingText);
            }
        }

        return $anchors;
    }

    /**
     * Convert a heading text to a GitHub-compatible anchor slug.
     */
    private function headingToAnchor(string $heading): string
    {
        // Remove severity emoji markers and other non-text decorations
        $slug = preg_replace('/[🔴🟡🟢]/u', '', $heading);
        // Lowercase
        $slug = mb_strtolower(trim($slug), 'UTF-8');
        // Replace spaces and underscores with hyphens
        $slug = preg_replace('/[\s_]+/', '-', $slug);
        // Remove characters that are not alphanumeric, hyphens, or CJK
        $slug = preg_replace('/[^\p{L}\p{N}\-]/u', '', $slug);
        // Collapse consecutive hyphens
        $slug = preg_replace('/-{2,}/', '-', $slug);
        // Trim leading/trailing hyphens
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Extract breaking change items from the Breaking_Change_Record.
     *
     * Parses docs/changes/unreleased/php85-upgrade.md and extracts each
     * bullet-point item under ## Changed and ## Removed sections.
     * Returns an array of keyword strings that should appear in the migration guide.
     */
    private function extractBreakingChangeItems(): array
    {
        $this->assertFileExists(
            self::BREAKING_CHANGE_RECORD_PATH,
            'Breaking change record file does not exist: docs/changes/unreleased/php85-upgrade.md'
        );

        $content = file_get_contents(self::BREAKING_CHANGE_RECORD_PATH);
        $items   = [];

        // Extract items from ## Changed and ## Removed sections.
        // Each top-level bullet (- ...) under these sections is a breaking change item.
        // We extract the key identifier from each bullet for coverage checking.
        $lines       = explode("\n", $content);
        $inSection   = false;
        $sectionName = '';

        foreach ($lines as $line) {
            // Detect section headers
            if (preg_match('/^##\s+(Changed|Removed|Added)/', $line, $m)) {
                $sectionName = $m[1];
                $inSection   = in_array($sectionName, ['Changed', 'Removed'], true);
                continue;
            }
            // Stop at next ## heading that is not Changed/Removed
            if (preg_match('/^##\s+/', $line) && $inSection) {
                $inSection = false;
                continue;
            }

            if (!$inSection) {
                continue;
            }

            // Top-level bullet items (- ...)
            if (preg_match('/^- (.+)$/', $line, $m)) {
                $item = trim($m[1]);
                if (!empty($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Check if a breaking change item is covered in the migration guide.
     *
     * Uses keyword extraction: pulls key identifiers (class names, package
     * names, version constraints) from the item and checks if they appear
     * in the guide content.
     */
    private function isItemCoveredInGuide(string $item, string $guideContent): bool
    {
        $keywords = $this->extractKeywords($item);

        if (empty($keywords)) {
            // If no keywords could be extracted, check for substring presence
            // of the first significant phrase (first 30 chars)
            $phrase = mb_substr($item, 0, 40, 'UTF-8');

            return str_contains($guideContent, $phrase);
        }

        // At least one keyword must appear in the guide
        foreach ($keywords as $keyword) {
            if (stripos($guideContent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract searchable keywords from a breaking change item.
     *
     * Looks for:
     *   - Backtick-quoted identifiers: `SilexKernel`, `^4.0`, `silex/silex`
     *   - Package names: word/word patterns
     *   - Class/interface names: PascalCase words
     */
    private function extractKeywords(string $item): array
    {
        $keywords = [];

        // Backtick-quoted identifiers
        if (preg_match_all('/`([^`]+)`/', $item, $matches)) {
            foreach ($matches[1] as $quoted) {
                $keywords[] = $quoted;
            }
        }

        // Package names (vendor/package)
        if (preg_match_all('/\b([a-z][a-z0-9-]*\/[a-z][a-z0-9-]*)\b/', $item, $matches)) {
            foreach ($matches[1] as $pkg) {
                $keywords[] = $pkg;
            }
        }

        // PascalCase class/interface names (at least 2 uppercase transitions)
        if (preg_match_all('/\b([A-Z][a-zA-Z]*(?:[A-Z][a-zA-Z]*)+)\b/', $item, $matches)) {
            foreach ($matches[1] as $className) {
                $keywords[] = $className;
            }
        }

        return array_unique($keywords);
    }

    /**
     * Extract breaking change entries from the migration guide.
     *
     * Each entry is a ### heading (with severity marker) followed by its body
     * content until the next ### heading or end of section.
     *
     * Returns array of ['title' => string, 'body' => string].
     */
    private function extractBreakingChangeEntries(string $content): array
    {
        $entries = [];
        // Split by ### headings that contain severity markers
        $pattern = '/^###\s+(.+)$/mu';

        if (!preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $entries;
        }

        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $title      = trim($matches[1][$i][0]);
            $startPos   = $matches[0][$i][1] + strlen($matches[0][$i][0]);

            // Only process entries with severity markers
            if (!preg_match('/[🔴🟡🟢]/u', $title)) {
                continue;
            }

            // Body extends to next ### heading or end of content
            if ($i + 1 < $count) {
                $endPos = $matches[0][$i + 1][1];
            } else {
                $endPos = strlen($content);
            }

            $body = substr($content, $startPos, $endPos - $startPos);

            $entries[] = [
                'title' => $title,
                'body'  => $body,
            ];
        }

        return $entries;
    }

    /**
     * Extract Bootstrap_Config keys from architecture.md.
     *
     * Parses the "Bootstrap Config 结构" section's markdown table to extract
     * all key names from the first column.
     */
    private function extractBootstrapConfigKeysFromArchitecture(): array
    {
        $this->assertFileExists(
            self::ARCHITECTURE_PATH,
            'Architecture doc does not exist: docs/state/architecture.md'
        );

        $content = file_get_contents(self::ARCHITECTURE_PATH);
        $keys    = [];

        // Find the Bootstrap Config table: lines matching | Key | ... |
        // Skip the header separator line (| --- | --- |)
        $lines       = explode("\n", $content);
        $inTable     = false;
        $headerFound = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Detect table start: header row with "Key" column
            if (!$inTable && preg_match('/^\|\s*Key\s*\|/i', $trimmed)) {
                $inTable = true;
                continue;
            }

            if (!$inTable) {
                continue;
            }

            // Skip separator row
            if (preg_match('/^\|[\s\-:]+\|/', $trimmed)) {
                $headerFound = true;
                continue;
            }

            // End of table: non-table line
            if (!str_starts_with($trimmed, '|')) {
                if ($headerFound) {
                    break;
                }
                continue;
            }

            // Extract first column value: | `key_name` | ... |
            if (preg_match('/^\|\s*`([^`]+)`\s*\|/', $trimmed, $m)) {
                $keys[] = $m[1];
            }
        }

        return $keys;
    }
}
