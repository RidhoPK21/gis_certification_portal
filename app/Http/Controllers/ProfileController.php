<?php

namespace App\Http\Controllers;

use App\Services\FileStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'company_name' => ['nullable', 'string', 'max:200'],
            'job_title' => ['nullable', 'string', 'max:100'],
        ]);

        $user->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }

    /**
     * Upload / ganti tanda tangan elektronik. Disimpan sebagai JPEG latar
     * putih pada disk privat agar bisa disisipkan ke PDF (SimplePdf hanya
     * menerima JPEG). Upload ulang menimpa file lama.
     */
    public function signature(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageSignature(), 403);

        $request->validate([
            'signature' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $jpeg = $this->toWhiteJpeg($request->file('signature')->getRealPath());
        $newPath = 'signatures/'.$user->id.'_esign_'.Str::random(8).'.jpg';
        Storage::disk('private')->put($newPath, $jpeg);

        $old = $user->signature_path;
        $user->update(['signature_path' => $newPath]);
        if ($old && $old !== $newPath) {
            Storage::disk('private')->delete($old);
        }

        return back()->with('success', 'Tanda tangan elektronik berhasil disimpan.');
    }

    public function removeSignature(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canManageSignature(), 403);

        if ($user->signature_path) {
            Storage::disk('private')->delete($user->signature_path);
            $user->update(['signature_path' => null]);
        }

        return back()->with('success', 'Tanda tangan elektronik dihapus.');
    }

    public function signaturePreview(Request $request, FileStorageService $files): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->hasSignature(), 404);

        return $files->response($user->signature_path, 'tanda-tangan.jpg', 'inline');
    }

    /**
     * Konversi gambar (PNG/JPG) menjadi JPEG dengan latar putih (rata,
     * tanpa transparansi) menggunakan GD.
     */
    private function toWhiteJpeg(string $absolutePath): string
    {
        $info = @getimagesize($absolutePath);
        abort_unless($info, 422, 'Berkas gambar tidak valid.');

        $source = match ($info['mime'] ?? null) {
            'image/png' => imagecreatefrompng($absolutePath),
            'image/jpeg' => imagecreatefromjpeg($absolutePath),
            default => null,
        };
        abort_unless($source, 422, 'Format gambar tidak didukung.');

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 90);
        $data = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($canvas);

        return $data;
    }
}
