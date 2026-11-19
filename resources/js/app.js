import './bootstrap';
import '../css/app.css';

import { employeeImportWizard } from './admin/employee-import-wizard';

// Komponen Alpine halaman: didaftarkan sebelum Livewire menjalankan Alpine.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('employeeImportWizard', employeeImportWizard);
});
