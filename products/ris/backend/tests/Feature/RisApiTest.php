<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Patient;
use App\Models\Report;
use App\Models\WorklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_crud(): void
    {
        $this->postJson('/api/patients', [
            'patient_id' => 'MRN-X1', 'name' => 'Test Pasien', 'sex' => 'M',
        ])->assertCreated();

        $this->getJson('/api/patients')
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.name', 'Test Pasien');
    }

    public function test_order_creates_worklist_item(): void
    {
        $patient = Patient::create([
            'patient_id' => 'MRN-X2', 'name' => 'Pasien 2', 'sex' => 'F',
        ]);

        $this->postJson('/api/orders', [
            'order_no' => 'ORD-T1', 'patient_id' => $patient->id,
            'modality' => 'DX', 'scheduled_aet' => 'ORTHANC',
        ])->assertCreated()->assertJsonPath('worklist_item.scheduled_aet', 'ORTHANC');

        $this->assertDatabaseCount('worklist_items', 1);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_worklist_shows_order_and_patient(): void
    {
        $patient = Patient::create(['patient_id' => 'MRN-X3', 'name' => 'Pasien 3']);
        $order = Order::create([
            'order_no' => 'ORD-T2', 'patient_id' => $patient->id, 'modality' => 'CT',
        ]);
        WorklistItem::create(['order_id' => $order->id, 'scheduled_aet' => 'CT-1']);

        $this->getJson('/api/worklist')
            ->assertOk()
            ->assertJsonPath('0.order.patient.name', 'Pasien 3');
    }

    public function test_report_store_and_filter_by_order(): void
    {
        $patient = Patient::create(['patient_id' => 'MRN-X4', 'name' => 'Pasien 4']);
        $order = Order::create([
            'order_no' => 'ORD-T3', 'patient_id' => $patient->id, 'modality' => 'DX',
        ]);

        $this->postJson('/api/reports', [
            'order_id' => $order->id,
            'radiologist' => 'dr. Rizky',
            'findings' => 'Cor pulmonale tidak tampak.',
            'impression' => 'Normal.',
            'status' => 'final',
        ])->assertCreated()->assertJsonPath('radiologist', 'dr. Rizky');

        $this->getJson("/api/reports?order_id={$order->id}")
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.order.patient.name', 'Pasien 4');
    }
}
