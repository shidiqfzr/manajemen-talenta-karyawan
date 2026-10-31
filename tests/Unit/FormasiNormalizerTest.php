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
        $this->assertSame('Asisten Afdeling VII dan VIII', FormasiNormalizer::jabatan('ASISTEN AFDELING VII DAN VIII'));
        $this->assertSame('Asisten TJSL', FormasiNormalizer::jabatan('ASISTEN TJSL'));
        $this->assertSame('Asisten Asesmen dan Rekrutmen', FormasiNormalizer::jabatan('ASISTEN ASESMEN DAN REKRUITMEN'));
        $this->assertSame('Asisten Tajati', FormasiNormalizer::jabatan('Asisten Tajati'));
        $this->assertSame('Asisten Afdeling IIA', FormasiNormalizer::jabatan('ASISTEN AFDELING IIA'));
    }

    public function test_normalizer_keeps_acronyms_and_roman_numerals_up_to_xiii_uppercase(): void
    {
        $this->assertSame('Asisten Afdeling XIII', FormasiNormalizer::jabatan('ASISTEN AFDELING XIII'));
        $this->assertSame('Asisten QC Pengolahan Kelapa Sawit', FormasiNormalizer::jabatan('ASISTEN QC PENGOLAHAN KELAPA SAWIT'));
        $this->assertSame('Kepala Sub Bagian QC & Administrasi Tanaman', FormasiNormalizer::jabatan('KEPALA SUB BAGIAN QC & ADMINISTRASI TANAMAN'));
        $this->assertSame('Asisten ESG dan Sustainability', FormasiNormalizer::jabatan('ASISTEN ESG DAN SUSTAINABILITY'));
        $this->assertSame('Kepala Sub Bagian Kesejahteraan dan HI', FormasiNormalizer::jabatan('KEPALA SUB BAGIAN KESEJAHTERAAN DAN HI'));
        $this->assertSame('Head of PMO Investasi Tanaman Wilayah Kalbar', FormasiNormalizer::jabatan('HEAD OF PMO INVESTASI TANAMAN WILAYAH KALBAR'));
        $this->assertSame('Key User Modul FI / CO / HCM / MM / PM / SD', FormasiNormalizer::jabatan('KEY USER MODUL FI / CO / HCM / MM / PM / SD'));
        $this->assertSame('Key User Modul PP, QM, dan Jembatan Timbang', FormasiNormalizer::jabatan('KEY USER MODUL PP, QM, DAN JEMBATAN TIMBANG'));
        $this->assertSame('Key User Modul FM,PS,BKM,SPB, dan D-Farm', FormasiNormalizer::jabatan('KEY USER MODUL FM,PS,BKM,SPB, DAN D-FARM'));
    }
}
