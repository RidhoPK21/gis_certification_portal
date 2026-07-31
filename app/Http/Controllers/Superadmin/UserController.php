<?php

namespace App\Http\Controllers\Superadmin;

use App\Actions\Fortify\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Mail\EmailOtpMail;
use App\Models\EmailOtp;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use PasswordValidationRules;

    public function index()
    {
        return view('superadmin.users', [
            'users' => User::with('roles')->latest()->paginate(25),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        return view('superadmin.users-create', [
            'roles' => Role::orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Halaman kelola satu akun: role, status, reset kata sandi, dan hapus.
     */
    public function edit(User $user)
    {
        $user->load('roles');

        return view('superadmin.users-edit', [
            'user' => $user,
            'roles' => Role::orderBy('sort_order')->get(),
            'applicationCount' => $user->applications()->count(),
        ]);
    }

    public function store(
        Request $request,
        OtpService $otp,
        AuditLogger $audit
    ) {
        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
                'job_title' => ['nullable', 'string', 'max:100'],
                'roles' => ['required', 'array', 'min:1'],
                'roles.*' => ['integer', 'exists:roles,id'],
            ],
            [
                'name.required' => 'Nama lengkap wajib diisi.',
                'email.required' => 'Alamat email wajib diisi.',
                'email.email' => 'Format alamat email tidak valid.',
                'email.unique' => 'Alamat email sudah digunakan.',
                'roles.required' => 'Pilih minimal satu role.',
            ]
        );

        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => Str::lower(trim($data['email'])),
                'job_title' => $data['job_title'] ?? null,

                /*
                 * Kata sandi acak yang tidak mungkin ditebak sebagai
                 * penanda "belum punya kata sandi". Diganti oleh staf
                 * sendiri saat aktivasi akun.
                 */
                'password' => Hash::make(Str::random(64)),
                'is_active' => true,
            ]);

            $user->roles()->sync($data['roles']);

            return $user;
        });

        $this->sendInvite($user, $otp);

        $audit->log('user.invited', $user, [], [
            'email' => $user->email,
            'roles' => $user->roles->pluck('code')->all(),
        ]);

        return redirect()
            ->route('superadmin.users.index')
            ->with('success', 'Undangan aktivasi telah dikirim ke ' . $user->email . '.');
    }

    public function resendInvite(
        User $user,
        OtpService $otp,
        AuditLogger $audit
    ) {
        if ($user->email_verified_at !== null) {
            return back()->withErrors([
                'invite' => 'Akun ini sudah diaktifkan, undangan tidak perlu dikirim ulang.',
            ]);
        }

        $this->sendInvite($user, $otp);

        $audit->log('user.invite_resent', $user);

        return back()->with('success', 'Undangan aktivasi dikirim ulang ke ' . $user->email . '.');
    }

    /**
     * Menghapus akun. Sengaja ditolak bila akun masih memiliki permohonan,
     * karena applications.client_id memakai cascade delete: menghapus klien
     * ikut menghapus seluruh permohonan, dokumen, invoice, dan sertifikatnya.
     */
    public function destroy(Request $request, User $user, AuditLogger $audit)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'Anda tidak dapat menghapus akun Anda sendiri.']);
        }

        $applications = $user->applications()->count();
        if ($applications > 0) {
            return back()->withErrors([
                'delete' => 'Akun ini memiliki ' . $applications . ' permohonan sehingga tidak dapat dihapus. '
                    . 'Nonaktifkan akunnya bila tidak dipakai lagi, agar riwayat sertifikasi tetap utuh.',
            ]);
        }

        /*
         * Jaring pengaman: dalam alur web ini tidak terjangkau (penghapus
         * selalu superadmin aktif dan tidak bisa menghapus dirinya sendiri),
         * tetapi tetap dipertahankan bila kelak ada jalur lain seperti CLI.
         */
        if ($user->is_active && $user->hasRole('superadmin') && $this->activeSuperadminCount() <= 1) {
            return back()->withErrors([
                'delete' => 'Ini satu-satunya superadmin aktif. Tunjuk superadmin lain lebih dahulu.',
            ]);
        }

        $snapshot = [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->roles->pluck('code')->all(),
        ];

        DB::transaction(function () use ($user): void {
            EmailOtp::where('user_id', $user->id)->delete();
            $user->roles()->detach();
            $user->delete();
        });

        $audit->log('user.deleted', User::class, $snapshot, []);

        return redirect()
            ->route('superadmin.users.index')
            ->with('success', 'Akun ' . $snapshot['email'] . ' telah dihapus.');
    }

    /**
     * Mengirim kode reset ke email pengguna. Kata sandi baru ditentukan
     * sendiri oleh pemilik akun, sehingga superadmin tidak mengetahuinya.
     */
    public function sendPasswordReset(User $user, OtpService $otp, AuditLogger $audit)
    {
        $code = $otp->generate($user, EmailOtp::PURPOSE_PASSWORD_RESET);

        Mail::to($user->email)->send(
            new EmailOtpMail($user, $code, EmailOtp::PURPOSE_PASSWORD_RESET)
        );

        $audit->log('user.password_reset_sent', $user);

        return back()->with('success', 'Kode reset kata sandi dikirim ke ' . $user->email . '.');
    }

    /**
     * Jalur cadangan bila pengguna tidak dapat mengakses emailnya:
     * superadmin menetapkan kata sandi sementara secara langsung.
     */
    public function setPassword(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate(
            ['password' => $this->passwordRules()],
            [
                'password.required' => 'Kata sandi baru wajib diisi.',
                'password.confirmed' => 'Konfirmasi kata sandi tidak sesuai.',
            ]
        );

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        $audit->log('user.password_set_by_superadmin', $user);

        return back()->with(
            'success',
            'Kata sandi ' . $user->email . ' diperbarui. Sampaikan ke pengguna dan minta segera menggantinya lewat menu Profil.'
        );
    }

    private function activeSuperadminCount(): int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('code', 'superadmin'))
            ->count();
    }

    public function update(Request $request, User $user, AuditLogger $audit)
    {
        $data = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $old = ['roles' => $user->roles->pluck('code'), 'is_active' => $user->is_active];
        $user->roles()->sync($data['roles']);
        $user->update(['is_active' => $request->boolean('is_active')]);
        $audit->log('user.access_updated', $user, $old, ['roles' => $user->fresh('roles')->roles->pluck('code'), 'is_active' => $user->is_active]);

        return back()->with('success', 'Role dan status akun diperbarui.');
    }

    /**
     * Dikirim sinkron, bukan queue: kode aktivasi bersifat
     * waktu-terbatas dan queue database butuh worker aktif.
     */
    private function sendInvite(User $user, OtpService $otp): void
    {
        $code = $otp->generate(
            $user,
            EmailOtp::PURPOSE_ADMIN_INVITE
        );

        Mail::to($user->email)->send(
            new EmailOtpMail($user, $code, EmailOtp::PURPOSE_ADMIN_INVITE)
        );
    }
}
