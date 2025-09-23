<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProgramJAVSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('programs')) {
            return;
        }

        $name = 'Jóvenes al Volante';

        $payload = ['name' => $name];

        if (Schema::hasColumn('programs', 'area')) {
            $payload['area'] = 'Bienestar';
        }

        if (Schema::hasColumn('programs', 'slug')) {
            $payload['slug'] = Str::slug($name);
        }

        if (Schema::hasColumn('programs', 'active')) {
            $payload['active'] = true;
        }

        if (Schema::hasColumn('programs', 'description') && ! isset($payload['description'])) {
            $payload['description'] = 'Programa Jóvenes al Volante';
        }

        if (Schema::hasColumn('programs', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $uniqueKey = Schema::hasColumn('programs', 'slug')
            ? ['slug' => $payload['slug']]
            : ['name' => $name];

        $exists = DB::table('programs')->where($uniqueKey)->exists();

        if ($exists) {
            DB::table('programs')->where($uniqueKey)->update($payload);
            return;
        }

        if (Schema::hasColumn('programs', 'created_at')) {
            $payload['created_at'] = now();
        }

        DB::table('programs')->insert($payload);
    }
}