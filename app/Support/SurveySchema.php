<?php

namespace App\Support;

/**
 * Forms made with the form builder (/form-builder): cleaning the form design sent by the browser, and checking
 * the answers people send. The design comes from the owner's browser, so every value is checked and trimmed here.
 *
 * Schema { title, intro, button, thanks, color, fields: [ { id, type, label, help, required, options[], placeholder } ] }
 */
class SurveySchema
{
    public const TYPES = [
        'text' => 'Short answer', 'textarea' => 'Paragraph', 'email' => 'Email', 'phone' => 'Phone', 'number' => 'Number',
        'date' => 'Date', 'select' => 'Dropdown', 'radio' => 'Multiple choice', 'checkbox' => 'Checkboxes', 'rating' => 'Rating (1–5)',
        'heading' => 'Section title', // no answer: a title and text between questions
    ];

    public const MAX_FIELDS = 30;

    public const MAX_OPTIONS = 20;

    private const CHOICE = ['select', 'radio', 'checkbox'];

    /**
     * Forms must not be used to collect passwords or card details (they are shown on owlvix.com). Labels are checked
     * against these words before a form is saved.
     */
    private const BLOCKED_WORDS = ['password', 'passcode', 'passwort', 'contraseña', 'card number', 'credit card', 'debit card', 'cvv', 'cvc',
        'security code', 'expiry date', 'expiration date', 'pin code', 'pin number', 'social security', 'ssn', 'seed phrase', 'recovery phrase',
        'private key', 'bank login', 'online banking', 'one-time code', 'verification code', '2fa', 'otp'];

    /** A clean schema, or an error message for the owner. */
    public static function clean(mixed $in): array|string
    {
        if (! is_array($in)) {
            return 'That form could not be read.';
        }
        $s = [
            'title' => self::str($in['title'] ?? '', 120) ?: 'My form',
            'intro' => self::str($in['intro'] ?? '', 1000),
            'button' => self::str($in['button'] ?? '', 40) ?: 'Send',
            'thanks' => self::str($in['thanks'] ?? '', 300) ?: 'Thanks! Your answers have been sent.',
            'color' => is_string($in['color'] ?? null) && preg_match('/^#[0-9a-f]{6}$/i', $in['color']) ? strtolower($in['color']) : '#16a34a',
            'fields' => [],
        ];
        $seen = [];
        foreach (array_slice(is_array($in['fields'] ?? null) ? $in['fields'] : [], 0, self::MAX_FIELDS) as $f) {
            if (! is_array($f) || ! isset(self::TYPES[$f['type'] ?? ''])) {
                continue;
            }
            $id = is_string($f['id'] ?? null) && preg_match('/^[a-z0-9]{3,12}$/', $f['id']) ? $f['id'] : substr(md5(uniqid('', true)), 0, 8);
            while (isset($seen[$id])) {
                $id .= 'x';
            }
            $seen[$id] = true;
            $field = [
                'id' => $id, 'type' => $f['type'],
                'label' => self::str($f['label'] ?? '', 200) ?: 'Question',
                'help' => self::str($f['help'] ?? '', 300),
                'required' => $f['type'] !== 'heading' && ! empty($f['required']),
                'placeholder' => self::str($f['placeholder'] ?? '', 100),
            ];
            if (in_array($f['type'], self::CHOICE, true)) {
                $opts = [];
                foreach (array_slice(is_array($f['options'] ?? null) ? $f['options'] : [], 0, self::MAX_OPTIONS) as $o) {
                    $o = self::str($o, 100);
                    if ($o !== '' && ! in_array($o, $opts, true)) {
                        $opts[] = $o;
                    }
                }
                if (! $opts) {
                    return '“'.$field['label'].'” needs at least one choice.';
                }
                $field['options'] = $opts;
            }
            $s['fields'][] = $field;
        }
        if (! array_filter($s['fields'], fn ($f) => $f['type'] !== 'heading')) {
            return 'Add at least one question.';
        }
        $text = mb_strtolower(implode(' ', [$s['title'], $s['intro'], $s['button'], $s['thanks'],
            ...array_map(fn ($f) => $f['label'].' '.$f['help'].' '.$f['placeholder'].' '.implode(' ', $f['options'] ?? []), $s['fields'])]));
        foreach (self::BLOCKED_WORDS as $word) {
            if (preg_match('/\b'.preg_quote($word, '/').'\b/u', $text)) {
                return 'For everyone’s safety, forms can’t ask for passwords, codes or card details (found “'.$word.'”).';
            }
        }

        return $s;
    }

