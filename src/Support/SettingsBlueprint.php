<?php

namespace Rechnerei\Inquiries\Support;

use Statamic\Facades\Form;

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
                                    'handle' => 'mode',
                                    'field' => [
                                        'type' => 'button_group',
                                        'display' => 'What should be forwarded?',
                                        'instructions' => '"All outgoing emails" watches every email the site sends (form notifications, auto-responders, core app mail, ...). "Only selected forms" instead listens directly to the submission of the forms you pick below: it fires exactly once per submission — even if the form emails both a visitor confirmation and an admin notification — and sends the actual field values instead of trying to parse them back out of an email.',
                                        'options' => [
                                            'all' => 'All outgoing emails',
                                            'selected_forms' => 'Only selected forms',
                                        ],
                                        'default' => 'all',
                                    ],
                                ],
                                [
                                    'handle' => 'forms',
                                    'field' => [
                                        'type' => 'checkboxes',
                                        'display' => 'Forms to listen to',
                                        'instructions' => 'Only submissions of these forms are sent to Rechnerei.',
                                        'options' => self::formOptions(),
                                        'if' => [
                                            'mode' => 'equals selected_forms',
                                        ],
                                    ],
                                ],
                                [
                                    'handle' => 'ignore_keywords',
                                    'field' => [
                                        'type' => 'textarea',
                                        'display' => 'Ignore emails containing',
                                        'instructions' => 'One phrase per line. Any outgoing email whose subject or body contains one of these phrases is skipped (case-insensitive). Pre-filled with common system notifications (password resets, backups, failed jobs, ...). Advanced: a line wrapped in slashes, e.g. `/invoice #\\d+/i`, is treated as a regular expression. Only applies in "All outgoing emails" mode.',
                                        'rows' => 8,
                                        'validate' => 'nullable|string',
                                        'if' => [
                                            'mode' => 'equals all',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string> form handle => display title, for the "forms" checkboxes field.
     */
    protected static function formOptions(): array
    {
        return Form::all()
            ->mapWithKeys(fn ($form) => [$form->handle() => $form->title()])
            ->all();
    }
}
