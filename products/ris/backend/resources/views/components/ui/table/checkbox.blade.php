@props(['checkboxId' => null])

{{-- 
    Native checkbox with custom UI.
    We keep the real input for accessibility and performance(smoothness), 
    and render state via CSS using peer + data-state.
--}}

<label 
    @class([
        "relative inline-grid place-items-center size-5 cursor-pointer",
        $attributes->get('class')
    ])
    
    @if ($checkboxId) 
        wire:key="{{ $checkboxId }}" 
    @endif
>
    <input
        {{ $attributes->except('class') }}
        
        @if ($checkboxId) 
            value="{{ $checkboxId }}"
        @endif
        
        type="checkbox"
        {{-- Livewire morphdom removes non-declared attributes on re-render.
            wire:ignore preserves the data-state attribute set by Alpine... --}}
        wire:ignore
        class="peer absolute inset-0 opacity-0 cursor-pointer focus:[&~]:outline"
    >

    <span
        class="size-4.5 rounded ring-1 peer-checked:ring-2 ring-neutral-100 dark:ring-white/10 peer-focus-visible:ring-2 peer-focus-visible:ring-(--color-primary) peer-focus-visible:ring-offset-2 peer-focus-visible:ring-offset-transparent peer-checked:bg-[var(--color-primary)] [&:is(:where(.peer):is([data-state=indeterminate])~*)]:bg-(--color-primary) transition shadow"
    ></span>

    {{-- Checked state indicator --}}
    <svg
        class=" absolute size-4 text-(--color-primary-fg) opacity-0 scale-75 peer-checked:opacity-100 peer-checked:scale-100 transition"
        viewBox="0 0 16 16"
        fill="currentColor"
    >
        <path 
            fill-rule="evenodd" 
            d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74Z"
        />
    </svg>

    {{-- Indeterminate state indicator --}}
    <span
        class="absolute w-3 h-0.5 rounded bg-(--color-primary-fg) opacity-0 [&:is(:where(.peer):is([data-state=indeterminate])~*)]:opacity-100 transition"
    ></span>
</label>
