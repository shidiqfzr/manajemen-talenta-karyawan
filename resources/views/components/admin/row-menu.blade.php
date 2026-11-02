{{--
    Menu aksi (DESIGN.md §7.5): satu tombol per baris, aria-label memuat nama baris.

    <x-admin.row-menu :label="$row->nama"> ...butir menu... </x-admin.row-menu>
    <x-admin.row-menu :label="'unit '.$unit->nama" text="Aksi Unit"> ... </x-admin.row-menu>  (tombol berlabel untuk menu tingkat halaman)
    Isi menu memakai butir menu modul (mis. <x-admin.master.menu-item>).

    Pola ARIA menu button: Enter/Spasi/Panah bawah membuka dan memfokuskan butir pertama, Panah atas/bawah dan
    Home/End berpindah antarbutir, Esc menutup dan mengembalikan fokus ke tombol, Tab menutup.
    Panel memakai posisi fixed yang dihitung dari tombol, membuka ke atas bila ruang di bawah kurang,
    dan menutup saat halaman digulir, sehingga tidak terpotong oleh wadah tabel.
--}}
@props(['label', 'text' => null])

<div class="relative inline-block text-left"
    x-data="{
        open: false,
        panelStyle: '',
        items() { return Array.from(this.$refs.panel.querySelectorAll('[role=menuitem]')); },
        focusItem(index) {
            const items = this.items();
            if (items.length) { items[(index + items.length) % items.length].focus(); }
        },
        show(focus = 'first') {
            this.open = true;
            this.panelStyle = 'visibility:hidden';
            this.$nextTick(() => {
                const rect = this.$refs.trigger.getBoundingClientRect();
                const height = this.$refs.panel.offsetHeight;
                const right = Math.max(8, window.innerWidth - rect.right);
                const below = window.innerHeight - rect.bottom - 8;
                const opensUp = below < height && rect.top - 8 > below;
                this.panelStyle = opensUp
                    ? `right:${right}px;bottom:${window.innerHeight - rect.top + 4}px`
                    : `right:${right}px;top:${rect.bottom + 4}px`;
                requestAnimationFrame(() => {
                    if (focus === 'first') { this.focusItem(0); }
                    if (focus === 'last') { this.focusItem(-1); }
                });
            });
        },
        toggle() { this.open ? this.close() : this.show('first'); },
        close(returnFocus = false) {
            this.open = false;
            if (returnFocus) { this.$refs.trigger.focus(); }
        },
        move(step) { this.focusItem(this.items().indexOf(document.activeElement) + step); }
    }"
    @keydown.escape.window="if (open) { close(true); }" @scroll.window.capture="close()" @resize.window="close()">
    @if ($text)
        <button type="button" x-ref="trigger" @click="toggle()" @keydown.arrow-down.prevent="show('first')" @keydown.arrow-up.prevent="show('last')" @keydown.tab="close()"
            :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Aksi untuk {{ $label }}"
            class="inline-flex items-center gap-2 px-3.5 py-2 bg-white hover:bg-surface-50 text-surface-700 border border-surface-300 rounded-xl text-sm font-semibold transition cursor-pointer shadow-2xs focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <span>{{ $text }}</span>
            <i class="fas fa-chevron-down text-[10px] text-surface-500" aria-hidden="true"></i>
        </button>
    @else
        <button type="button" x-ref="trigger" @click="toggle()" @keydown.arrow-down.prevent="show('first')" @keydown.arrow-up.prevent="show('last')" @keydown.tab="close()"
            :aria-expanded="open.toString()" aria-haspopup="menu" aria-label="Aksi untuk {{ $label }}"
            class="w-8 h-8 text-surface-500 hover:text-surface-700 hover:bg-surface-100 rounded-lg transition cursor-pointer inline-flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <i class="fas fa-ellipsis-v text-xs" aria-hidden="true"></i>
        </button>
    @endif
    <div x-show="open" x-cloak x-ref="panel" role="menu" aria-label="Aksi untuk {{ $label }}" @click.away="close()" @click="close()" :style="panelStyle"
        @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
        @keydown.home.prevent="focusItem(0)" @keydown.end.prevent="focusItem(-1)" @keydown.tab="close()"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="fixed z-50 w-64 origin-top-right rounded-xl bg-white p-1 text-xs shadow-lg ring-1 ring-surface-900/5 border border-surface-200">
        {{ $slot }}
    </div>
</div>
