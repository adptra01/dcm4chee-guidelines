<?php

use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('developer.index');
middleware(['auth', 'verified']);

new class extends Component {
    public bool $copied = false;
    public string $copiedId = '';

    /**
     * Endpoint nyata dari routes/api.php — single source of truth.
     */
    public function endpoints(): array
    {
        $base = url('/api');

        return [
            [
                'id' => 'patients-index',
                'method' => 'GET',
                'path' => '/api/patients',
                'desc' => 'Daftar semua pasien terdaftar.',
                'curl' => "curl {$base}/patients",
                'response' => '[{"id":1,"patient_id":"P001","name":"Pasien Contoh","sex":"F","birthdate":"1990-01-01"}]',
            ],
            [
                'id' => 'patients-store',
                'method' => 'POST',
                'path' => '/api/patients',
                'desc' => 'Registrasi pasien baru. patient_id unik, NIK 16 digit opsional.',
                'curl' => "curl -X POST {$base}/patients \\\n  -H 'Content-Type: application/json' \\\n  -H 'Accept: application/json' \\\n  -d '{\"patient_id\":\"P002\",\"name\":\"Budi Santoso\",\"sex\":\"M\",\"birthdate\":\"1985-05-10\",\"nik\":\"3201010105850001\"}'",
                'response' => '{"id":2,"patient_id":"P002","name":"Budi Santoso","sex":"M","birthdate":"1985-05-10","nik":"3201010105850001"}',
            ],
            [
                'id' => 'orders-index',
                'method' => 'GET',
                'path' => '/api/orders',
                'desc' => 'Daftar order pemeriksaan.',
                'curl' => "curl {$base}/orders",
                'response' => '[{"id":1,"patient_id":"P001","procedure_code":"CR-CH","status":"scheduled","accession":"ACC-001"}]',
            ],
            [
                'id' => 'orders-store',
                'method' => 'POST',
                'path' => '/api/orders',
                'desc' => 'Buat order — otomatis membuat worklist item (MWL source).',
                'curl' => "curl -X POST {$base}/orders \\\n  -H 'Content-Type: application/json' \\\n  -H 'Accept: application/json' \\\n  -d '{\"patient_id\":\"P001\",\"procedure_code\":\"CR-CH\",\"accession\":\"ACC-002\",\"scheduled_at\":\"2026-08-21 09:00:00\"}'",
                'response' => '{"id":2,"patient_id":"P001","procedure_code":"CR-CH","status":"scheduled","accession":"ACC-002"}',
            ],
            [
                'id' => 'orders-status',
                'method' => 'PATCH',
                'path' => '/api/orders/{id}/status',
                'desc' => 'Update status order: scheduled, in_progress, completed, cancelled. Bisa bawa study_instance_uid (dari MPPS).',
                'curl' => "curl -X PATCH {$base}/orders/1/status \\\n  -H 'Content-Type: application/json' \\\n  -H 'Accept: application/json' \\\n  -d '{\"status\":\"completed\",\"study_instance_uid\":\"1.2.840.113619.2.1.1.1\"}'",
                'response' => '{"id":1,"status":"completed","study_instance_uid":"1.2.840.113619.2.1.1.1"}',
            ],
            [
                'id' => 'worklist',
                'method' => 'GET',
                'path' => '/api/worklist',
                'desc' => 'Worklist item — sumber MWL untuk modalitas (via Integration :4243).',
                'curl' => "curl {$base}/worklist",
                'response' => '[{"id":1,"accession":"ACC-001","status":"pending","scheduled_aet":"ORTHANC"}]',
            ],
            [
                'id' => 'reports-store',
                'method' => 'POST',
                'path' => '/api/reports',
                'desc' => 'Simpan laporan per order (findings + impression, draft/final). Finalisasi = tanda tangan.',
                'curl' => "curl -X POST {$base}/reports \\\n  -H 'Content-Type: application/json' \\\n  -H 'Accept: application/json' \\\n  -d '{\"order_id\":1,\"findings\":\"Cor normal.\",\"impression\":\"No acute disease.\",\"status\":\"draft\"}'",
                'response' => '{"id":1,"order_id":1,"status":"draft","signed_by":null,"signed_at":null}',
            ],
            [
                'id' => 'fhir-patient',
                'method' => 'GET',
                'path' => '/api/fhir/Patient',
                'desc' => 'FHIR R4 Patient search (SATUSEHAT-ready). Content-Type: application/fhir+json.',
                'curl' => "curl -H 'Accept: application/fhir+json' '{$base}/fhir/Patient?name=Contoh'",
                'response' => '{"resourceType":"Bundle","type":"searchset","entry":[{"resource":{"resourceType":"Patient","id":"1","name":[{"family":"Contoh"}]}}]}',
            ],
        ];
    }

    public function copy(string $id): void
    {
        $this->copied = true;
        $this->copiedId = $id;
        $this->dispatch('$refresh');
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Developer Portal</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Dokumentasi API RIS — endpoint nyata, contoh request, dan demo otentikasi</p>
        </div>
    </x-slot>

    @volt('ris-developer')
        <div class="space-y-8 pb-10">
            {{-- Intro + sandbox note --}}
            <section class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">REST API RIS</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    Semua endpoint di bawah hidup di instance dev ini — aman dicoba (sandbox lokal).
                    Base URL: <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-emerald-700 dark:bg-zinc-800 dark:text-emerald-400">{{ url('/api') }}</code>
                </p>
            </section>

            {{-- Auth demo (US-DEV-003) --}}
            <section class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Otentikasi — X-API-Key</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Endpoint eksternal (OMC, AI, Integration) memakai header <code class="font-mono text-xs">X-API-Key</code>.</p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900/50 dark:bg-emerald-950/30">
                        <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">Mode Dev (sandbox)</p>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Env <code class="font-mono text-xs">RIS_API_KEYS</code> kosong — semua request diterima tanpa header.</p>
                        <pre class="mt-3 overflow-x-auto rounded-lg bg-white p-3 font-mono text-xs text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">curl {{ url('/api/patients') }} -H 'Accept: application/json'
# 200 OK — semua data pasien</pre>
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30">
                        <p class="text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400">Mode Produksi</p>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">Env terisi — request tanpa key atau key salah ditolak:</p>
                        <pre class="mt-3 overflow-x-auto rounded-lg bg-white p-3 font-mono text-xs text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">curl {{ url('/api/patients') }} -H 'Accept: application/json'
# 401 {"message":"Invalid API key"}

curl {{ url('/api/patients') }} \
  -H 'Accept: application/json' \
  -H 'X-API-Key: <key-anda>'
# 200 OK</pre>
                    </div>
                </div>
            </section>

            {{-- Endpoint docs (US-DEV-001) --}}
            <section class="space-y-4">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Endpoint</h2>
                @foreach ($this->endpoints() as $ep)
                    <div class="overflow-hidden rounded-2xl border border-zinc-200/70 bg-white dark:border-zinc-200/10 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-center gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                            <span class="rounded px-2 py-0.5 font-mono text-xs font-semibold {{ ['GET' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-300', 'POST' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300', 'PATCH' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300'][$ep['method']] ?? 'bg-zinc-100 text-zinc-700' }}">{{ $ep['method'] }}</span>
                            <code class="font-mono text-sm text-zinc-900 dark:text-zinc-100">{{ $ep['path'] }}</code>
                            <span class="ml-auto text-sm text-zinc-400 dark:text-zinc-500">{{ $ep['desc'] }}</span>
                        </div>
                        <div class="grid gap-px bg-zinc-100 md:grid-cols-2 dark:bg-zinc-800">
                            <div class="bg-white p-4 dark:bg-zinc-900">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Request (curl)</p>
                                    <button wire:click="copy('{{ $ep['id'] }}')" onclick="navigator.clipboard.writeText(this.closest('div.bg-white, div.bg-zinc-900').querySelector('pre').textContent); this.textContent='Copied'" class="rounded-md border border-zinc-200 px-2 py-1 text-xs font-medium text-zinc-600 hover:border-emerald-300 hover:text-emerald-600 dark:border-zinc-700 dark:text-zinc-300">{{ $copiedId === $ep['id'] ? 'Copied' : 'Copy' }}</button>
                                </div>
                                <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-50 p-3 font-mono text-xs leading-relaxed text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">{{ $ep['curl'] }}</pre>
                            </div>
                            <div class="bg-white p-4 dark:bg-zinc-900">
                                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400">Response (JSON)</p>
                                <pre class="mt-2 overflow-x-auto rounded-lg bg-zinc-50 p-3 font-mono text-xs leading-relaxed text-zinc-700 dark:bg-zinc-950 dark:text-zinc-300">{{ $ep['response'] }}</pre>
                            </div>
                        </div>
                    </div>
                @endforeach
            </section>

            {{-- Integrasi lintas produk --}}
            <section class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Integrasi Lintas Produk</h2>
                <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                    <li><span class="font-medium text-zinc-900 dark:text-zinc-100">OMC</span> — modalitas query worklist via Integration MWL SCP (:4243), kirim MPPS N-SET setelah store (:4244) → status order RIS ter-update otomatis.</li>
                    <li><span class="font-medium text-zinc-900 dark:text-zinc-100">Integration Platform</span> — HL7 ADT (pasien baru), MORBIS SEP/klaim, FHIR R4 lintas sistem.</li>
                    <li><span class="font-medium text-zinc-900 dark:text-zinc-100">Viewer</span> — OHIF via Orthanc <code class="font-mono text-xs">{{ ':8042/ohif' }}</code>, tombol "Buka di viewer" muncul saat order punya StudyInstanceUID.</li>
                </ul>
            </section>
        </div>
    @endvolt
</x-layouts.app>
