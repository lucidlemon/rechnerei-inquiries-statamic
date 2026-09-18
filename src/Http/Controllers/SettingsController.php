<?php

namespace Rechnerei\Inquiries\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Rechnerei\Inquiries\Settings;

class SettingsController extends Controller
{
    public function edit(Settings $settings)
    {
        return view('rechnerei-inquiries::settings', [
            'settings' => $settings->all(),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'endpoint' => ['nullable', 'url', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
            'ignore_keywords' => ['nullable', 'string'],
        ]);

        $settings->save([
            'enabled' => (bool) ($data['enabled'] ?? false),
            'endpoint' => $data['endpoint'] ?? '',
            'token' => $data['token'] ?? '',
            'ignore_keywords' => $data['ignore_keywords'] ?? '',
        ]);

        return back()->with('success', __('Saved.'));
    }

    public function test(Settings $settings): RedirectResponse
    {
        $s = $settings->all();

        if ($s['endpoint'] === '' || $s['token'] === '') {
            return back()->with('error', __('Please save an endpoint URL and API token first.'));
        }

        try {
            $response = Http::timeout(8)->withToken($s['token'])->acceptJson()->post($s['endpoint'], [
                'customer_name' => 'Rechnerei Inquiries – Test',
                'source' => 'statamic-test',
                'form' => [
                    'message' => 'This is a test inquiry sent from the Rechnerei Inquiries Statamic addon settings page.',
                    'site_url' => config('app.url'),
                ],
            ]);

            if ($response->successful()) {
                return back()->with('success', __('Test inquiry sent successfully. Check your Rechnerei inbox.'));
            }

            return back()->with('error', __('Test inquiry failed (:code). Double-check the endpoint URL and API token.', ['code' => $response->status()]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Test inquiry failed: :message', ['message' => $e->getMessage()]));
        }
    }
}
