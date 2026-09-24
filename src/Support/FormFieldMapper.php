<?php

namespace Rechnerei\Inquiries\Support;

use Statamic\Contracts\Forms\Submission;

/**
 * Maps a Statamic form submission's field values onto the shape the
 * Rechnerei API expects, using the field's fieldtype and handle/label as
 * hints (e.g. a field of type "email", or handled/labelled "E-Mail", is
 * used as the customer's email address) instead of regex-parsing an email
 * body. Falls back to Extractor against the flattened field text when
 * nothing matches, so unusual field setups still work.
 */
class FormFieldMapper
{
    public static function map(Submission $submission): array
    {
        $data = $submission->data()->all();
        $types = [];
        $labels = [];

        foreach ($submission->form()->blueprint()->fields()->all() as $handle => $field) {
            $types[$handle] = $field->type();
            $labels[$handle] = (string) $field->display();
        }

        $name = self::firstMatch($data, $types, $labels, [], '/^(name|full[_ ]?name|your[_ ]?name|vollst[äa]ndiger[_ ]?name|vorname[_ ]?(und|&)[_ ]?nachname)$/i');
        $email = self::firstMatch($data, $types, $labels, ['email'], '/e-?mail/i');
        $phone = self::firstMatch($data, $types, $labels, [], '/\b(phone|telefon|mobil(?:nummer)?|handy|tel)\b/i');
        $message = self::firstMatch($data, $types, $labels, ['textarea'], '/message|nachricht|anfrage|comment|kommentar|notiz/i');
        $eventDate = self::firstMatch($data, $types, $labels, ['date'], '/date|datum|termin/i');

        $fields = self::flatten($data);
        $text = implode("\n", $fields);

        return [
            'name' => self::stringify($name) ?: Extractor::extractName($text),
            'email' => self::stringify($email) ?: Extractor::extractEmail($text),
            'phone' => self::stringify($phone) ?: Extractor::extractPhone($text),
            'event_date' => self::stringify($eventDate) ?: Extractor::extractDate($text),
            'message' => self::stringify($message),
            'fields' => $fields,
        ];
    }

    /**
     * Looks for the first non-empty field whose fieldtype is in
     * $wantedTypes, then falls back to matching the field's handle or
     * display label against $pattern.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $types
     * @param array<string, string> $labels
     * @param string[] $wantedTypes
     */
    protected static function firstMatch(array $data, array $types, array $labels, array $wantedTypes, string $pattern): mixed
    {
        if ($wantedTypes !== []) {
            foreach ($data as $handle => $value) {
                if (!self::isEmpty($value) && in_array($types[$handle] ?? null, $wantedTypes, true)) {
                    return $value;
                }
            }
        }

        foreach ($data as $handle => $value) {
            if (!self::isEmpty($value) && preg_match($pattern, (string) $handle)) {
                return $value;
            }
        }

        foreach ($data as $handle => $value) {
            if (!self::isEmpty($value) && isset($labels[$handle]) && preg_match($pattern, $labels[$handle])) {
                return $value;
            }
        }

        return null;
    }

    protected static function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    protected static function stringify(mixed $value): ?string
    {
        if (self::isEmpty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => self::stringify($v) ?? '', $value));
        }

        return trim((string) $value);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected static function flatten(array $data): array
    {
        $out = [];

        foreach ($data as $handle => $value) {
            $out[$handle] = self::stringify($value) ?? '';
        }

        return $out;
    }
}
