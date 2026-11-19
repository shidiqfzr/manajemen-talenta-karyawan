<?php

namespace Tests\Unit;

use App\Services\Formasi\FormasiNormalizer;
use PHPUnit\Framework\TestCase;

class FormasiNormalizerTest extends TestCase
{
    public function test_normalizer_formats_units_like_existing_master(): void
    {
        $this->assertSame('Bagian Sekretariat & Hukum', FormasiNormalizer::unit('BAGIAN SEKRETARIAT & HUKUM'));
        $this->assertSame('Kebun-PKS Longkali', FormasiNormalizer::unit('KEBUN-PKS LONGKALI'));
        $this->assertSame('Group Manager Kaltim', FormasiNormalizer::unit('GROUP MANAJER KALTIM'));
        $this->assertSame('Bagian Akuntansi & Keuangan', FormasiNormalizer::unit('BAGIAN AKUNTASI DAN KEUANGAN'));
        $this->assertSame('Kebun Kemitraan Area Kaltim-Sel', FormasiNormalizer::unit('KEBUN KEMITRAAN AREA KALTIM-SEL'));
        $this->assertSame('PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng', FormasiNormalizer::unit('PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG'));
        $this->assertSame('PMO Batu Bara & Bisnis Lainnya', FormasiNormalizer::unit('PMO BATU BARA DAN BISNIS LAINNYA'));
    }

    public function test_normalizer_formats_jabatan(): void
    {
        $this->assertSame('Asisten Humas & Protokoler', FormasiNormalizer::jabatan('ASISTEN HUMAS & PROTOKOLER'));
        $this->assertSame('Asisten Afdeling VII & VIII', FormasiNormalizer::jabatan('ASISTEN AFDELING VII DAN VIII'));
        $this->assertSame('Asisten TJSL', FormasiNormalizer::jabatan('ASISTEN TJSL'));
        $this->assertSame('Asisten Asesmen & Rekrutmen', FormasiNormalizer::jabatan('ASISTEN ASESMEN DAN REKRUITMEN'));
        $this->assertSame('Asisten Tajati', FormasiNormalizer::jabatan('Asisten Tajati'));
        $this->assertSame('Asisten Afdeling IIA', FormasiNormalizer::jabatan('ASISTEN AFDELING IIA'));
    }

    public function test_normalizer_keeps_acronyms_and_roman_numerals_up_to_xiii_uppercase(): void
    {
        $this->assertSame('Asisten Afdeling XIII', FormasiNormalizer::jabatan('ASISTEN AFDELING XIII'));
        $this->assertSame('Asisten QC Pengolahan Kelapa Sawit', FormasiNormalizer::jabatan('ASISTEN QC PENGOLAHAN KELAPA SAWIT'));
        $this->assertSame('Kepala Sub Bagian QC & Administrasi Tanaman', FormasiNormalizer::jabatan('KEPALA SUB BAGIAN QC & ADMINISTRASI TANAMAN'));
        $this->assertSame('Asisten ESG & Sustainability', FormasiNormalizer::jabatan('ASISTEN ESG DAN SUSTAINABILITY'));
        $this->assertSame('Kepala Sub Bagian Kesejahteraan & HI', FormasiNormalizer::jabatan('KEPALA SUB BAGIAN KESEJAHTERAAN DAN HI'));
        $this->assertSame('Head of PMO Investasi Tanaman Wilayah Kalbar', FormasiNormalizer::jabatan('HEAD OF PMO INVESTASI TANAMAN WILAYAH KALBAR'));
        $this->assertSame('Key User Modul FI / CO / HCM / MM / PM / SD', FormasiNormalizer::jabatan('KEY USER MODUL FI / CO / HCM / MM / PM / SD'));
        $this->assertSame('Key User Modul PP, QM & Jembatan Timbang', FormasiNormalizer::jabatan('KEY USER MODUL PP, QM, DAN JEMBATAN TIMBANG'));
        $this->assertSame('Key User Modul FM,PS,BKM,SPB & D-Farm', FormasiNormalizer::jabatan('KEY USER MODUL FM,PS,BKM,SPB, DAN D-FARM'));
    }

    public function test_normalizer_keeps_tax_and_certification_acronyms_and_roman_suffixes_c_and_d(): void
    {
        $this->assertSame('Ketua Tim Audit VC', FormasiNormalizer::jabatan('Ketua Tim Audit VC'));
        $this->assertSame('Asisten Auditor VD', FormasiNormalizer::jabatan('Asisten Auditor VD'));
        $this->assertSame('Krani PBB & Tagihan Jasa', FormasiNormalizer::jabatan('Krani PBB & Tagihan Jasa'));
        $this->assertSame('Krani Admi PPN Masukan & Keluaran', FormasiNormalizer::jabatan('KRANI ADMI PPN MASUKAN & KELUARAN'));
        $this->assertSame('Krani PPh Pasal 21 & 23', FormasiNormalizer::jabatan('KRANI PPH PASAL 21 & 23'));
        $this->assertSame('Asisten ICOFR', FormasiNormalizer::jabatan('Asisten ICOFR'));
        $this->assertSame('Krani Penagihan PUMK', FormasiNormalizer::jabatan('KRANI PENAGIHAN PUMK'));
        $this->assertSame('Krani Admi & BPJS Kesehatan', FormasiNormalizer::jabatan('KRANI ADMI & BPJS KESEHATAN'));
        $this->assertSame('Krani Bidang Pengelolaan PSR/KKPA', FormasiNormalizer::jabatan('KRANI BIDANG PENGELOLAAN PSR/KKPA'));
        $this->assertSame('Krani Sertifikasi (RSPO/ISPO/ISO)', FormasiNormalizer::jabatan('KRANI SERTIFIKASI (RSPO/ISPO/ISO)'));
        $this->assertSame('Krani SMK3 & Admi Manajemen Sertifikasi', FormasiNormalizer::jabatan('KRANI SMK3 & ADMI MANAJEMEN SERTIFIKASI'));
    }
}
