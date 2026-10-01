<?php
/**
 * Shared authenticated navigation context.
 *
 * The including view must define $portalRole and $portalActive before loading
 * this file. Keeping the role map here prevents operational pages from
 * drifting away from their dashboard navigation.
 */
$portalMaps = [
    'ADMIN' => [
        'home' => '/admin/dashboard',
        'label' => 'Administrator',
        'nav' => [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'/admin/dashboard'],
            ['key'=>'participants','label'=>'Kelola peserta','href'=>'/admin/peserta'],
            ['key'=>'exams','label'=>'Sesi ujian','href'=>'/admin/ujian'],
            ['key'=>'questions','label'=>'Bank soal','href'=>'/admin/bank-soal'],
            ['key'=>'monitoring','label'=>'Pengawasan','href'=>'/admin/pengawasan'],
            ['key'=>'decisions','label'=>'Keputusan kelulusan','href'=>'/admin/keputusan'],
            ['key'=>'reports','label'=>'Laporan hasil','href'=>'/admin/laporan'],
            ['key'=>'configuration','label'=>'Konfigurasi','href'=>'/admin/konfigurasi'],
            ['key'=>'retention','label'=>'Retensi data','href'=>'/admin/retensi'],
        ],
    ],
    'PARTICIPANT' => [
        'home' => '/dashboard',
        'label' => 'Peserta',
        'nav' => [
            ['key'=>'dashboard','label'=>'Dashboard','href'=>'/dashboard'],
            ['key'=>'profile','label'=>'Profil peserta','href'=>'/profil-peserta'],
            ['key'=>'exams','label'=>'Sesi ujian saya','href'=>'/ujian'],
        ],
    ],
];

$portalConfig = $portalMaps[$portalRole] ?? $portalMaps['PARTICIPANT'];
$portalHome = $portalConfig['home'];
$portalRoleLabel = $portalConfig['label'];
$portalNav = $portalConfig['nav'];
