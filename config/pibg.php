<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default school code
    |--------------------------------------------------------------------------
    |
    | Used to build both family codes and SSP student IDs when the teacher
    | does not override the prefix in the import form.
    |
    */

    'school_code' => env('PIBG_SCHOOL_CODE', 'SSP'),
    'announcements' => [
        ['title' => 'Mesyuarat PIBG Mendatang', 'date' => '15 April 2026', 'body' => 'Sila hadiri bengkel kewangan dan pantau status bayaran PIBG.'],
        ['title' => 'Sumbangan PIBG', 'date' => '1 April 2026', 'body' => 'Sumbangan RM100 boleh dibayar melalui portal ini atau FPX.'],
        ['title' => 'Reka bentuk kelas', 'date' => '28 Mac 2026', 'body' => 'Pastikan senarai murid terkini sebelum buat penyusunan kelas.'],
    ],
];
