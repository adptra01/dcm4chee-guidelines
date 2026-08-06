<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_page_renders_for_authed_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/doctors')
            ->assertOk()
            ->assertSee('Dokter');
    }

    public function test_doctor_belongs_to_order(): void
    {
        $doctor = Doctor::create(['name' => 'dr. Andi', 'specialization' => 'Radiologi']);
        $patient = Patient::create(['patient_id' => 'MRN-D1', 'name' => 'Pasien D', 'sex' => 'M']);

        $order = Order::create([
            'order_no' => 'ORD-D1', 'patient_id' => $patient->id,
            'doctor_id' => $doctor->id, 'modality' => 'CT',
        ]);

        $this->assertSame('dr. Andi', $order->doctor->name);
        $this->assertCount(1, $doctor->orders);
    }
}
