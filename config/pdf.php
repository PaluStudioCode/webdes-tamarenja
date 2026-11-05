<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Standar Ukuran Kertas Dinas Desa: Folio / F4 (215 × 330 mm)
    |--------------------------------------------------------------------------
    |
    | Berdasarkan tata naskah dinas pemerintahan desa, seluruh surat resmi dan
    | laporan keuangan menggunakan kertas F4 / Folio:
    | - Lebar: 215 mm (609.45 pt)
    | - Tinggi: 330 mm (935.43 pt)
    | - Bounds DomPDF: [0, 0, 609.45, 935.43]
    |
    */
    'f4' => [
        'width_mm' => 215,
        'height_mm' => 330,
        'width_pt' => 609.45,
        'height_pt' => 935.43,
        'dimensions' => [0, 0, 609.45, 935.43],
    ],
];
