<?php

namespace App\Support;

class TextNormalizer
{
    public static function fixMojibake(?string $value): string
    {
        if ($value === null || $value === '') {
            return (string) $value;
        }

        $best = $value;
        $bestScore = self::score($best);
        $current = $value;

        // Some legacy records were decoded in different code pages.
        for ($i = 0; $i < 3; $i++) {
            $candidates = self::decodeCandidates($current);
            if ($candidates === []) {
                break;
            }

            $roundBest = $current;
            $roundBestScore = self::score($current);
            foreach ($candidates as $candidate) {
                $score = self::score($candidate);
                if ($score > $roundBestScore) {
                    $roundBest = $candidate;
                    $roundBestScore = $score;
                }
            }

            if ($roundBestScore <= $bestScore) {
                break;
            }

            $best = $roundBest;
            $bestScore = $roundBestScore;
            $current = $roundBest;
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private static function decodeCandidates(string $value): array
    {
        $encodings = ['Windows-1256', 'CP1256', 'Windows-1252', 'ISO-8859-1'];
        $candidates = [];

        foreach ($encodings as $encoding) {
            $decoded = @iconv($encoding, 'UTF-8//IGNORE', $value);
            if (is_string($decoded) && $decoded !== '' && $decoded !== $value) {
                $candidates[] = $decoded;
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function score(string $value): int
    {
        $arabicLetters = preg_match_all('/[\x{0621}-\x{064A}]/u', $value);
        if ($arabicLetters === false) {
            $arabicLetters = 0;
        }

        $score = (int) $arabicLetters;
        $score += 8 * substr_count($value, 'ال');
        $score += 4 * substr_count($value, 'ة');
        $score += 3 * substr_count($value, 'ي');
        $score += 3 * substr_count($value, 'ا');

        // Common mojibake markers.
        $score -= 5 * substr_count($value, 'ط§');
        $score -= 5 * substr_count($value, 'ظ„');
        $score -= 3 * substr_count($value, 'ط');
        $score -= 3 * substr_count($value, 'ظ');
        $score -= 5 * substr_count($value, 'Ã');
        $score -= 5 * substr_count($value, 'Â');
        $score -= 7 * substr_count($value, 'â');

        return $score;
    }
}
