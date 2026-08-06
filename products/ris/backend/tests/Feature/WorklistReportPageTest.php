<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Patient;
use App\Models\Report;
use App\Models\User;
use App\Models\WorklistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorklistReportPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(string $no, string $status = 'scheduled'): Order
    {
        $patient = Patient::create(['patient_id' => "MRN-$no", 'name' => "Pasien $no", 'sex' => 'M']);
        return Order::create([
            'order_no' => "ORD-$no", 'patient_id' => $patient->id,
            'modality' => 'DX', 'status' => $status,
        ]);
    }

    public function test_worklist_page_renders_and_shows_item(): void
    {
        $order = $this->makeOrder('W1');
        WorklistItem::create(['order_id' => $order->id, 'scheduled_aet' => 'ORTHANC', 'scheduled_at' => now()]);

        $this->actingAs(User::factory()->create())
            ->get('/worklist')
            ->assertOk()
            ->assertSee('ORD-W1')
            ->assertSee('Pasien W1');
    }

    public function test_reports_page_renders_with_report(): void
    {
        $order = $this->makeOrder('R1', 'completed');
        Report::create(['order_id' => $order->id, 'findings' => 'Normal', 'impression' => 'Tidak ada kelainan', 'status' => 'final']);

        $this->actingAs(User::factory()->create())
            ->get('/reports')
            ->assertOk()
            ->assertSee('ORD-R1')
            ->assertSee('Tidak ada kelainan');
    }
}
