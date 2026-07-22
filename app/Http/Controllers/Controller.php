<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Balasan seragam setelah aksi simpan.
     *
     * Untuk permintaan AJAX (data-ajax) dikembalikan JSON sehingga
     * halaman tidak perlu reload; selain itu kembali seperti biasa
     * dengan pesan flash.
     */
    protected function savedResponse(Request $request, string $message, array $extra = [])
    {
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $message] + $extra);
        }

        return back()->with('success', $message);
    }
}
