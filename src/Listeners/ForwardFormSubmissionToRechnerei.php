<?php

namespace Rechnerei\Inquiries\Listeners;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rechnerei\Inquiries\Settings;
use Rechnerei\Inquiries\Support\FormFieldMapper;
use Statamic\Events\FormSubmitted;

/**
 * Listens to Statamic's FormSubmitted event, which fires exactly once per
 * form submission — before any notification emails go out — regardless of
 * how many recipients (e.g. a site admin and a visitor confirmation copy)
 * end up being emailed for it. Used instead of ForwardMailToRechnerei when
 * the addon is configured to only listen to specific forms, since it both
 * avoids the one-inquiry-per-recipient duplication and gives access to the
 * submission's actual field values.
 *
 * Safety contract: never return false from handle()/process(). Statamic
 * halts the form submission (and skips saving it / sending its own emails)
 * if any FormSubmitted listener returns exactly false, so this listener
 * must not interfere with the real submission no matter what happens here.
 */
class ForwardFormSubmissionToRechnerei
{
    public function handle(FormSubmitted $event): void
    {
        try {
            $this->process($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function process(FormSubmitted $event): void
    {
        $settings = app(Settings::class);

        if (!$settings->isEnabled() || $settings->mode() !== 'selected_forms') {
            return;
        }

        $submission = $event->submission;
        $form = $submission->form();

        if (!in_array($form->handle(), $settings->selectedForms(), true)) {
            return;
        }

        $mapped = FormFieldMapper::map($submission);

        // Same contract as the Rechnerei API requires elsewhere: skip the
        // call if we couldn't identify anything usable on the form.
        if (!$mapped['email'] && !$mapped['phone'] && !$mapped['name']) {
            return;
        }

        $payload = [
            'customer_name' => $mapped['name'],
            'email' => $mapped['email'],
            'phone' => $mapped['phone'],
            'event_date' => $mapped['event_date'],
            'source' => 'statamic',
            'form' => [
                'handle' => $form->handle(),
                'title' => $form->title(),
                'subject' => mb_substr($form->title(), 0, 255),
                'message' => $mapped['message'] ? mb_substr($mapped['message'], 0, 4000) : null,
                'fields' => $mapped['fields'],
                'site_url' => config('app.url'),
            ],
        ];

        $endpoint = $settings->all()['endpoint'];
        $token = $settings->all()['token'];

        // Deferred until after the response has been sent to the visitor
        // who submitted the form, so a slow/unreachable Rechnerei endpoint
        // never adds latency to their request. Works without a queue worker.
        dispatch(function () use ($endpoint, $token, $payload) {
            try {
                Http::timeout(4)->withToken($token)->acceptJson()->post($endpoint, $payload);
            } catch (\Throwable $e) {
                Log::warning('[Rechnerei Inquiries] Failed to forward form submission: ' . $e->getMessage());
            }
        })->afterResponse();
    }
}