    /**
     * The answers as [field id => value], or a list of errors for the person filling in the form.
     *
     * @return array{0: array<string, string|array>, 1: string[]}
     */
    public static function answers(array $schema, array $input): array
    {
        $out = [];
        $errors = [];
        foreach ($schema['fields'] ?? [] as $f) {
            if ($f['type'] === 'heading') {
                continue;
            }
            $raw = $input[$f['id']] ?? null;
            $label = $f['label'];
            if ($f['type'] === 'checkbox') {
                $picked = array_values(array_intersect($f['options'], array_filter(is_array($raw) ? $raw : [], 'is_string')));
                if ($f['required'] && ! $picked) {
                    $errors[] = 'Please choose at least one answer for “'.$label.'”.';
                }
                if ($picked) {
                    $out[$f['id']] = $picked;
                }

                continue;
            }
            $v = is_string($raw) ? trim($raw) : '';
            if ($v === '') {
                if ($f['required']) {
                    $errors[] = 'Please answer “'.$label.'”.';
                }

                continue;
            }
            $max = $f['type'] === 'textarea' ? 5000 : 500;
            if (mb_strlen($v) > $max) {
                $errors[] = 'Please keep your answer to “'.$label.'” under '.$max.' characters.';

                continue;
            }
            $bad = match ($f['type']) {
                'email' => ! filter_var($v, FILTER_VALIDATE_EMAIL),
                'phone' => ! preg_match('/^[\d\s()+.\-\/]{5,30}$/', $v),
                'number' => ! is_numeric(str_replace(',', '.', $v)),
                'date' => ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) || ! checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)),
                'select', 'radio' => ! in_array($v, $f['options'], true),
                'rating' => ! in_array($v, ['1', '2', '3', '4', '5'], true),
                default => false,
            };
            if ($bad) {
                $errors[] = 'Please check your answer to “'.$label.'”.';

                continue;
            }
            $out[$f['id']] = $v;
        }

        return [$out, $errors];
    }

    /**
     * The new schema, remembering questions that were removed from the old one (id and label only), so answers
     * people already gave to them still show in the responses table and CSV.
     */
    public static function keepRemoved(array $old, array $new): array
    {
        $now = array_column($new['fields'], 'id');
        $removed = [];
        foreach ([...($old['removed'] ?? []), ...($old['fields'] ?? [])] as $f) {
            if (($f['type'] ?? '') !== 'heading' && isset($f['id'], $f['label']) && ! in_array($f['id'], $now, true)) {
                $removed[$f['id']] = ['id' => $f['id'], 'label' => $f['label']];
            }
        }
        $new['removed'] = array_slice(array_values($removed), -50);

        return $new;
    }

    /** Columns for the responses table and CSV: current questions, then removed ones. */
    public static function columns(array $schema): array
    {
        $current = array_values(array_filter($schema['fields'] ?? [], fn ($f) => $f['type'] !== 'heading'));
        $removed = array_map(fn ($f) => $f + ['label' => '', 'type' => 'text'], $schema['removed'] ?? []);

        return [...$current, ...array_map(fn ($f) => ['label' => $f['label'].' (removed)'] + $f, $removed)];
    }

    /** The answer as text (for emails and CSV). */
    public static function text(mixed $answer): string
    {
        return is_array($answer) ? implode(', ', $answer) : (string) $answer;
    }

    /** The first email answer, to reply to. */
    public static function replyTo(array $schema, array $answers): ?string
    {
        foreach ($schema['fields'] ?? [] as $f) {
            if ($f['type'] === 'email' && filled($answers[$f['id']] ?? null)) {
                return $answers[$f['id']];
            }
        }

        return null;
    }

    private static function str(mixed $v, int $max): string
    {
        return is_string($v) ? trim(mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '', 0, $max)) : '';
    }
}
