<?php

namespace Rechnerei\Inquiries\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Rechnerei\Inquiries\Settings;
use Rechnerei\Inquiries\Support\SettingsBlueprint;
use Statamic\Facades\Blueprint;
use Statamic\Http\Controllers\CP\CpController;
use Statamic\Support\Arr;

class SettingsController extends CpController
{
    public function index(Settings $settings)
    {
        $blueprint = Blueprint::make()->setContents(SettingsBlueprint::build());
        $fields = $blueprint->fields()->addValues($settings->all())->preProcess();

        return Inertia::render('rechnerei-inquiries::settings', [
            'title' => 'Rechnerei Inquiries',
            'action' => cp_route('rechnerei-inquiries.update'),
            'testAction' => cp_route('rechnerei-inquiries.test'),
            'initialBlueprint' => $blueprint->toPublishArray(),
            'initialValues' => $fields->values()->all(),
            'initialMeta' => $fields->meta(),
        ]);
    }

    public function update(Request $request, Settings $settings): JsonResponse
    {
        $blueprint = Blueprint::make()->setContents(SettingsBlueprint::build());
        $fields = $blueprint->fields()->addValues($request->all());

        $fields->validate();

        $values = Arr::removeNullValues($fields->process()->values()->all());

        $settings->save([
            'enabled' => (bool) ($values['enabled'] ?? false),
            'endpoint' => $values['endpoint'] ?? '',
            'token' => $values['token'] ?? '',
            'ignore_keywords' => $values['ignore_keywords'] ?? '',
        ]);

        return response()->json(['message' => __('Saved.')]);
    }

    public function test(Settings $settings): JsonResponse
    {
        $s = $settings->all();

        if ($s['endpoint'] === '' || $s['token'] === '') {
            return response()->json(['message' => __('Please save an endpoint URL and API token first.')], 422);
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
                return response()->json(['message' => __('Test inquiry sent successfully. Check your Rechnerei inbox.')]);
            }

            return response()->json([
                'message' => __('Test inquiry failed (:code). Double-check the endpoint URL and API token.', ['code' => $response->status()]),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => __('Test inquiry failed: :message', ['message' => $e->getMessage()])], 422);
        }
    }
}
