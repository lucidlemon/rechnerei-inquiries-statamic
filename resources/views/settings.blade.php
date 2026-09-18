@extends('statamic::layout')

@section('title', 'Rechnerei Inquiries')

@section('content')
    <style>
        .rechnerei-settings { max-width: 720px; }
        .rechnerei-settings h1 { font-size: 1.5rem; font-weight: 600; margin-bottom: .25rem; }
        .rechnerei-settings p.intro { color: #6b7280; margin-bottom: 1.5rem; }
        .rechnerei-settings .card { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .rechnerei-settings label { display: block; font-weight: 600; margin-bottom: .25rem; }
        .rechnerei-settings .field { margin-bottom: 1.25rem; }
        .rechnerei-settings .help { color: #6b7280; font-size: .8125rem; margin-top: .25rem; }
        .rechnerei-settings input[type=text],
        .rechnerei-settings input[type=url],
        .rechnerei-settings input[type=password],
        .rechnerei-settings textarea {
            width: 100%; border: 1px solid #d1d5db; border-radius: .375rem; padding: .5rem .625rem;
            font-family: inherit; font-size: .875rem;
        }
        .rechnerei-settings textarea { font-family: ui-monospace, monospace; font-size: .8125rem; }
        .rechnerei-settings .checkbox-field { display: flex; align-items: center; gap: .5rem; }
        .rechnerei-settings .checkbox-field label { margin: 0; font-weight: 500; }
        .rechnerei-settings button {
            background: #171717; color: #fff; border: none; border-radius: .375rem;
            padding: .5rem 1rem; font-size: .875rem; cursor: pointer;
        }
        .rechnerei-settings button.secondary { background: #fff; color: #171717; border: 1px solid #d1d5db; }
        .rechnerei-settings .alert { padding: .75rem 1rem; border-radius: .375rem; margin-bottom: 1rem; font-size: .875rem; }
        .rechnerei-settings .alert-success { background: #ecfdf5; color: #065f46; }
        .rechnerei-settings .alert-error { background: #fef2f2; color: #991b1b; }
    </style>

    <div class="rechnerei-settings">
        <h1>Rechnerei Inquiries</h1>
        <p class="intro">
            Forwards outgoing site emails (Statamic form notifications, etc.) to your Rechnerei inquiries
            inbox. Your site keeps sending its emails exactly as before &mdash; this only sends a copy of
            relevant messages.
        </p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">
                <ul style="margin:0;padding-left:1.25rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ cp_route('rechnerei-inquiries.update') }}">
            @csrf
            <div class="card">
                <div class="field checkbox-field">
                    <input type="checkbox" id="enabled" name="enabled" value="1" @checked($settings['enabled'])>
                    <label for="enabled">Forward inquiries to Rechnerei</label>
                </div>

                <div class="field">
                    <label for="endpoint">Endpoint URL</label>
                    <input type="url" id="endpoint" name="endpoint" value="{{ old('endpoint', $settings['endpoint']) }}"
                           placeholder="https://your-business.rechnerei.io/api/inquiries">
                    <div class="help">Find this under Inquiries &rarr; Website integration in your Rechnerei account.</div>
                </div>

                <div class="field">
                    <label for="token">API Token</label>
                    <input type="password" id="token" name="token" autocomplete="off" value="{{ old('token', $settings['token']) }}">
                </div>

                <div class="field">
                    <label for="ignore_keywords">Ignore emails containing</label>
                    <textarea id="ignore_keywords" name="ignore_keywords" rows="8">{{ old('ignore_keywords', $settings['ignore_keywords']) }}</textarea>
                    <div class="help">
                        One phrase per line. Any outgoing email whose subject or body contains one of these
                        phrases is skipped (case-insensitive). Pre-filled with common system notifications
                        (password resets, backups, failed jobs, &hellip;). Advanced: a line wrapped in
                        slashes, e.g. <code>/invoice #\d+/i</code>, is treated as a regular expression.
                    </div>
                </div>

                <button type="submit">Save</button>
            </div>
        </form>

        <div class="card">
            <label style="margin-bottom:.5rem;">Test connection</label>
            <p class="help" style="margin-bottom:.75rem;">
                Sends a small test inquiry to Rechnerei using the settings currently saved above (save first
                if you just changed them).
            </p>
            <form method="POST" action="{{ cp_route('rechnerei-inquiries.test') }}">
                @csrf
                <button type="submit" class="secondary">Send test inquiry</button>
            </form>
        </div>
    </div>
@endsection
