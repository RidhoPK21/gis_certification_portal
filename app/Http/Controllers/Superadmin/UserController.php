<?php

namespace App\Http\Controllers\Superadmin;

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
