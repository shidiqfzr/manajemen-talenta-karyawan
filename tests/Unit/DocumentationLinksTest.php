<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * SUT: the links in the root documents (README.md, AGENTS.md, CONTRIBUTING.md and the four reference documents).
 * A link to a path on one developer's machine, or to a file that does not exist, is a dead link for every contributor
 * and on GitHub. Files under docs/ are out of scope: specs and plans may quote an example path.
 */
class DocumentationLinksTest extends TestCase
{
    private const DOCUMENTS = [
        'README.md', 'AGENTS.md', 'CONTRIBUTING.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md',
    ];

    /**
     * Existing documents only (CONTRIBUTING.md is checked once it exists), as written.
     *
     * @return array<string, string> file name => text
     */
    private function documents(): array
    {
        $texts = [];

        foreach (self::DOCUMENTS as $name) {
            $path = base_path($name);

            if (is_file($path)) {
                $texts[$name] = (string) file_get_contents($path);
            }
        }

        return $texts;
    }

    /**
     * Without fenced code blocks and inline code, where example links may legitimately appear.
     */
    private function withoutCode(string $text): string
    {
        $text = (string) preg_replace('/```.*?```/s', '', $text);

        return (string) preg_replace('/`[^`\n]*`/', '', $text);
    }

    public function test_the_scan_covers_at_least_the_reference_documents(): void
    {
        $this->assertGreaterThanOrEqual(6, count($this->documents()));
    }

    public function test_no_document_contains_a_local_machine_path_even_inside_code(): void
    {
        foreach ($this->documents() as $name => $text) {
            $this->assertStringNotContainsString('file:///', $text, "{$name} contains a file:/// address");
            $this->assertDoesNotMatchRegularExpression('/[a-z]:[\\\\\/]Users[\\\\\/]/i', $text, "{$name} contains a local Windows user path");
        }
    }

    public function test_relative_links_point_to_existing_files(): void
    {
        foreach ($this->documents() as $name => $text) {
            preg_match_all('/\]\(([^)\s]+)\)/', $this->withoutCode($text), $matches);

            foreach ($matches[1] as $target) {
                if (preg_match('/^(https?:|mailto:|#)/i', $target) === 1) {
                    continue;
                }

                $path = explode('#', $target)[0];

                $this->assertFileExists(base_path($path), "{$name} links to a missing file: {$target}");
            }
        }
    }
}
