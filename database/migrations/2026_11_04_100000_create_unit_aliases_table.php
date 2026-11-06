<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama lama/variasi penulisan unit yang masih ditemukan pada berkas pegawai lama.
     * Dahulu berupa konstanta di kode impor; kini disimpan sebagai data master.
     *
     * @var array<string, string> alias => nama unit master
     */
    private const LEGACY_ALIASES = [
        'PROJECT MANAGEMENT OFFICE (PMO) INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG' => 'PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng',
        'PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG' => 'PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng',
        'PROJECT MANAGEMENT OFFICE (PMO) BATU BARA DAN BISNIS LAINNYA' => 'PMO Batu Bara & Bisnis Lainnya',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('unit_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->string('alias');
            $table->string('alias_key')->unique()->comment('Bentuk ternormalisasi alias (huruf kecil, & = dan, spasi tunggal) untuk pencocokan');
            $table->string('source', 20)->default('manual')->comment('baseline | rename | manual');
            $table->timestamps();
        });

        foreach (self::LEGACY_ALIASES as $alias => $unitName) {
            $unitId = DB::table('units')->where('nama', $unitName)->value('id');
            if ($unitId === null) {
                continue;
            }

            DB::table('unit_aliases')->insert([
                'unit_id' => $unitId,
                'alias' => $alias,
                'alias_key' => strtolower(preg_replace('/\s+/', ' ', str_replace('&', ' dan ', $alias))),
                'source' => 'baseline',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_aliases');
    }
};
