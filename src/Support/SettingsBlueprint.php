<?php

namespace Rechnerei\Inquiries\Support;

/**
 * Defines the settings screen as a real Statamic blueprint so it renders
 * through Statamic's own field Vue components (native styling, for free)
 * instead of a hand-rolled Blade form.
 */
class SettingsBlueprint
{
    public static function build(): array
    {
        return [
            'tabs' => [
                'main' => [
                    'display' => 'Settings',
                    'sections' => [
                        [
                            'fields' => [
                                [
                                    'handle' => 'enabled',
                                    'field' => [
                                        'type' => 'toggle',
                                        'display' => 'Enabled',
                                        'instructions' => 'Forward inquiries to Rechnerei.',
                                    ],
                                ],
                                [
                                    'handle' => 'endpoint',
                                    'field' => [
                                        'type' => 'text',
                                        'input_type' => 'url',
                                        'display' => 'Endpoint URL',
                                        'instructions' => 'Find this under Inquiries → Website integration in your Rechnerei account.',
                                        'placeholder' => 'https://your-business.rechnerei.io/api/inquiries',
                                        'validate' => 'nullable|url|max:255',
                                    ],
                                ],
                                [
                                    'handle' => 'token',
                                    'field' => [
                                        'type' => 'text',
                                        'input_type' => 'password',
                                        'display' => 'API Token',
                                        'validate' => 'nullable|string|max:255',
                                    ],
                                ],
                                [
                                    'handle' => 'ignore_keywords',
                                    'field' => [
                                        'type' => 'textarea',
                                        'display' => 'Ignore emails containing',
                                        'instructions' => 'One phrase per line. Any outgoing email whose subject or body contains one of these phrases is skipped (case-insensitive). Pre-filled with common system notifications (password resets, backups, failed jobs, ...). Advanced: a line wrapped in slashes, e.g. `/invoice #\\d+/i`, is treated as a regular expression.',
                                        'rows' => 8,
                                        'validate' => 'nullable|string',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
