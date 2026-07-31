@php($turnstile = app(\App\Services\TurnstileService::class))

@if ($turnstile->enabled())
    <div class="form-group">
        <div
            class="cf-turnstile"
            data-sitekey="{{ $turnstile->siteKey() }}"
            data-language="id"
            data-theme="light"
        ></div>

        @error(\App\Services\TurnstileService::FIELD)
            <div class="form-error">
                {{ $message }}
            </div>
        @enderror
    </div>

    @push('scripts')
        <script
            src="https://challenges.cloudflare.com/turnstile/v0/api.js"
            async
            defer
        ></script>
    @endpush
@endif
