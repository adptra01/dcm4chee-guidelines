<?php

use App\Models\WorklistItem;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('worklist.index');
middleware(['auth', 'verified']);

new class extends Component {
    public array $items = [];
    public string $filter = 'all';
    public string $loadedAt = '';

    public function mount(): void
    {
        $this->load();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->load();
    }

    public function load(): void
    {
        $this->loadedAt = now()->format('H:i:s');
        $query = WorklistItem::with('order.patient')
            ->orderBy('scheduled_at');

        if ($this->filter !== 'all') {
            $query->where('status', $this->filter);
        }

        $this->items = $query->get()->toArray();
    }

    public function updateStatus(int $id, string $status): void
    {
        if (! in_array($status, ['pending', 'arrived', 'started', 'completed'])) {
            return;
        }
        WorklistItem::whereKey($id)->update(['status' => $status]);
        $this->load();
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Worklist</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Jadwal pemeriksaan per modality (sumber MWL)</p>
        </div>
    </x-slot>

    @volt('worklist-index')
        <div wire:poll.30s="load" class="space-y-6 pb-10">
            {{-- Indikator auto-refresh + status --}}
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap gap-2">
                    @foreach (['all' => 'Semua', 'pending' => 'Pending', 'arrived' => 'Sudah datang', 'started' => 'Sedang jalan', 'completed' => 'Selesai'] as $val => $label)
                        <button wire:click="setFilter('{{ $val }}')"
                                class="rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors {{ $filter === $val ? 'bg-emerald-600 text-white' : 'bg-white text-zinc-600 hover:bg-zinc-100 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800' }}">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
                <span class="ml-auto inline-flex items-center gap-1.5 text-xs text-zinc-400 dark:text-zinc-500" wire:loading.delay>
                    <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25"/><path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="4" class="opacity-75"/></svg>
                    Memuat...
                </span>
                <span class="text-xs text-zinc-400 dark:text-zinc-500" wire:loading.remove>Auto-refresh 30 dtk · terakhir {{ $loadedAt ?: '—' }}</span>
            </div>

            <div class="relative overflow-hidden rounded-2xl border border-zinc-200/70 dark:border-zinc-200/10" wire:loading.class="opacity-60">
                <div wire:loading class="absolute inset-x-0 top-0 h-0.5 overflow-hidden">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-emerald-500"></div>
                </div>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200/70 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-900/60 dark:text-zinc-500">
                            <th class="px-5 py-3 font-medium">Waktu</th>
                            <th class="px-5 py-3 font-medium">Pasien</th>
                            <th class="px-5 py-3 font-medium">Order</th>
                            <th class="hidden px-5 py-3 font-medium sm:table-cell">AET</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-200/10">
                        @forelse ($items as $i)
                            <tr class="bg-white transition-colors hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-900/60">
                                <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $i['scheduled_at'] ? \Illuminate\Support\Carbon::parse($i['scheduled_at'])->format('d M H:i') : '—' }}</td>
                                <td class="px-5 py-4 font-medium text-zinc-800 dark:text-zinc-200">{{ $i['order']['patient']['name'] ?? '—' }}</td>
                                <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $i['order']['order_no'] ?? '—' }}</td>
                                <td class="hidden px-5 py-4 font-mono text-xs text-zinc-500 sm:table-cell dark:text-zinc-400">{{ $i['scheduled_aet'] }}</td>
                                <td class="px-5 py-4">
                                    @php $badge = match ($i['status']) { 'completed' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-500/10', 'started' => 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-500/10', 'arrived' => 'text-sky-700 bg-sky-50 dark:text-sky-300 dark:bg-sky-500/10', default => 'text-zinc-600 bg-zinc-100 dark:text-zinc-300 dark:bg-zinc-500/10' }; @endphp
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                                        {{ $i['status'] }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach (['arrived', 'started', 'completed'] as $next)
                                            @if ($i['status'] !== $next)
                                                <button wire:click="updateStatus({{ $i['id'] }}, '{{ $next }}')"
                                                        class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-600 transition-colors hover:bg-emerald-50 hover:text-emerald-700 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-emerald-500/10 dark:hover:text-emerald-300">
                                                    {{ $next }}
                                                </button>
                                            @endif
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                    Tidak ada item worklist untuk filter ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endvolt
</x-layouts.app>