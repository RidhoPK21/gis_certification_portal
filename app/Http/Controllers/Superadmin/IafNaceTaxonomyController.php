<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationValue;
use App\Models\IafCode;
use App\Models\NaceCode;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Pengelolaan ruang lingkup akreditasi (KAN K-07.01 Rev.2 Lampiran 1).
 *
 * Isi awalnya datang dari IafNaceTaxonomySeeder; halaman ini dipakai saat KAN
 * merevisi lampirannya. Menonaktifkan menyembunyikan kode dari form klien tanpa
 * mengusik permohonan lama; menghapus hanya boleh untuk kode yang belum pernah
 * dipilih siapa pun.
 */
class IafNaceTaxonomyController extends Controller
{
    public function index()
    {
        return view('superadmin.iaf-nace', [
            'iafCodes' => IafCode::with('naceCodes')
                ->orderBy('sort_order')
                ->paginate(10)
                ->withQueryString(),
            'allIafCodes' => IafCode::orderBy('sort_order')->get(['id', 'code', 'name_id']),
        ]);
    }

    public function storeIaf(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:iaf_codes,code'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_id' => ['required', 'string', 'max:255'],
        ]);

        $data['sort_order'] = (int) IafCode::max('sort_order') + 1;
        $data['is_active'] = true;

        $iaf = IafCode::create($data);
        $audit->log('iaf_nace.iaf_created', $iaf);

        return back()->with('success', 'Kode IAF '.$iaf->code.' ditambahkan.');
    }

    public function updateIaf(Request $request, IafCode $iaf, AuditLogger $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('iaf_codes', 'code')->ignore($iaf->id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_id' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $old = $iaf->toArray();
        $data['is_active'] = $request->boolean('is_active');
        $iaf->update($data);
        $audit->log('iaf_nace.iaf_updated', $iaf, $old, $iaf->fresh()->toArray());

        return back()->with('success', 'Kode IAF diperbarui.');
    }

    public function destroyIaf(IafCode $iaf, AuditLogger $audit)
    {
        $this->pastikanBelumDipakai('iaf_code', $iaf->code);

        // Kode NACE di bawahnya pun tidak boleh sedang dipakai.
        foreach ($iaf->naceCodes as $nace) {
            $this->pastikanBelumDipakai('nace_code', $nace->code);
        }

        $kode = $iaf->code;
        $audit->log('iaf_nace.iaf_deleted', $iaf, $iaf->toArray(), []);
        $iaf->delete();

        return back()->with('success', 'Kode IAF '.$kode.' beserta kode NACE di bawahnya dihapus.');
    }

    public function storeNace(Request $request, AuditLogger $audit)
    {
        $data = $request->validate([
            'iaf_code_id' => ['required', 'exists:iaf_codes,id'],
            'code' => ['required', 'string', 'max:20'],
            'name_en' => ['required', 'string', 'max:500'],
            'name_id' => ['required', 'string', 'max:1000'],
        ]);

        /*
         * Keunikan berlaku per IAF, bukan global: NACE 17 sah berada di IAF 7a
         * sekaligus 7b menurut Lampiran 1.
         */
        $sudahAda = NaceCode::where('iaf_code_id', $data['iaf_code_id'])
            ->where('code', $data['code'])
            ->exists();

        if ($sudahAda) {
            return back()
                ->withInput()
                ->withErrors(['code' => 'Kode NACE ini sudah ada pada IAF tersebut.']);
        }

        $data['sort_order'] = (int) NaceCode::where('iaf_code_id', $data['iaf_code_id'])->max('sort_order') + 1;
        $data['is_active'] = true;

        $nace = NaceCode::create($data);
        $audit->log('iaf_nace.nace_created', $nace);

        return back()->with('success', 'Kode NACE '.$nace->code.' ditambahkan.');
    }

    public function updateNace(Request $request, NaceCode $nace, AuditLogger $audit)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name_en' => ['required', 'string', 'max:500'],
            'name_id' => ['required', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bentrok = NaceCode::where('iaf_code_id', $nace->iaf_code_id)
            ->where('code', $data['code'])
            ->where('id', '!=', $nace->id)
            ->exists();

        if ($bentrok) {
            return back()->withErrors(['code' => 'Kode NACE ini sudah ada pada IAF tersebut.']);
        }

        $old = $nace->toArray();
        $data['is_active'] = $request->boolean('is_active');
        $nace->update($data);
        $audit->log('iaf_nace.nace_updated', $nace, $old, $nace->fresh()->toArray());

        return back()->with('success', 'Kode NACE diperbarui.');
    }

    public function destroyNace(NaceCode $nace, AuditLogger $audit)
    {
        $this->pastikanBelumDipakai('nace_code', $nace->code);

        $kode = $nace->code;
        $audit->log('iaf_nace.nace_deleted', $nace, $nace->toArray(), []);
        $nace->delete();

        return back()->with('success', 'Kode NACE '.$kode.' dihapus.');
    }

    /**
     * Menolak penghapusan kode yang pernah dipilih permohonan mana pun.
     *
     * Permohonan menyimpan kode sebagai teks, bukan relasi, supaya riwayatnya
     * tetap utuh meski daftar acuan berubah. Karena itu pemeriksaannya juga
     * berdasarkan nilai teks yang tersimpan.
     */
    private function pastikanBelumDipakai(string $fieldCode, string $nilai): void
    {
        $dipakai = ApplicationValue::where('field_code', $fieldCode)
            ->where('value_text', $nilai)
            ->exists();

        abort_if(
            $dipakai,
            422,
            'Kode ini sudah dipakai permohonan sehingga tidak dapat dihapus. Nonaktifkan saja agar tidak muncul lagi pada form klien.'
        );
    }
}
