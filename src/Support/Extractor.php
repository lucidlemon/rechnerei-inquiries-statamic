<?php

namespace Rechnerei\Inquiries\Support;

/**
 * Best-effort, regex-based extraction of contact details from free-form
 * email text (form notification emails rarely have a stable structure, so
 * this is deliberately forgiving rather than strict).
 *
 * Intentionally framework-agnostic (no Laravel/Statamic calls) so it can be
 * unit tested and reasoned about in isolation.
 */
class Extractor
{
    public static function plainText(string $html): string
    {
        try {
            $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html);
            $text = preg_replace('/<br\s*\/?>|<\/p>|<\/div>|<\/li>/i', "\n", (string) $text);
            $text = strip_tags((string) $text);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", (string) $text);
            return trim((string) $text);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function extractEmail(string $text): ?string
    {
        if (!preg_match_all('/[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+/', $text, $matches)) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            if (preg_match('/^(no-?reply|do-?not-?reply|postmaster|mailer-daemon)@/i', $candidate)) {
                continue;
            }
            return $candidate;
        }

        return $matches[0][0] ?? null;
    }

    public static function extractPhone(string $text): ?string
    {
        $text = preg_replace('/[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', ' ', $text);

        if (!preg_match_all('/(?<![\d.\-\/])(\+?\d[\d\s().\/-]{5,18}\d)(?![\d])/', (string) $text, $matches)) {
            return null;
        }

        foreach ($matches[0] as $candidate) {
            $digits = preg_replace('/\D/', '', $candidate);
            $digitCount = strlen($digits);

            if ($digitCount < 6 || $digitCount > 15) {
                continue;
            }

            if (preg_match('/^\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}$/', trim($candidate))) {
                continue; // looks like a date
            }
            if (preg_match('/^\d+([.,]\d{2})$/', trim($candidate))) {
                continue; // looks like a price
            }

            return (str_starts_with($candidate, '+') ? '+' : '') . $digits;
        }

        return null;
    }

    public static function extractDate(string $text): ?string
    {
        if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m)) {
            if ($date = self::toValidDate($m[1], $m[2], $m[3])) {
                return $date;
            }
        }

        if (preg_match('/\b(\d{1,2})[.\/](\d{1,2})[.\/](\d{4}|\d{2})\b/', $text, $m)) {
            $year = strlen($m[3]) === 2 ? ('20' . $m[3]) : $m[3];
            if ($date = self::toValidDate($year, $m[2], $m[1])) {
                return $date;
            }
        }

        $months = [
            'januar' => 1, 'january' => 1, 'jän' => 1, 'jan' => 1,
            'februar' => 2, 'february' => 2, 'feb' => 2,
            'märz' => 3, 'march' => 3, 'mär' => 3, 'mar' => 3,
            'april' => 4, 'apr' => 4,
            'mai' => 5, 'may' => 5,
            'juni' => 6, 'june' => 6, 'jun' => 6,
            'juli' => 7, 'july' => 7, 'jul' => 7,
            'august' => 8, 'aug' => 8,
            'september' => 9, 'sep' => 9, 'sept' => 9,
            'oktober' => 10, 'october' => 10, 'okt' => 10, 'oct' => 10,
            'november' => 11, 'nov' => 11,
            'dezember' => 12, 'december' => 12, 'dez' => 12, 'dec' => 12,
        ];
        $monthPattern = implode('|', array_map('preg_quote', array_keys($months)));

        if (preg_match('/\b(\d{1,2})\.?\s+(' . $monthPattern . ')\.?\s+(\d{4})\b/iu', $text, $m)) {
            if ($month = $months[strtolower($m[2])] ?? null) {
                if ($date = self::toValidDate($m[3], $month, $m[1])) {
                    return $date;
                }
            }
        }

        if (preg_match('/\b(' . $monthPattern . ')\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+(\d{4})\b/iu', $text, $m)) {
            if ($month = $months[strtolower($m[1])] ?? null) {
                if ($date = self::toValidDate($m[3], $month, $m[2])) {
                    return $date;
                }
            }
        }

        return null;
    }

    public static function extractName(string $text): ?string
    {
        if (preg_match('/^\s*(?:Ihr\s+Name|Full\s*name|Name|Vorname\s*(?:und|&)\s*Nachname)\s*[:\-]\s*(.+)$/mi', $text, $m)) {
            $name = trim($m[1]);
            $name = preg_replace('/\s{2,}/', ' ', $name);
            if ($name !== '' && strlen($name) <= 255) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Each rule is either a plain, case-insensitive substring, or (if
     * wrapped in slashes, e.g. "/foo.*bar/i") a full PCRE pattern.
     *
     * @param string[] $rules
     */
    public static function matchesIgnoreList(string $subject, string $body, array $rules): bool
    {
        $haystack = $subject . "\n" . $body;

        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }

            if (preg_match('/^\/.*\/[a-zA-Z]*$/', $rule)) {
                if (@preg_match($rule, $haystack) === 1) {
                    return true;
                }
                continue;
            }

            if (mb_stripos($haystack, $rule) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function toValidDate($year, $month, $day): ?string
    {
        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;

        if (!checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
