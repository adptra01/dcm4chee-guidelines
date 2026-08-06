<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcedureTest extends TestCase
{
    use RefreshDatabase;

    public function test_procedure_page_renders_for_authed_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/procedures')
            ->assertOk()
            ->assertSee('Prosedur');
    }

    public function test_procedure_belongs_to_order(): void
    {
        $procedure = Procedure::create([
            'code' => 'DX-CHEST', 'name' => 'Foto Thorax PA', 'modality' => 'DX',
        ]);
        $patient = Patient::create(['patient_id' => 'MRN-P1', 'name' => 'Pasien P', 'sex' => 'M']);

        $order = Order::create([
            'order_no' => 'ORD-P1', 'patient_id' => $patient->id,
            'procedure_id' => $procedure->id, 'modality' => 'DX',
        ]);

        $this->assertSame('Foto Thorax PA', $order->procedure->name);
        $this->assertCount(1, $procedure->orders);
    }
}
