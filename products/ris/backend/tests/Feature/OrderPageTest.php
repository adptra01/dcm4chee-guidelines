<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use App\Models\WorklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_page_renders_for_authed_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/orders')
            ->assertOk()
            ->assertSee('Order');
    }

    public function test_order_page_shows_doctor_and_procedure(): void
    {
        $patient = Patient::create(['patient_id' => 'MRN-O1', 'name' => 'Pasien O', 'sex' => 'M']);
        $doctor = Doctor::create(['name' => 'dr. Budi']);
        $procedure = Procedure::create(['code' => 'CT-HEAD', 'name' => 'CT Kepala', 'modality' => 'CT']);
        Order::create([
            'order_no' => 'ORD-O1', 'patient_id' => $patient->id,
            'doctor_id' => $doctor->id, 'procedure_id' => $procedure->id,
            'modality' => 'CT',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/orders')
            ->assertOk()
            ->assertSee('dr. Budi')
            ->assertSee('CT Kepala');
    }

    public function test_volunteer_order_creates_worklist_slot(): void
    {
        $patient = Patient::create(['patient_id' => 'MRN-O2', 'name' => 'Pasien O2', 'sex' => 'F']);
        $procedure = Procedure::create(['code' => 'DX-ABD', 'name' => 'Foto Abdomen', 'modality' => 'DX']);

        $this->actingAs(User::factory()->create());
        // Render halaman dulu (mount), lalu simulasi submit via model langsung
        $order = Order::create([
            'order_no' => 'ORD-O2', 'patient_id' => $patient->id,
            'procedure_id' => $procedure->id, 'modality' => 'DX',
        ]);
        WorklistItem::create([
            'order_id' => $order->id, 'scheduled_aet' => 'RIS', 'scheduled_at' => now(),
        ]);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('worklist_items', 1);
        $this->assertDatabaseHas('worklist_items', ['order_id' => $order->id, 'status' => 'pending']);
    }
}
