<?php

namespace Tests\Feature;

use App\Models\CertificateFinal;
use App\Models\CertificationApplication;
use App\Models\CertificationScheme;
use App\Models\Role;
use App\Models\SurveillanceSchedule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SchemeCatalogSeeder;
use Database\Seeders\WorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveillanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(SchemeCatalogSeeder::class);
        $this->seed(WorkflowSeeder::class);
    }

    private function user(string $roleCode): User
    {
        $user = User::create([
            'name' => ucfirst($roleCode),
            'email' => $roleCode . Str::random(4) . '@example.com',
            'password' => 'RahasiaKuat123',
            'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('code', $roleCode)->value('id'));

        return $user;
    }

    private function application(User $client, string $status): CertificationApplication
    {
        $scheme = CertificationScheme::orderBy('sort_order')->firstOrFail();

        return CertificationApplication::create([
            'uuid' => (string) Str::uuid(),
            'client_id' => $client->id,
            'certification_scheme_id' => $scheme->id,
            'form_version' => $scheme->form_version,
            'status' => $status,
            'current_step' => $status,
            'company_name' => 'PT Uji',
            'contact_email' => 'kontak@uji.test',
            'order_number' => 'SURV-' . Str::random(4),
            'order_date' => today(),
        ]);
    }

    public function test_upload_final_menghasilkan_rencana_surveillance(): void
    {
        Storage::fake('private');
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'certificate_review');

        $this->actingAs($tech)
            ->post(route('technical.final.upload', $app), [
                'certificate' => UploadedFile::fake()->create('final.pdf', 200, 'application/pdf'),
                'certificate_number' => 'GIS-SURV-CERT',
                'issued_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertTrue($app->surveillanceSchedules()->exists());
        $this->assertDatabaseHas('surveillance_schedules', [
            'application_id' => $app->id,
            'cycle' => 1,
            'status' => 'planned',
            'formula_version' => 'GIS-SURV-1.0',
        ]);
    }

    public function test_complete_mengaktifkan_status_surveillance(): void
    {
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'final_certificate');
        $final = CertificateFinal::create([
            'application_id' => $app->id, 'certificate_number' => 'GIS-C-1', 'original_name' => 'f.pdf',
            'file_path' => 'x/f.pdf', 'checksum_sha256' => str_repeat('a', 64), 'issued_date' => today(), 'status' => 'released',
        ]);
        SurveillanceSchedule::create([
            'application_id' => $app->id, 'certificate_final_id' => $final->id, 'cycle' => 1,
            'planned_date' => today()->addMonths(11), 'status' => 'planned',
        ]);

        $this->actingAs($tech)
            ->post(route('technical.complete', $app), [
                'notes' => 'Selesai.', 'action_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertSame('surveillance', $app->refresh()->status);
    }

    public function test_teknis_dapat_memperbarui_jadwal_surveillance(): void
    {
        $this->seedAll();
        $tech = $this->user('technical');
        $app = $this->application($this->user('client'), 'surveillance');
        $schedule = SurveillanceSchedule::create([
            'application_id' => $app->id, 'cycle' => 1,
            'planned_date' => today()->addMonths(11), 'status' => 'planned',
        ]);

        $this->actingAs($tech)
            ->post(route('technical.surveillance.update', $schedule), [
                'scheduled_date' => today()->addMonths(11)->format('Y-m-d'),
                'status' => 'scheduled',
                'notes' => 'Dijadwalkan bersama klien.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('surveillance_schedules', [
            'id' => $schedule->id,
            'status' => 'scheduled',
        ]);
    }
}
