<?php

use App\Models\Order;
use App\Models\Patient;
use App\Models\Report;
use App\Models\WorklistItem;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('dashboard');
middleware(['auth', 'verified']);

new class extends Component {
    public int $patients;
    public int $openOrders;
    public int $worklist;
    public int $finalReports;
    public array $recentOrders = [];

    public function mount(): void
    {
        $this->patients = Patient::count();
        $this->openOrders = Order::whereIn('status', ['scheduled', 'in_progress'])->count();
        $this->worklist = WorklistItem::where('status', 'pending')->count();
        $this->finalReports = Report::where('status', 'final')->count();
        $this->recentOrders = Order::with('patient', 'worklistItem')->latest()->limit(5)->get()->toArray();
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Radiology</h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Operasi klinis hari ini</p>
            </div>
        </div>
    </x-slot>

    @volt('ris-dashboard')
        <div class="space-y-6 pb-10">
            {{-- Statistik — split grid tanpa kartu kotak (border/negative space) --}}
            <section class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-zinc-200/70 bg-zinc-200/70 md:grid-cols-4 dark:border-zinc-200/10 dark:bg-zinc-200/10">
                @php
                    $stats = [
                        ['label' => 'Pasien', 'value' => $patients, 'hint' => 'terdaftar', 'mono' => false],
                        ['label' => 'Order terbuka', 'value' => $openOrders, 'hint' => 'scheduled + in_progress', 'mono' => false],
                        ['label' => 'Worklist', 'value' => $worklist, 'hint' => 'menunggu modality', 'mono' => false],
                        ['label' => 'Laporan final', 'value' => $finalReports, 'hint' => 'selesai dibaca', 'mono' => false],
                    ];
                @endphp
                @foreach ($stats as $s)
                    <div class="group bg-white p-6 dark:bg-zinc-900">
                        <p class="text-xs font-medium uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ $s['label'] }}</p>
                        <p class="mt-2 font-mono text-4xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100 group-hover:text-emerald-600 dark:group-hover:text-emerald-400">{{ $s['value'] }}</p>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ $s['hint'] }}</p>
                    </div>
                @endforeach
            </section>

            {{-- Order terbaru --}}
            <section class="grid gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="flex items-end justify-between">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Order terbaru</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Permintaan pemeriksaan dari klinisi</p>
                        </div>
                        <a href="#" class="text-sm font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400">Lihat semua →</a>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-zinc-200/70 dark:border-zinc-200/10">
                        <table class="w-full text-left text-sm">
                            <thead>
                                <tr class="border-b border-zinc-200/70 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-900/60 dark:text-zinc-500">
                                    <th class="px-5 py-3 font-medium">Order</th>
                                    <th class="hidden px-5 py-3 font-medium sm:table-cell">Pasien</th>
                                    <th class="px-5 py-3 font-medium">Modality</th>
                                    <th class="px-5 py-3 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-200/10">
                                @forelse ($recentOrders as $o)
                                    <tr class="bg-white transition-colors hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-900/60">
                                        <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $o['order_no'] }}</td>
                                        <td class="hidden px-5 py-4 text-zinc-800 sm:table-cell dark:text-zinc-200">{{ $o['patient']['name'] ?? '—' }}</td>
                                        <td class="px-5 py-4"><span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $o['modality'] }}</span></td>
                                        <td class="px-5 py-4">
                                            @php $badge = match ($o['status']) { 'completed' => 'text-emerald-700 bg-emerald-50 dark:text-emerald-300 dark:bg-emerald-500/10', 'in_progress' => 'text-amber-700 bg-amber-50 dark:text-amber-300 dark:bg-amber-500/10', 'scheduled' => 'text-zinc-600 bg-zinc-100 dark:text-zinc-300 dark:bg-zinc-500/10', default => 'text-zinc-500 bg-zinc-100 dark:text-zinc-400 dark:bg-zinc-500/10' }; @endphp
                                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                                                @if ($o['status'] === 'in_progress')
                                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"></span>
                                                @endif
                                                {{ $o['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                            Belum ada order.<br><a href="#" class="text-emerald-600 dark:text-emerald-400">Buat order pertama →</a>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Panel samping: worklist hari ini --}}
                <aside>
                    <div class="flex items-end justify-between">
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Worklist</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Jadwal modality (sumber MWL)</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div class="rounded-xl border border-zinc-200/70 bg-white p-4 dark:border-zinc-200/10 dark:bg-zinc-900">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-wider text-zinc-400">Slot pending</span>
                                <span class="font-mono text-xl font-semibold text-zinc-900 dark:text-zinc-100">{{ $worklist }}</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">Modality akan ambil via C-FIND</p>
                        </div>

                        <div class="rounded-xl border border-zinc-200/70 bg-white p-4 dark:border-zinc-200/10 dark:bg-zinc-900">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-wider text-zinc-400">AET aktif</span>
                                <span class="font-mono text-sm text-emerald-600 dark:text-emerald-400">ORTHANC · RIS</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    @endvolt
</x-layouts.app>