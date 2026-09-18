<?php

namespace Rechnerei\Inquiries\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Rechnerei\Inquiries\Settings;
use Rechnerei\Inquiries\Support\Extractor;
use Symfony\Component\Mime\Email;

/**
 * Listens to Laravel's MessageSent event, which fires after every outgoing
 * email regardless of how it was sent (Statamic form notifications,
 * auto-responders, core app mail, ...).
 *
 * Safety contract: this only ever runs *after* the real email has already
 * been handed to the mail transport, so nothing this listener does — or
 * fails to do — can prevent, delay, or alter that email. Any error is
 * caught and logged rather than allowed to bubble up and disrupt the
 * request/response cycle that triggered the email.
 */
class ForwardMailToRechnerei
{
    public function handle(MessageSent $event): void
    {
        try {
            $this->process($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function process(MessageSent $event): void
    {
        $settings = app(Settings::class);

        if (!$settings->isEnabled()) {
            return;
        }

        $message = $event->message;

        if (!$message instanceof Email) {
            return;
        }

        $subject = (string) $message->getSubject();
        $plain = Extractor::plainText($this->extractBody($message));

        if (Extractor::matchesIgnoreList($subject, $plain, $settings->ignoreRules())) {
            return;
        }

        $replyToEmail = $this->extractReplyToEmail($message);
        $email = $replyToEmail ?: Extractor::extractEmail($subject . "\n" . $plain);
        $phone = Extractor::extractPhone($plain);
        $eventDate = Extractor::extractDate($subject . "\n" . $plain);
        $name = Extractor::extractName($plain) ?: $this->extractReplyToName($message);

        // The Rechnerei API requires at least one of these; skip the call
        // entirely if we couldn't find anything usable — this also
        // naturally filters out most system emails that slipped past the
        // keyword list.
        if (!$email && !$phone && !$name) {
            return;
        }

        $to = collect($message->getTo())
            ->map(fn ($address) => $address->getAddress())
            ->implode(', ');

        $payload = [
            'customer_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'event_date' => $eventDate,
            'source' => 'statamic',
            'form' => [
                'subject' => mb_substr($subject, 0, 255),
                'message' => mb_substr($plain, 0, 4000),
                'to' => mb_substr($to, 0, 255),
                'site_url' => config('app.url'),
            ],
        ];

        $endpoint = $settings->all()['endpoint'];
        $token = $settings->all()['token'];

        // Defer the outbound call until after the response has been sent
        // to whoever triggered this email (e.g. a visitor submitting a
        // form), so a slow/unreachable Rechnerei endpoint never adds
        // latency to their request. Works without a queue worker.
        dispatch(function () use ($endpoint, $token, $payload) {
            try {
                Http::timeout(4)->withToken($token)->acceptJson()->post($endpoint, $payload);
            } catch (\Throwable $e) {
                Log::warning('[Rechnerei Inquiries] Failed to forward inquiry: ' . $e->getMessage());
            }
        })->afterResponse();
    }

    protected function extractBody(Email $message): string
    {
        try {
            if ($html = $message->getHtmlBody()) {
                return (string) $html;
            }
        } catch (\Throwable $e) {
            // fall through to text body
        }

        try {
            return (string) $message->getTextBody();
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function extractReplyToEmail(Email $message): ?string
    {
        try {
            $replyTo = $message->getReplyTo();
            return $replyTo[0]->getAddress() ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function extractReplyToName(Email $message): ?string
    {
        try {
            $replyTo = $message->getReplyTo();
            $name = $replyTo[0]->getName() ?? '';
            return $name !== '' ? $name : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
