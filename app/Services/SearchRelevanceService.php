<?php

namespace App\Services;

use Illuminate\Support\Str;

class SearchRelevanceService
{
    /**
     * Rank what the viewer typed against a catalogue item. Title matches are
     * deliberately worth much more than incidental description/genre/VJ hits.
     */
    public function score(
        string $query,
        string $title,
        ?string $description = null,
        array $genres = [],
        ?string $vjName = null,
        ?string $alternativeTitle = null,
    ): int {
        $needle = $this->normalize($query);
        $haystack = $this->normalize($title);

        if ($needle === '' || $haystack === '') {
            return 0;
        }

        $score = 0;

        if ($haystack === $needle) {
            $score = 100_000;
        } elseif (str_starts_with($haystack, $needle.' ')) {
            $score = 92_000;
        } elseif ($this->containsWholePhrase($haystack, $needle)) {
            $score = 86_000;
        } elseif (str_contains($haystack, $needle)) {
            $score = 80_000;
        } elseif (str_contains(str_replace(' ', '', $haystack), str_replace(' ', '', $needle))) {
            $score = 76_000;
        }

        $queryTokens = $this->tokens($needle);
        $titleTokens = $this->tokens($haystack);
        if ($queryTokens !== []) {
            $matchedAll = true;
            $tokenScore = 0;
            foreach ($queryTokens as $queryToken) {
                if (in_array($queryToken, $titleTokens, true)) {
                    $tokenScore += 8_000;
                    continue;
                }

                if ($this->anyTokenStartsWith($titleTokens, $queryToken)) {
                    $tokenScore += 6_000;
                    continue;
                }

                if ($this->anyTokenContains($titleTokens, $queryToken)) {
                    $tokenScore += 3_000;
                    continue;
                }

                if ($this->anyTokenIsCloseTo($titleTokens, $queryToken)) {
                    $tokenScore += 2_000;
                    continue;
                }

                $matchedAll = false;
            }

            if ($matchedAll) {
                $score = max($score, 68_000 + $tokenScore);
            }
        }

        $alternative = $this->normalize($alternativeTitle ?? '');
        if ($alternative !== '') {
            if ($alternative === $needle) {
                $score = max($score, 66_000);
            } elseif (str_starts_with($alternative, $needle.' ')) {
                $score = max($score, 63_000);
            } elseif ($this->containsWholePhrase($alternative, $needle) || str_contains($alternative, $needle)) {
                $score = max($score, 58_000);
            }
        }

        $descriptionText = $this->normalize($description ?? '');
        if ($descriptionText !== '' && str_contains($descriptionText, $needle)) {
            $score += 2_000;
        }

        $genreText = $this->normalize(implode(' ', $genres));
        if ($genreText !== '' && str_contains($genreText, $needle)) {
            $score += 4_000;
        }

        $vjText = $this->normalize($vjName ?? '');
        if ($vjText !== '' && str_contains($vjText, $needle)) {
            $score += 3_000;
        }

        // Prefer the tighter title when the semantic match tier is identical.
        return $score + max(0, 500 - abs(mb_strlen($haystack) - mb_strlen($needle)));
    }

    private function normalize(string $value): string
    {
        $ascii = Str::ascii(Str::lower(trim($value)));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? '');
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        return array_values(array_filter(explode(' ', $value), static fn (string $token) => $token !== ''));
    }

    private function containsWholePhrase(string $haystack, string $needle): bool
    {
        return str_contains(' '.$haystack.' ', ' '.$needle.' ');
    }

    /** @param list<string> $tokens */
    private function anyTokenStartsWith(array $tokens, string $needle): bool
    {
        foreach ($tokens as $token) {
            if (str_starts_with($token, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $tokens */
    private function anyTokenContains(array $tokens, string $needle): bool
    {
        foreach ($tokens as $token) {
            if (str_contains($token, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $tokens */
    private function anyTokenIsCloseTo(array $tokens, string $needle): bool
    {
        $length = strlen($needle);
        if ($length < 4) {
            return false;
        }

        $maximumDistance = $length >= 8 ? 2 : 1;
        foreach ($tokens as $token) {
            if (abs(strlen($token) - $length) <= $maximumDistance
                && levenshtein($token, $needle) <= $maximumDistance) {
                return true;
            }
        }

        return false;
    }
}
