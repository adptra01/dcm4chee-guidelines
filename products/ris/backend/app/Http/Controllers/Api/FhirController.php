<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Report;
use Illuminate\Http\Request;

/**
 * FHIR R4 — expose data RIS sebagai resource standar (SATUSEHAT-ready).
 * Mapping manual tanpa library: resource yang dipakai terbatas.
 */
class FhirController extends Controller
{
    private const CT = 'application/fhir+json';

    // ------------------------------------------------------------- Patient
    public function patientShow(int $id)
    {
        $p = Patient::findOrFail($id);
        return response($this->patient($p), 200)->header('Content-Type', self::CT);
    }

    public function patientSearch(Request $request)
    {
        // search: identifier=MRN001 | name=Bud
        $q = Patient::query();
        if ($identifier = $request->query('identifier')) {
            $q->where('patient_id', ltrim($identifier, '|'));
        }
        if ($name = $request->query('name')) {
            $q->where('name', 'like', "%{$name}%");
        }
        $entry = $q->get()->map(fn ($p) => $this->entry($this->patient($p), 'Patient'))->all();
        return response($this->bundle($entry), 200)->header('Content-Type', self::CT);
    }

    private function patient(Patient $p): array
    {
        return [
            'resourceType' => 'Patient',
            'id' => (string) $p->id,
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/nik',
                'value' => $p->patient_id,
            ]],
            'name' => [['text' => $p->name, 'family' => $p->name]],
            'gender' => match ($p->sex) { 'M' => 'male', 'F' => 'female', default => 'unknown' },
            'birthDate' => $p->birthdate?->format('Y-m-d'),
        ];
    }

    // ------------------------------------------------------ ServiceRequest
    public function orderShow(int $id)
    {
        $o = Order::with('patient')->findOrFail($id);
        return response($this->serviceRequest($o), 200)->header('Content-Type', self::CT);
    }

    private function serviceRequest(Order $o): array
    {
        return [
            'resourceType' => 'ServiceRequest',
            'id' => (string) $o->id,
            'identifier' => [['system' => 'urn:orp:order', 'value' => $o->order_no]],
            'status' => match ($o->status) {
                'scheduled' => 'active', 'completed' => 'completed',
                'cancelled' => 'revoked', default => 'draft',
            },
            'intent' => 'order',
            'subject' => ['reference' => "Patient/{$o->patient_id}"],
            'code' => ['text' => $o->modality, 'coding' => [[
                'system' => 'urn:orp:modality', 'code' => $o->modality,
            ]]],
            'authoredOn' => $o->requested_at?->format('c'),
        ];
    }

    // --------------------------------------------------- DiagnosticReport
    public function reportShow(int $id)
    {
        $r = Report::with('order.patient')->findOrFail($id);
        return response($this->diagnosticReport($r), 200)->header('Content-Type', self::CT);
    }

    private function diagnosticReport(Report $r): array
    {
        return [
            'resourceType' => 'DiagnosticReport',
            'id' => (string) $r->id,
            'status' => match ($r->status) {
                'final' => 'final', 'amended' => 'amended', default => 'preliminary',
            },
            'basedOn' => [['reference' => "ServiceRequest/{$r->order_id}"]],
            'subject' => ['reference' => "Patient/{$r->order->patient_id}"],
            'conclusion' => $r->impression,
            'result' => [],
        ];
    }

    // ------------------------------------------------------------- helpers
    private function entry(array $resource, string $type): array
    {
        return [
            'fullUrl' => url("/api/fhir/{$type}/{$resource['id']}"),
            'resource' => $resource,
        ];
    }

    private function bundle(array $entry): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => 'searchset',
            'total' => count($entry),
            'entry' => $entry,
        ];
    }
}
