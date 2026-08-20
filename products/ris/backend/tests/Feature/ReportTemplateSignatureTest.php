<?php

use App\Models\Order;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\Report;
use App\Models\User;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOrderWithProcedure(string $no, string $status = 'in_progress'): Order
{
    $patient = Patient::create(['patient_id' => "MRN-$no", 'name' => "Pasien $no", 'sex' => 'M']);
    $proc = Procedure::create([
        'code' => "PX-$no", 'name' => "Pemeriksaan $no",
        'modality' => 'DX',
        'report_template' => "Temuan default $no\n---\nKesan default $no",
    ]);
    return Order::create([
        'order_no' => "ORD-$no", 'patient_id' => $patient->id,
        'procedure_id' => $proc->id, 'modality' => 'DX', 'status' => $status,
    ]);
}

test('laporan baru pakai template prosedur order', function () {
    $order = makeOrderWithProcedure('T1');
    $user = User::factory()->create();
    $this->be($user);

    $component = Volt::test('reports.index', ['user' => $user])
        
        ->set('order_id', $order->id)
        ->call('applyTemplate');

    $component->assertSet('findings', "Temuan default T1")
        ->assertSet('impression', "Kesan default T1");
});

test('template hanya untuk order terpilih', function () {
    $order = makeOrderWithProcedure('T2');
    $user = User::factory()->create();
    $this->be($user);

    Volt::test('reports.index', ['user' => $user])
        
        ->call('applyTemplate')
        ->assertSet('flash', 'Pilih order terlebih dahulu.');

    $order->procedure->update(['report_template' => null]);
    Volt::test('reports.index', ['user' => $user])
        
        ->set('order_id', $order->id)
        ->call('applyTemplate')
        ->assertSet('flash', 'Tidak ada templat untuk prosedur order ini.');
});

test('finalisasi laporan menyimpan tanda tangan', function () {
    $order = makeOrderWithProcedure('T3', 'completed');
    $report = Report::create([
        'order_id' => $order->id,
        'findings' => 'Normal', 'impression' => 'Baik', 'status' => 'draft',
    ]);
    $user = User::factory()->create(["name" => "Dr Radiolog"]);
    $this->be($user);

    Volt::test('reports.index', ['user' => $user])
        
        ->call('finalize', $report->id);

    $this->assertDatabaseHas('reports', [
        'id' => $report->id,
        'status' => 'final',
        'signed_by' => 'Dr Radiolog',
    ]);
    $this->assertNotNull($report->fresh()->signed_at);
});

test('laporan final tidak bisa ditandatangani ulang', function () {
    $order = makeOrderWithProcedure('T4', 'completed');
    $report = Report::create([
        'order_id' => $order->id, 'status' => 'final',
        'signed_by' => 'Dr Lama', 'signed_at' => now()->subDay(),
    ]);
    $user = User::factory()->create(["name" => "Dr Baru"]);
    $this->be($user);

    Volt::test('reports.index', ['user' => $user])
        
        ->call('finalize', $report->id);

    $this->assertDatabaseHas('reports', ['id' => $report->id, 'signed_by' => 'Dr Lama']);
});