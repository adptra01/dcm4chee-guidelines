<?php

use App\Models\Doctor;
use Livewire\Volt\Component;
use function Laravel\Folio\{middleware, name};

name('doctors.index');
middleware(['auth', 'verified']);

new class extends Component {
    public array $doctors = [];

    public string $name = '';
    public string $specialization = '';
    public string $phone = '';
    public string $nidn = '';
    public ?string $flash = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->doctors = Doctor::orderBy('name')->get()->toArray();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'specialization' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'nidn' => ['nullable', 'string', 'max:32'],
        ]);

        Doctor::create($validated);
        $this->reset('name', 'specialization', 'phone', 'nidn');
        $this->flash = 'Dokter tersimpan.';
        $this->load();
    }
};
?>

<x-layouts.app>
    <x-slot name="header">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Dokter</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Referrer / pengirim permintaan pemeriksaan</p>
        </div>
    </x-slot>

    @volt('doctors-index')
        <div class="grid gap-6 pb-10 lg:grid-cols-3">
            {{-- Daftar dokter --}}
            <div class="lg:col-span-2">
                <div class="overflow-hidden rounded-2xl border border-zinc-200/70 dark:border-zinc-200/10">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-zinc-200/70 bg-zinc-50 text-xs uppercase tracking-wider text-zinc-400 dark:border-zinc-200/10 dark:bg-zinc-900/60 dark:text-zinc-500">
                                <th class="px-5 py-3 font-medium">Nama</th>
                                <th class="px-5 py-3 font-medium">Spesialisasi</th>
                                <th class="hidden px-5 py-3 font-medium sm:table-cell">NIDN</th>
                                <th class="hidden px-5 py-3 font-medium md:table-cell">Telepon</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200/70 dark:divide-zinc-200/10">
                            @forelse ($doctors as $d)
                                <tr class="bg-white transition-colors hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-900/60">
                                    <td class="px-5 py-4 font-medium text-zinc-800 dark:text-zinc-200">{{ $d['name'] }}</td>
                                    <td class="px-5 py-4 text-zinc-500 dark:text-zinc-400">{{ $d['specialization'] ?? '—' }}</td>
                                    <td class="hidden px-5 py-4 font-mono text-xs text-zinc-500 sm:table-cell dark:text-zinc-400">{{ $d['nidn'] ?? '—' }}</td>
                                    <td class="hidden px-5 py-4 font-mono text-xs text-zinc-500 md:table-cell dark:text-zinc-400">{{ $d['phone'] ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-12 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                        Belum ada dokter.<br>Tambah lewat form di samping.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Form tambah dokter --}}
            <aside>
                <div class="rounded-2xl border border-zinc-200/70 bg-white p-6 dark:border-zinc-200/10 dark:bg-zinc-900">
                    <h2 class="text-lg font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">Tambah dokter</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Data pengirim order pemeriksaan.</p>

                    @if ($flash)
                        <p class="mt-3 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $flash }}</p>
                    @endif

                    <form wire:submit="save" class="mt-5 space-y-4">
                        <div>
                            <label for="name" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Nama</label>
                            <input id="name" wire:model="name" type="text" placeholder="dr. Andi Pratama"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                            @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="specialization" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Spesialisasi</label>
                            <input id="specialization" wire:model="specialization" type="text" placeholder="Radiologi"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                        </div>
                        <div>
                            <label for="phone" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Telepon</label>
                            <input id="phone" wire:model="phone" type="text" placeholder="0812..."
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                        </div>
                        <div>
                            <label for="nidn" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">NIDN / SIP</label>
                            <input id="nidn" wire:model="nidn" type="text" placeholder="—"
                                   class="mt-1 w-full rounded-lg border border-zinc-200/70 bg-white px-3 py-2 text-sm text-zinc-900 placeholder-zinc-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 dark:border-zinc-200/10 dark:bg-zinc-800/60 dark:text-zinc-100">
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                            Simpan dokter
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    @endvolt
</x-layouts.app>
