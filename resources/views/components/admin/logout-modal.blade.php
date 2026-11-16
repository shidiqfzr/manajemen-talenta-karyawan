{{--
    Dialog konfirmasi keluar dari sistem (DESIGN.md §7.6), dibangun di atas cangkang x-admin.modal. Satu kali di layout;
    pemicunya cukup @click="$dispatch('confirm-logout')". Keluar dikirim sebagai form POST ber-CSRF.
--}}
<div x-data="{ open: false }" @confirm-logout.window="open = true">
    <x-admin.modal title-id="logout-title" title="Keluar dari Sistem?" icon="fas fa-arrow-right-from-bracket" form :action="route('logout')"
        description="Sesi Anda akan diakhiri. Perubahan yang belum disimpan pada formulir yang terbuka akan hilang.">
        <x-slot:footer>
            <x-admin.button variant="primary" type="submit" icon="fas fa-arrow-right-from-bracket" once>Ya, Keluar</x-admin.button>
        </x-slot:footer>
    </x-admin.modal>
</div>
