<?php

namespace Goldnead\Assessments\Scoring;

/**
 * The result levels of one assessment: ordered ranges of points, each with a
 * key, a label, a text, and optionally an address to send the visitor to.
 *
 * Stored as JSON on the assessment (`scoring`). This class is the one reading
 * of that shape, for the editor's validation and for the assignment at submit.
 */
class Levels
{
    /** @param  list<array{key: string, label: string, min: int, max: int, text: string|null, redirect: string|null}>  $levels */
    final public function __construct(protected array $levels) {}

    /** @param  iterable<mixed>  $raw */
    public static function fromArray(iterable $raw): static
    {
        $levels = [];

        foreach ($raw as $level) {
            if (! is_array($level)) {
                continue;
            }

            $levels[] = [
                'key' => trim((string) ($level['key'] ?? '')),
                'label' => trim((string) ($level['label'] ?? '')),
                'min' => (int) ($level['min'] ?? 0),
                'max' => (int) ($level['max'] ?? 0),
                'text' => isset($level['text']) && $level['text'] !== '' ? (string) $level['text'] : null,
                'redirect' => isset($level['redirect']) && $level['redirect'] !== '' ? (string) $level['redirect'] : null,
            ];
        }

        usort($levels, fn (array $a, array $b) => $a['min'] <=> $b['min']);

        return new static($levels);
    }

    /** @return list<array{key: string, label: string, min: int, max: int, text: string|null, redirect: string|null}> */
    public function all(): array
    {
        return $this->levels;
    }

    public function isEmpty(): bool
    {
        return $this->levels === [];
    }

    /** @return array{key: string, label: string, min: int, max: int, text: string|null, redirect: string|null}|null */
    public function byKey(string $key): ?array
    {
        foreach ($this->levels as $level) {
            if ($level['key'] === $key) {
                return $level;
            }
        }

        return null;
    }

    /**
     * The level a score falls into.
     *
     * Boundaries are inclusive on both ends. A score below every level gets
     * the lowest, above every level the highest: the editor refuses gaps and
     * overlaps, but a question edited after publishing can still move the
     * achievable range, and a visitor who just finished must not see nothing.
     *
     * @return array{key: string, label: string, min: int, max: int, text: string|null, redirect: string|null}|null
     */
    public function forScore(int $score): ?array
    {
        if ($this->levels === []) {
            return null;
        }

        foreach ($this->levels as $level) {
            if ($score >= $level['min'] && $score <= $level['max']) {
                return $level;
            }
        }

        $first = $this->levels[0];

        if ($score < $first['min']) {
            return $first;
        }

        return $this->levels[count($this->levels) - 1];
    }

    /**
     * What is wrong with these levels, as message keys, or nothing.
     *
     * Checked in order of how confusing each would be on the result page:
     * a level without a key or label cannot be named, one whose `min` is above
     * its `max` can never be hit, two levels sharing a score would make the
     * result depend on sort order, and a hole between two levels puts a
     * visitor with exactly that score into a level they did not earn.
     *
     * When the achievable range is known (`$range`) the levels must also
     * cover all of it. That is checked only where the caller asks for it — an
     * unpublished draft may be half-built.
     *
     * @param  array{0: int, 1: int}|null  $range
     * @return list<string>
     */
    public function problems(?array $range = null): array
    {
        $problems = [];
        $keys = [];

        foreach ($this->levels as $index => $level) {
            if ($level['key'] === '' || $level['label'] === '') {
                $problems[] = 'level_incomplete';
            }

            if (! preg_match('/^[a-z0-9_-]+$/', $level['key'])) {
                $problems[] = 'level_key_format';
            }

            if (isset($keys[$level['key']])) {
                $problems[] = 'level_key_duplicate';
            }

            $keys[$level['key']] = true;

            if ($level['min'] > $level['max']) {
                $problems[] = 'level_inverted';
            }

            if ($index === 0) {
                continue;
            }

            $previous = $this->levels[$index - 1];

            if ($level['min'] <= $previous['max']) {
                $problems[] = 'level_overlap';
            } elseif ($level['min'] !== $previous['max'] + 1) {
                $problems[] = 'level_gap';
            }
        }

        if ($range !== null && $this->levels !== []) {
            $first = $this->levels[0];
            $last = $this->levels[count($this->levels) - 1];

            if ($first['min'] > $range[0] || $last['max'] < $range[1]) {
                $problems[] = 'level_range';
            }
        }

        return array_values(array_unique($problems));
    }
}
