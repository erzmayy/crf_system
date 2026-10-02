# CRF Workflow Update

Alur workflow:

Pemohon → CMO (Filter) → Otomasi (Level Urgensi + SLA) → Kepala Departemen Operasional (Approval) → Otomasi (Eksekusi & Implementasi) → Pemohon (Post Implementation Review) → CMO (Finalisasi) → Selesai → Pemohon

## Tahap workflow
- PEMOHON
- CMO_FILTER
- OTOMASI
- kadep_operasional
- PEMOHON_PIR
- CMO_FINAL
- SELESAI

`PEMOHON_PIR` adalah tahap aktif bagi Pemohon untuk mengisi Post Implementation Review.

## Role prototype
- pemohon
- cmo
- otomasi
- kadep_operasional
- admin

## Akun demo
Semua password: `password`

- USER001 — Pemohon
- CMO001 — CMO
- OTOMASI001 — Otomasi
- JOKO001 — Pak Joko
- ADMIN001 — Admin

## Database
Untuk database `crf_system` lama yang belum memiliki kolom approval Kepala Departemen Operasional, jalankan:

`kadep_approval_migration.sql`

Migrasi ini aman dijalankan ulang. Untuk instalasi workflow lama yang belum memiliki kolom workflow/SLA atau tabel role, gunakan `workflow_migration.sql`.

Untuk database yang sudah berjalan, jalankan `kadep_workflow_stage_migration.sql` agar enum `workflow_stage` mencakup `PEMOHON_PIR`. Migrasi ini juga menyelaraskan nilai lama `PAK_JOKO` menjadi `kadep_operasional`.

Untuk database yang sudah berjalan, jalankan `implementation_date_migration.sql` sebelum menggunakan form terbaru. Migrasi ini menyediakan kolom Tanggal Implementasi, Tanggal Post Implementation Review, Tipe Pengajuan, dan kategori Dampak.

Migration menambahkan:
- `workflow_stage`
- data SLA
- timestamp proses Otomasi
- data approval Pak Joko
- tabel `crf_user_roles`
- mapping role dan akun demo workflow bila belum ada

## Catatan
- Level Urgensi ditentukan otomatis dari Dampak saat Pemohon mengajukan CRF. Otomasi menetapkan SLA tanpa mengubah Level Urgensi, lalu mengirim CRF ke Kepala Departemen Operasional untuk approval sebelum eksekusi.
- Setelah approval, Otomasi mengisi Tanggal Implementasi dan Implementasi / Hasil Perubahan, lalu menyelesaikan eksekusi ke tahap `PEMOHON_PIR`.
- Pemohon mengisi Tanggal dan hasil Post Implementation Review pada halaman Post Implementation Review. Setelah dikirim, hasil review tampil read-only pada detail CRF dan workflow diteruskan ke `CMO_FINAL`.
- Waktu penyelesaian SLA dicatat saat Otomasi menyelesaikan eksekusi melalui `automation_completed_at`; pengisian Post Implementation Review tidak mengubah waktu tersebut.
