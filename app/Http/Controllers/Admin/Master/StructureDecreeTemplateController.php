<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Services\Structure\StructureTemplateBuilder;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan template lampiran SK: struktur organisasi saat ini dalam format unggahan. Tanpa data pribadi.
 */
class StructureDecreeTemplateController extends Controller
{
    public function __invoke(StructureTemplateBuilder $builder): StreamedResponse
    {
        abort_unless(Gate::allows('struktur.prepare'), 403);

        return response()->streamDownload(function () use ($builder): void {
            (new Xlsx($builder->build()))->save('php://output');
        }, StructureTemplateBuilder::FILE_NAME, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
