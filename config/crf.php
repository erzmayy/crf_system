<?php
/**
 * config/crf.php
 * Aturan siapa yang menjadi admin CRF, berdasarkan atribut user
 * (kolom dept dan divisi/jabatan). Perbandingan tidak peka huruf
 * besar/kecil dan spasi di pinggir diabaikan.
 */

const CRF_ADMIN_RULES = [
    [
        'dept'   => 'Departemen Operasional',
        'divisi' => ['Pemimpin Divisi', 'Pemimpin Departemen'],
    ],
    [
        'dept'   => 'CMO',
        'divisi' => ['Pemimpin Divisi'],
    ],
];

/**
 * User ID yang DIKECUALIKAN walaupun cocok dengan aturan di atas.
 * Contoh: ['3127']
 */
const CRF_ADMIN_EXCLUDE_USERIDS = [];