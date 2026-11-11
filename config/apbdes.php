<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Katalog Resmi 14 Kode Pos Baku APBDes (Permendagri No. 20/2018 & PRD 03)
    |--------------------------------------------------------------------------
    */
    'katalog_pos' => [
        // Kelompok Pendapatan (6 Pos)
        [
            'urutan' => 1,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'dds',
            'nama_pos' => 'Dana Desa (DDS)',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 2,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'add',
            'nama_pos' => 'Alokasi Dana Desa (ADD)',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 3,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'pades',
            'nama_pos' => 'Pendapatan Asli Desa (PADes)',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 4,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'bhprd',
            'nama_pos' => 'Bagi Hasil Pajak & Retribusi Daerah (BHPRD)',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 5,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'bankeu',
            'nama_pos' => 'Bantuan Keuangan Provinsi/Kabupaten',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 6,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'pendapatan_lain',
            'nama_pos' => 'Pendapatan Lain-lain yang Sah',
            'is_otomatis_modul_1' => false,
        ],

        // Kelompok Belanja 5 Bidang Permendagri 20/2018 (6 Pos)
        [
            'urutan' => 7,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_1',
            'nama_pos' => 'Bidang Penyelenggaraan Pemerintahan Desa',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 8,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_2_fisik',
            'nama_pos' => 'Bidang Pelaksanaan Pembangunan Desa - Komponen Fisik',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 9,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_2_non_fisik',
            'nama_pos' => 'Bidang Pelaksanaan Pembangunan Desa - Komponen Non-Fisik',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 10,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_3',
            'nama_pos' => 'Bidang Pembinaan Kemasyarakatan Desa',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 11,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_4',
            'nama_pos' => 'Bidang Pemberdayaan Masyarakat Desa',
            'is_otomatis_modul_1' => false,
        ],
        [
            'urutan' => 12,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_5',
            'nama_pos' => 'Bidang Penanggulangan Bencana, Keadaan Darurat & Mendesak',
            'is_otomatis_modul_1' => false,
        ],

        // Kelompok Pembiayaan Penerimaan (1 Pos)
        [
            'urutan' => 13,
            'kelompok' => 'pembiayaan_penerimaan',
            'kode_pos' => 'silpa',
            'nama_pos' => 'SiLPA Tahun Sebelumnya',
            'is_otomatis_modul_1' => false,
        ],

        // Kelompok Pembiayaan Pengeluaran (1 Pos)
        [
            'urutan' => 14,
            'kelompok' => 'pembiayaan_pengeluaran',
            'kode_pos' => 'penyertaan_modal',
            'nama_pos' => 'Penyertaan Modal Desa (BUMDes) / Pembentukan Dana Cadangan',
            'is_otomatis_modul_1' => false,
        ],
    ],
];
