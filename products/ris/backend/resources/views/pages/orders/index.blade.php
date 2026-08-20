<?php

use App\Models\Doctor;
use App\Models\Order;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\WorklistItem;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('orders.index');
middleware(['auth', 'verified']);

new class extends Component {
    public array $orders = [];
    public array $patients = [];
    public array $doctors = [];
    public array $procedures = [];

    public ?int $patient_id = null;
    public ?int $doctor_id = null;
    public ?int $procedure_id = null;
    public string $modality = '';
    public ?string $flash = null;

    public function mount(): void
    {
        $this->patients = Patient::orderBy('name')->get()->toArray();
        $this->doctors = Doctor::orderBy('name')->get()->toArray();
        $this->procedures = Procedure::orderBy('name')->get()->toArray();
        $this->load();
    }

    public function load(): void
    {
        $this->orders = Order::with('patient', 'doctor', 'procedure', 'worklistItem')
            ->latest()->limit(10)->get()->toArray();
    }

    public function updatedProcedureId(): void
    {
        // Isi modality otomatis dari prosedur terpilih
        $proc = collect($this->procedures)->firstWhere('id', $this->procedure_id);
        if ($proc) {
            $this->modality = $proc['modality'] ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'procedure_id' => ['nullable', 'exists:procedures,id'],
            'modality' => ['required', 'string', 'max:8'],
        ]);

        $order = Order::create([
            'order_no' => 'ORD-' . strtoupper(uniqid()),
            'patient_id' => $validated['patient_id'],
            'doctor_id' => $validated['doctor_id'],
            'procedure_id' => $validated['procedure_id'],
            'modality' => $validated['modality'],
            'requested_at' => now(),
        ]);

        // Worklist slot (MWL source) — konsisten dengan API store
        WorklistItem::create([
            'order_id' => $order->id,
            'scheduled_aet' => 'RIS',
            'scheduled_at' => now(),
        ]);

        $this->reset('patient_id', 'doctor_id', 'procedure_id', 'modality');
        $this->flash = "Order {$order->order_no} dibuat. Slot worklist tersedia.";
        $this->load();
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Order</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Permintaan pemeriksaan radiologi</p>
        </div>
    </x-slot>

    @volt('orders-index')
        <div class="grid gap-6 pb-10 lg:grid-cols-3">
            {{-- Daftar order --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-zinc-200/70 dark:border-zinc-200/10">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200/70 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-900/60 dark:text-zinc-500">
                                <th class="px-5 py-3 font-medium">Order</th>
                                <th class="px-5 py-3 font-medium">Pasien</th>
                                <th class="hidden px-5 py-3 font-medium md:table-cell">Dokter</th>
                                <th class="px-5 py-3 font-medium">Prosedur</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="hidden px-5 py-3 font-medium sm:table-cell">Study</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-200/10">
                            @forelse ($orders as $o)
                                <tr class="bg-white transition-colors hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-900/60">
                                    <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $o['order_no'] }}</td>
                                    <td class="px-5 py-4 text-zinc-800 dark:text-zinc-200">{{ $o['patient']['name'] ?? '—' }}</td>
                                    <td class="hidden px-5 py-4 text-zinc-500 md:table-cell dark:text-zinc-400">{{ $o['doctor']['name'] ?? '—' }}</td>
                                    <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $o['procedure']['name'] ?? '—' }}</td>
                                    <td class="px-5 py-4">
                                        @php $badge = match ($o['status']) { 'completed' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-500/10', 'in_progress' => 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-500/10', 'scheduled' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-300 dark:bg-zinc-500/10', default => 'text-zinc-500 bg-zinc-100 dark:text-zinc-400 dark:bg-zinc-500/10' }; @endphp
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                                            {{ $o['status'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        @if (! empty($o['study_instance_uid']))
                                            <a href="http://localhost:8042/ohif/viewer?StudyInstanceUIDs={{ $o['study_instance_uid'] }}" target="_blank"
                                               class="inline-flex items-center gap-1 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-emerald-500">
                                                Buka di viewer →
                                            </a>
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                        Belum ada order.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Form order baru --}}
            <aside>
                <div class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Order baru</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Order otomatis membuat slot worklist (sumber MWL).</p>

                    @if ($flash)
                        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $flash }}</p>
                    @endif

                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label for="patient_id" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Pasien</label>
                            <select id="patient_id" wire:model="patient_id"
                                    class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                                <option value="">— pilih pasien —</option>
                                @foreach ($patients as $p)
                                    <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['patient_id'] }})</option>
                                @endforeach
                            </select>
                            @error('patient_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="doctor_id" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Dokter pengirim</label>
                            <select id="doctor_id" wire:model="doctor_id"
                                    class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                                <option value="">— pilih dokter —</option>
                                @foreach ($doctors as $d)
                                    <option value="{{ $d['id'] }}">{{ $d['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="procedure_id" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Prosedur</label>
                            <select id="procedure_id" wire:model.change="procedure_id"
                                    class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                                <option value="">— pilih prosedur —</option>
                                @foreach ($procedures as $p)
                                    <option value="{{ $p['id'] }}">{{ $p['code'] }} — {{ $p['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="modality" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Modalitas</label>
                            <input id="modality" wire:model="modality" type="text" readonly placeholder="terisi otomatis dari prosedur"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-800/40 dark:text-zinc-100">
                            @error('modality') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 disabled:cursor-not-allowed disabled:opacity-50">
                            <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" class="opacity-75"/></svg>
                            <span wire:loading.remove wire:target="save">Buat order</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    @endvolt
</x-layouts.app>