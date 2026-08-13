<?php

namespace App\Services;

use App\Models\CertificationScheme;
use App\Models\User;

/**
 * Menentukan tim mana yang memiliki sebuah skema.
 *
 * Seluruh pembagian ISPO ke Tim Sustain bermuara di sini: antrean menyaring
 * lewat templatesOwnedBy(), penjagaan halaman lewat handles(), dan notifikasi
 * lewat adminRoleFor()/technicalRoleFor(). Menambah pembagian baru cukup
 * dengan menyunting config/scheme_ownership.php.
 */
class SchemeOwnershipService
{
    /**
     * Kode role Admin yang memiliki skema dengan template tersebut.
     */
    public function adminRoleFor(?string $template): string
    {
        return $this->pemilik($template)['admin'];
    }

    /**
     * Kode role Tim Teknis yang memiliki skema dengan template tersebut.
     */
    public function technicalRoleFor(?string $template): string
    {
        return $this->pemilik($template)['technical'];
    }

    /**
     * Daftar review_template yang boleh ditangani pengguna ini.
     *
     * Mengembalikan null bila pengguna tidak tunduk pada pembagian ini, yaitu
     * Superadmin maupun peran yang memang dipakai bersama seluruh skema —
     * Finance, Auditor, Klien. Cakupan mereka dibatasi cara lain: status order
     * untuk Finance, penugasan untuk Auditor, kepemilikan order untuk Klien.
     * Menyaring mereka per skema justru mengosongkan antreannya.
     *
     * Larik kosong hanya mungkin muncul bila pengguna memegang peran pemilik
     * tetapi tak satu pun template menjadi miliknya, dan artinya "tidak melihat
     * apa-apa" — bukan "tidak disaring".
     *
     * @return array<int, string>|null
     */
    public function templatesOwnedBy(User $user): ?array
    {
        /*
         * Sengaja membaca relasi yang sudah dimuat, bukan hasRole(): metode itu
         * selalu menembak basis data sehingga akan berbeda hasilnya untuk
         * pengguna yang belum tersimpan, dan memicu kueri berulang saat scope
         * ini dipakai pada daftar.
         */
        $user->loadMissing('roles');

        $kodeRole = $user->roles->where('is_active', true)->pluck('code')->all();

        $tanpaBatas = (array) config('scheme_ownership.unrestricted_roles', []);

        if (array_intersect($kodeRole, $tanpaBatas) !== []) {
            return null;
        }

        // Di luar pembagian sama sekali: tidak ada yang perlu disaring di sini.
        if (array_intersect($kodeRole, $this->seluruhPeranPemilik()) === []) {
            return null;
        }

        $dimiliki = [];

        foreach ($this->seluruhTemplate() as $template) {
            $pemilik = $this->pemilik($template);

            if (array_intersect([$pemilik['admin'], $pemilik['technical']], $kodeRole) !== []) {
                $dimiliki[] = $template;
            }
        }

        return array_values(array_unique($dimiliki));
    }

    /**
     * Apakah pengguna berhak menangani skema ini.
     */
    public function handles(User $user, ?CertificationScheme $scheme): bool
    {
        $dimiliki = $this->templatesOwnedBy($user);

        if ($dimiliki === null) {
            return true;
        }

        return in_array((string) $scheme?->review_template, $dimiliki, true);
    }

    /**
     * Seluruh kode role yang muncul sebagai pemilik skema, baik pada peta
     * pembagian maupun pada pemilik bawaan. Memegang salah satunya berarti
     * tunduk pada pembagian; tidak memegang satu pun berarti berada di luarnya.
     *
     * @return array<int, string>
     */
    private function seluruhPeranPemilik(): array
    {
        $peta = (array) config('scheme_ownership.by_template', []);
        $bawaan = (array) config('scheme_ownership.default');

        $kode = array_values($bawaan);

        foreach ($peta as $pemilik) {
            $kode = array_merge($kode, array_values((array) $pemilik));
        }

        return array_values(array_unique($kode));
    }

    /**
     * @return array<string, string>
     */
    private function pemilik(?string $template): array
    {
        $peta = (array) config('scheme_ownership.by_template', []);
        $bawaan = (array) config('scheme_ownership.default');

        return ($peta[(string) $template] ?? []) + $bawaan;
    }

    /**
     * Seluruh review_template yang dikenal: yang disebut pada peta pembagian,
     * ditambah yang benar-benar dipakai skema di katalog.
     *
     * Dibaca dari katalog, bukan didaftar manual, supaya skema baru dengan
     * template baru otomatis jatuh ke pemilik bawaan tanpa perlu diingat.
     *
     * @return array<int, string>
     */
    private function seluruhTemplate(): array
    {
        $dariKatalog = CertificationScheme::query()
            ->distinct()
            ->pluck('review_template')
            ->filter()
            ->all();

        $dariPeta = array_keys((array) config('scheme_ownership.by_template', []));

        return array_values(array_unique(array_merge($dariKatalog, $dariPeta)));
    }
}
