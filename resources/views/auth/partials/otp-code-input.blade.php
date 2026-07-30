<div class="form-group">
    <label
        class="form-label"
        for="code"
    >
        Kode Verifikasi (6 angka)
    </label>

    <input
        class="form-control @error('code') is-invalid @enderror"
        id="code"
        type="text"
        name="code"
        value="{{ old('code') }}"
        placeholder="000000"
        inputmode="numeric"
        autocomplete="one-time-code"
        maxlength="6"
        pattern="[0-9]{6}"
        style="letter-spacing: 10px; font-size: 20px; text-align: center;"
        required
        autofocus
    >

    @error('code')
        <div class="form-error">
            {{ $message }}
        </div>
    @enderror
</div>
