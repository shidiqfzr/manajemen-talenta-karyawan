<?php

namespace Tests\Fixtures\DataTable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Throwaway Eloquent model backing data table tests.
 */
class FixtureRow extends Model
{
    protected $table = 'data_table_fixture_rows';

    protected $guarded = [];

    public $timestamps = false;

    /** Safe to call repeatedly (e.g. from setUp under RefreshDatabase). */
    public static function createTable(): void
    {
        if (Schema::hasTable('data_table_fixture_rows')) {
            return;
        }

        Schema::create('data_table_fixture_rows', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->string('kode')->nullable();
            $table->string('bidang');
            $table->boolean('aktif')->default(true);
            $table->integer('nilai');
        });
    }
}
