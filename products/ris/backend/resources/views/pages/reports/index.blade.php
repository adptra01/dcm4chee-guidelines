<?php

use App\Models\Order;
use App\Models\Report;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('reports.index');
middleware(['auth', 'verified']);

new class extends Component {
    public array $orders = [];
    public array $reports = [];

    public ?int $order_id = null;
    public string $findings = '';
    public string $impression = '';
    public ?string $flash = null;

    public function mount(): void
    {
        $this->orders = Order::with('patient', 'procedure')
            ->whereIn('status', ['in_progress', 'completed'])
            ->orderBy('order_no')->get()->toArray();
        $this->load();
    }

    public function load(): void
    {
        $this->reports = Report::with('order.patient', 'order.procedure')
            ->latest()->get()->toArray();
    }

    public function applyTemplate(): void
    {
        if (! $this->order_id) {
            $this->flash = 'Pilih order terlebih dahulu.';
            return;
        }
        $template = Order::with('procedure')->find($this->order_id)?->procedure?->report_template;
        if (! $template) {
            $this->flash = 'Tidak ada templat untuk prosedur order ini.';
            return;
        }
        [$findings, $impression] = array_pad(explode("\n---\n", $template, 2), 2, '');
        $this->findings = $findings;
        $this->impression = $impression;
        $this->flash = 'Templat diterapkan — sesuaikan sebelum simpan.';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'findings' => ['nullable', 'string'],
            'impression' => ['nullable', 'string'],
        ]);

        $report = Report::create([
            'order_id' => $validated['order_id'],
            'radiologist' => auth()->user()->name,
            'findings' => $validated['findings'],
            'impression' => $validated['impression'],
            'status' => 'draft',
        ]);

        $this->reset('order_id', 'findings', 'impression');
        $this->flash = "Laporan #{$report->id} dibuat (draft).";
        $this->load();
    }

    public function finalize(int $id): void
    {
        $report = Report::findOrFail($id);
        if ($report->status === 'final') {
            return;
        }
        $report->update([
            'status' => 'final',
            'signed_by' => auth()->user()->name,
            'signed_at' => now(),
        ]);
        $this->flash = 'Laporan difinalisasi — ditandatangani oleh '.auth()->user()->name.'.';
        $this->load();
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Laporan</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Pelaporan radiologi per order</p>
        </div>
    </x-slot>

    @volt('reports.index')
        <div class="grid gap-6 pb-10 lg:grid-cols-3">
            {{-- Daftar laporan --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-zinc-200/70 dark:border-zinc-200/10">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200/70 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-900/60 dark:text-zinc-500">
                                <th class="px-5 py-3 font-medium">Order</th>
                                <th class="px-5 py-3 font-medium">Pasien</th>
                                <th class="hidden px-5 py-3 font-medium md:table-cell">Impresi</th>
                                <th class="px-5 py-3 font-medium">Status</th>
                                <th class="hidden px-5 py-3 font-medium md:table-cell">Tanda tangan</th>
                                <th class="px-5 py-3 font-medium">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-200/10">
                            @forelse ($reports as $r)
                                <tr class="bg-white transition-colors hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-900/60">
                                    <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $r['order']['order_no'] ?? '—' }}</td>
                                    <td class="px-5 py-4 text-zinc-800 dark:text-zinc-200">{{ $r['order']['patient']['name'] ?? '—' }}</td>
                                    <td class="hidden px-5 py-4 text-sm text-zinc-500 md:table-cell dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($r['impression'] ?? '—', 48) }}</td>
                                    <td class="px-5 py-4">
                                        @php $badge = $r['status'] === 'final' ? 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-500/10' : 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-500/10'; @endphp
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ $r['status'] }}</span>
                                    </td>
                                    <td class="hidden px-5 py-4 text-xs text-zinc-500 md:table-cell dark:text-zinc-400">
                                        {{ $r['signed_by'] ?? '—' }}
                                        @if ($r['signed_at'])
                                            <br><span class="font-mono">{{ \Carbon\Carbon::parse($r['signed_at'])->format('d M Y H:i') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        @if ($r['status'] !== 'final')
                                            <button wire:click="finalize({{ $r['id'] }})" wire:loading.attr="disabled" wire:target="finalize({{ $r['id'] }})"
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                                                <svg wire:loading wire:target="finalize({{ $r['id'] }})" class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" class="opacity-75"/></svg>
                                                <span wire:loading.remove wire:target="finalize({{ $r['id'] }})">Finalisasi</span>
                                                <span wire:loading wire:target="finalize({{ $r['id'] }})">...</span>
                                            </button>
                                        @else
                                            <span class="text-xs text-zinc-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                        Belum ada laporan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Form laporan baru --}}
            <aside>
                <div class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Laporan baru</h2>

                    @if ($flash)
                        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $flash }}</p>
                    @endif

                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label for="order_id" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Order</label>
                            <div class="mt-1 flex gap-2">
                                <select id="order_id" wire:model="order_id"
                                        class="w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                                    <option value="">— pilih order —</option>
                                    @foreach ($orders as $o)
                                        <option value="{{ $o['id'] }}">{{ $o['order_no'] }} — {{ $o['patient']['name'] ?? '?' }}</option>
                                    @endforeach
                                </select>
                                <button type="button" wire:click="applyTemplate"
                                        class="shrink-0 rounded-lg border border-zinc-200/70 px-3 py-2 text-sm font-medium text-zinc-600 transition-colors hover:border-emerald-300 hover:text-emerald-600 dark:border-zinc-200/10 dark:text-zinc-300 dark:hover:text-emerald-300">
                                    Isi template
                                </button>
                            </div>
                            @error('order_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="findings" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Temuan (findings)</label>
                            <textarea id="findings" wire:model="findings" rows="4" placeholder="Deskripsi temuan radiologi..."
                                      class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100"></textarea>
                        </div>
                        <div>
                            <label for="impression" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Kesan (impression)</label>
                            <textarea id="impression" wire:model="impression" rows="2" placeholder="Kesan / kesimpulan"
                                      class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100"></textarea>
                        </div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 disabled:cursor-not-allowed disabled:opacity-50">
                            <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" class="opacity-75"/></svg>
                            <span wire:loading.remove wire:target="save">Simpan draft</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    @endvolt
</x-layouts.app>