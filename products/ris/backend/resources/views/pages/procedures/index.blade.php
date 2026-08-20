<?php

use App\Models\Procedure;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('procedures.index');
middleware(['auth', 'verified']);

new class extends Component {
    public array $procedures = [];

    public string $code = '';
    public string $name = '';
    public string $body_part = '';
    public string $modality = '';
    public string $report_template = '';
    public ?string $flash = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->procedures = Procedure::orderBy('name')->get()->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'code' => ['required', 'string', 'max:16', 'unique:procedures,code'],
            'name' => ['required', 'string', 'max:255'],
            'body_part' => ['nullable', 'string', 'max:64'],
            'modality' => ['nullable', 'string', 'max:8'],
            'report_template' => ['nullable', 'string'],
        ]);

        Procedure::create($validated);
        $this->reset('code', 'name', 'body_part', 'modality', 'report_template');
        $this->flash = 'Prosedur tersimpan.';
        $this->load();
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Prosedur</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Master pemeriksaan radiologi</p>
        </div>
    </x-slot>

    @volt('procedures-index')
        <div class="grid gap-6 pb-10 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-zinc-200/70 dark:border-zinc-200/10">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200/70 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-900/60 dark:text-zinc-500">
                                <th class="px-5 py-3 font-medium">Kode</th>
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Bagian tubuh</th>
                                <th class="px-5 py-3 font-medium">Modalitas</th>
                                <th class="hidden px-5 py-3 font-medium lg:table-cell">Templat laporan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-200/10">
                            @forelse ($procedures as $p)
                                <tr class="bg-white transition-colors hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-900/60">
                                    <td class="px-5 py-4 font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ $p['code'] }}</td>
                                    <td class="px-5 py-4 font-medium text-zinc-800 dark:text-zinc-200">{{ $p['name'] }}</td>
                                    <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $p['body_part'] ?? '—' }}</td>
                                    <td class="px-5 py-4"><span class="font-mono text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $p['modality'] ?? '—' }}</span></td>
                                    <td class="hidden px-5 py-4 text-xs text-zinc-500 lg:table-cell dark:text-zinc-400">{{ \Illuminate\Support\Str::limit(str_replace("\n---\n", ' — ', $p['report_template'] ?? ''), 48) ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                        Belum ada prosedur.<br>Tambah lewat form di samping.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <div class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Tambah prosedur</h2>

                    @if ($flash)
                        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $flash }}</p>
                    @endif

                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label for="code" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Kode</label>
                            <input id="code" wire:model="code" type="text" placeholder="DX-CHEST"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                            @error('code') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="name" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Nama</label>
                            <input id="name" wire:model="name" type="text" placeholder="Foto Thorax PA"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                            @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="body_part" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Bagian tubuh</label>
                            <input id="body_part" wire:model="body_part" type="text" placeholder="Thorax"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                        </div>
                        <div>
                            <label for="modality" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Modalitas</label>
                            <select id="modality" wire:model="modality"
                                    class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                                <option value="">— pilih —</option>
                                @foreach (['DX' => 'Radiografi (DX)', 'CT' => 'CT Scan', 'MR' => 'MRI', 'US' => 'Ultrasonografi', 'CR' => 'Computed Radiography'] as $val => $label)
                                    <option value="{{ $val }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="report_template" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Templat laporan</label>
                            <textarea id="report_template" wire:model="report_template" rows="4" placeholder="Temuan default...&#10;---&#10;Kesan default..."
                                      class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100"></textarea>
                            <p class="mt-1 text-xs text-zinc-400">Pisahkan temuan &amp; kesan dengan baris <code class="font-mono">---</code> (tidak wajib).</p>
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            Simpan prosedur
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    @endvolt
</x-layouts.app>