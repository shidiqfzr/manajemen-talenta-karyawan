<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Models\StructureDecree;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan berkas SK struktur (lampiran Excel dan salinan PDF) lewat stream berotorisasi dari disk privat.
 * Berkas tidak berisi data pribadi; catatan baseline tidak memiliki berkas unggahan.
 */
class StructureDecreeDownloadController extends Controller
{
    public function __invoke(StructureDecree $decree, string $berkas): StreamedResponse
    {
        abort_unless(Gate::allows('struktur.prepare'), 403);

        [$path, $name] = match ($berkas) {
            'lampiran' => [$decree->source_path, 'lampiran-sk.xlsx'],
            'salinan' => [$decree->pdf_path, 'salinan-sk.pdf'],
            default => abort(404),
        };

        abort_if($path === null || $path === '' || str_starts_with($path, 'baseline:'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name);
    }
}
