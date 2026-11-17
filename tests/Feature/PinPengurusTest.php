<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\RequirePin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinPengurusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buatGeng();
    }

    public function test_warga_biasa_tidak_bisa_membuka_menu_pengurus(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('pengurus.warga.index'))->assertForbidden();
        $this->get(route('pin.buat'))->assertForbidden();
    }

    public function test_semua_role_pengurus_bisa_membuka_menu_pengurus(): void
    {
        foreach ([Role::Ketua, Role::Sekretaris, Role::Bendahara, Role::TataTertib] as $role) {
            $this->masukSebagaiPengurus($role);
            $this->get(route('pengurus.warga.index'))->assertOk();
        }
    }

    public function test_pengurus_tanpa_pin_wajib_membuat_pin_dulu(): void
    {
        $pengurus = User::factory()->pengurus()->create();
        $this->actingAs($pengurus);

        $this->get(route('pengurus.tugas.index'))->assertRedirect(route('pin.buat'));

        $this->post(route('pin.simpan'), ['pin' => '12345', 'pin_confirmation' => '12345'])->assertSessionHasErrors('pin');
        $this->post(route('pin.simpan'), ['pin' => '123456', 'pin_confirmation' => '654321'])->assertSessionHasErrors('pin');
        $this->post(route('pin.simpan'), ['pin' => 'abcdef', 'pin_confirmation' => 'abcdef'])->assertSessionHasErrors('pin');

        $this->post(route('pin.simpan'), ['pin' => '246810', 'pin_confirmation' => '246810'])
            ->assertRedirect(route('pengurus.tugas.index'));

        $this->assertTrue($pengurus->fresh()->cekPin('246810'));
        $this->assertNotSame('246810', $pengurus->fresh()->pin_hash);
        $this->get(route('pengurus.tugas.index'))->assertOk();
    }

    public function test_pin_diminta_di_sesi_baru(): void
    {
        $pengurus = User::factory()->pengurus()->denganPin('123456')->create();
        $token = $pengurus->buatTokenBaru();

        // Masuk lewat link = sesi baru, PIN belum diverifikasi.
        $this->get("/m/{$token}");
        $this->get(route('pengurus.warga.index'))->assertRedirect(route('pin.masukkan'));

        $this->post(route('pin.verifikasi'), ['pin' => '000000'])->assertSessionHasErrors('pin');
        $this->post(route('pin.verifikasi'), ['pin' => '123456'])->assertRedirect(route('pengurus.warga.index'));
        $this->get(route('pengurus.warga.index'))->assertOk();
    }

    public function test_pin_kedaluwarsa_setelah_2_jam_tidak_ada_aktivitas(): void
    {
        $this->masukSebagaiPengurus();

        $this->travel(119)->minutes();
        $this->get(route('pengurus.warga.index'))->assertOk();

        // Aktivitas tadi menggeser batas idle.
        $this->travel(119)->minutes();
        $this->get(route('pengurus.warga.index'))->assertOk();

        $this->travel(121)->minutes();
        $this->get(route('pengurus.warga.index'))->assertRedirect(route('pin.masukkan'));
    }

    public function test_dashboard_warga_tidak_butuh_pin(): void
    {
        $pengurus = User::factory()->pengurus()->denganPin()->create();

        $this->actingAs($pengurus)->get(route('dashboard'))->assertOk();
    }

    public function test_salah_pin_5_kali_dikunci_sementara(): void
    {
        $pengurus = User::factory()->pengurus()->denganPin('123456')->create();
        $this->actingAs($pengurus);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('pin.verifikasi'), ['pin' => '000000']);
        }

        $this->post(route('pin.verifikasi'), ['pin' => '123456'])->assertSessionHasErrors('pin');
        $this->assertNull(session(RequirePin::SESSION_KEY));
    }

    public function test_pin_tidak_bisa_dibuat_ulang_lewat_form_kalau_sudah_ada(): void
    {
        $pengurus = User::factory()->pengurus()->denganPin('123456')->create();
        $this->actingAs($pengurus);

        $this->post(route('pin.simpan'), ['pin' => '999999', 'pin_confirmation' => '999999'])->assertForbidden();
        $this->assertTrue($pengurus->fresh()->cekPin('123456'));
    }

    public function test_reset_pin_lewat_artisan(): void
    {
        $pengurus = User::factory()->pengurus()->denganPin()->create();

        $this->artisan('app:reset-pin', ['email' => $pengurus->email])->assertSuccessful();

        $this->assertNull($pengurus->fresh()->pin_hash);
    }
}
