# Portal Pengurusan Sumbangan PIBG

Portal web untuk mengurus kutipan sumbangan PIBG mengikut keluarga, kelas dan sesi persekolahan. Sistem ini menyatukan akses ibu bapa, pemantauan guru, operasi PIBG, pembayaran dalam talian, komunikasi WhatsApp, laporan kewangan dan jejak audit dalam satu aplikasi.

[Lihat katalog ciri](https://mmarifmz.github.io/portal_pengurusan_yuran_pibg/) · [Panduan penerbitan](docs/FIRST_LIVE_PUBLISH.md) · [Laporkan isu](https://github.com/mmarifmz/portal_pengurusan_yuran_pibg/issues)

> Data dalam tangkap layar dokumentasi ialah data demonstrasi atau telah dinyahkenal pasti. Jangan komit fail `.env`, pangkalan data, nombor telefon, data murid atau bukti pembayaran sebenar.

## Kandungan

- [Gambaran keseluruhan](#gambaran-keseluruhan)
- [Keupayaan utama](#keupayaan-utama)
- [Peranan dan akses](#peranan-dan-akses)
- [Teknologi](#teknologi)
- [Keperluan sistem](#keperluan-sistem)
- [Persediaan tempatan](#persediaan-tempatan)
- [Konfigurasi](#konfigurasi)
- [Arahan harian](#arahan-harian)
- [Ujian dan kualiti](#ujian-dan-kualiti)
- [Operasi produksi](#operasi-produksi)
- [Keselamatan dan privasi](#keselamatan-dan-privasi)
- [Struktur projek](#struktur-projek)
- [Dokumentasi tambahan](#dokumentasi-tambahan)
- [Penyelesaian masalah](#penyelesaian-masalah)

## Gambaran keseluruhan

Sumbangan dinilai pada peringkat keluarga, bukan hanya seorang murid. Satu keluarga boleh mempunyai beberapa murid dan pembayaran boleh dibuat secara penuh atau ansuran. Aplikasi mengagihkan transaksi, mengekalkan sejarah pembayaran dan menyediakan pandangan berbeza mengikut peranan pengguna.

Aliran utama:

1. Data murid, keluarga, kelas dan konfigurasi sumbangan dimasukkan oleh pentadbir.
2. Ibu bapa mencari rekod keluarga dan mengesahkan akses melalui TAC/OTP.
3. Bayaran dibuat melalui gerbang pembayaran atau diselesaikan secara manual oleh pentadbir yang dibenarkan.
4. Guru dan PIBG memantau kemajuan kelas serta menyelaras peringatan.
5. Resit, laporan PDF, eksport kewangan dan audit menyediakan bukti operasi.

## Keupayaan utama

### Ibu bapa dan pembayaran

- Carian rekod keluarga dengan aliran TAC/OTP.
- Paparan baki, status sumbangan dan pecahan ahli keluarga.
- Bayaran penuh atau pelan ansuran.
- Integrasi ToyyibPay untuk checkout, callback dan halaman kembali.
- Sejarah transaksi, resit PDF dan pautan perkongsian resit.
- Notifikasi penerimaan bayaran melalui saluran WhatsApp yang dikonfigurasi.

### Pemantauan kelas

- Kemajuan kutipan mengikut kelas dan keluarga.
- Profil keluarga serta rekod pembayaran untuk guru yang dibenarkan.
- Laporan PDF berasingan untuk ringkasan, dah bayar dan belum bayar.
- Senarai “Belum Bayar” bermula pada halaman baharu.
- Cap masa dijana pada laporan untuk rujukan.
- Muat turun semua laporan kelas dalam satu fail ZIP untuk System Admin.
- Papan kedudukan sumbangan dan eksport berkaitan.

### WhatsApp dan susulan

- Pratonton mesej sebelum dihantar.
- Baris gilir mesej dengan status tertunda, sedang dihantar, berjaya atau gagal.
- Penghantaran berkelompok mengikut kelas.
- Pemantauan baris gilir serta arahan pemprosesan semula.
- Sokongan pembekal WaSender dan Twilio melalui konfigurasi persekitaran.

### Pentadbiran murid dan pengguna

- Import data murid serta pengguna guru.
- Pengurusan guru, jemputan onboarding, kelas dan kebenaran.
- Pengurusan ibu bapa serta pautan ibu bapa–murid.
- Penyelesaian pembayaran keluarga secara manual dengan rekod transaksi.
- Jejak pertukaran kelas murid yang menyimpan nilai sebelum/selepas, pentadbir dan masa perubahan.
- Tetapan tag sosial murid, akaun penguji bayaran dan kawalan akses.

### Kempen, analitik dan kewangan

- Kempen QR dengan sumber, medium, label dan tempoh.
- Muat turun kod QR serta poster PNG.
- Analitik imbasan, pembayaran dan kadar penukaran.
- Monitor corong pembayaran dan semakan kesihatan gerbang.
- Lejar perakaunan, ringkasan kutipan dan eksport kewangan.
- Log pelawat dan eksport untuk analisis operasi.
- Kalendar sekolah, konfigurasi portal dan tetapan kempen.

### Integrasi dan API

- Endpoint callback ToyyibPay dengan pemadanan transaksi.
- API semakan status bayaran menggunakan kunci akses guru.
- Endpoint notifikasi TAC ibu bapa dan resit transaksi.
- Aplikasi web progresif (PWA) untuk pengalaman mudah alih.

## Peranan dan akses

| Peranan | Skop utama |
| --- | --- |
| `parent` | Rekod keluarga sendiri, pelan bayaran, transaksi dan resit |
| `teacher` | Kelas yang diberikan, profil keluarga dan kemajuan kutipan |
| `super_teacher` | Pemantauan sekolah yang lebih luas serta fungsi guru lanjutan |
| `pta` | Pandangan operasi PIBG, kutipan dan laporan yang dibenarkan |
| `system_admin` | Konfigurasi sistem, pengguna, kewangan, kempen, import dan audit |

Kawalan akses dilaksanakan pada route/middleware dan disokong oleh semakan peranan pada model. Menyembunyikan butang pada antaramuka sahaja tidak dianggap sebagai kawalan akses.

## Teknologi

| Lapisan | Teknologi |
| --- | --- |
| Aplikasi | Laravel 13, PHP |
| Antaramuka | Livewire 4, Flux UI, Blade |
| Gaya dan aset | Tailwind CSS 4, Vite 8 |
| Pengesahan | Laravel Fortify, 2FA |
| Laporan | DomPDF |
| Kod QR | endroid/qr-code |
| Ujian | Pest 4 |
| Pangkalan data | SQLite untuk pembangunan ringkas; MySQL untuk produksi |
| Kerja latar | Laravel Queue, proses Supervisor di produksi |

## Keperluan sistem

- PHP **8.4 atau lebih baharu** dengan sambungan biasa Laravel (`curl`, `dom`, `fileinfo`, `filter`, `gd`, `mbstring`, `openssl`, `pdo`, `session`, `tokenizer`, `xml`, `zip`).
- Composer 2.
- Node.js 22 dan npm.
- SQLite untuk pembangunan tempatan, atau MySQL 8 untuk persekitaran setara produksi.
- Git.

Kunci pergantungan semasa memerlukan PHP 8.4. Jika arahan Composer melaporkan versi PHP tidak serasi, semak versi CLI sebenar sebelum mengubah fail kunci.

## Persediaan tempatan

```bash
git clone https://github.com/mmarifmz/portal_pengurusan_yuran_pibg.git
cd portal_pengurusan_yuran_pibg
composer setup
composer dev
```

`composer setup` akan:

1. memasang pergantungan PHP;
2. mencipta `.env` daripada `.env.example` jika belum ada;
3. menjana `APP_KEY`;
4. mencipta pangkalan data SQLite jika diperlukan;
5. menjalankan migrasi;
6. memasang pergantungan frontend; dan
7. membina aset produksi.

`composer dev` menjalankan pelayan Laravel, queue worker, pembaca log dan Vite secara serentak. Untuk kerja berasingan:

```bash
php artisan serve
npm run dev
php artisan queue:work
```

Jangan jalankan `composer setup` pada pangkalan data produksi tanpa menyemak `.env` dan pemacu pangkalan data terlebih dahulu.

## Konfigurasi

Salin `.env.example` dan berikan nilai sebenar melalui pengurus rahsia atau persekitaran hos. Kumpulan utama ialah:

### Aplikasi dan pangkalan data

```dotenv
APP_NAME="Portal Sumbangan PIBG"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite
```

Untuk MySQL, tetapkan `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` dan `DB_PASSWORD`. Cache, session dan queue menggunakan pemacu pangkalan data secara lalai.

### Gerbang pembayaran

Tetapkan pemboleh ubah ToyyibPay termasuk URL asas, kod kategori, kunci rahsia dan mod sandbox/produksi. Pastikan `APP_URL` awam betul supaya callback dan return URL boleh dicapai.

### WhatsApp

Pilih pembekal yang disokong dan isikan token, URL API, nombor penghantar atau SID yang berkaitan. Uji dengan akaun penguji sebelum menghantar kepada penerima sebenar.

### PWA, installer dan operasi

`.env.example` turut menyediakan pilihan untuk PWA, akaun penguji bayaran, identiti sekolah dan kawalan sandaran. Kekalkan nilai sensitif di luar repositori.

Selepas mengubah konfigurasi produksi:

```bash
php artisan config:clear
php artisan config:cache
```

## Arahan harian

```bash
# Jalankan aplikasi pembangunan lengkap
composer dev

# Jalankan migrasi
php artisan migrate

# Proses baris gilir biasa
php artisan queue:work --tries=3

# Proses dan semak baris gilir WhatsApp
php artisan whatsapp:process-queue --limit=20
php artisan whatsapp:queue-status

# Import pengguna guru
php artisan teachers:import storage/app/imports/teachers.csv --assign-class

# Import rekod keluarga lama tanpa menulis data
php artisan billing:import-legacy-families storage/app/imports/families.csv --dry-run

# Bersihkan cache semasa menyiasat konfigurasi
php artisan optimize:clear
```

Gunakan `php artisan list` dan `php artisan help <command>` untuk pilihan lengkap sebelum menjalankan import atau operasi pukal.

## Ujian dan kualiti

```bash
# Suite projek: cache bersih, Pint, ujian dan binaan frontend
composer test

# Ujian aplikasi sahaja
php artisan test

# Fail atau senario terpilih
php artisan test tests/Feature/QrCampaignAnalyticsTest.php
php artisan test --filter=StudentClassChangeTest

# Semak gaya tanpa mengubah fail
composer lint:check

# Semak binaan frontend
npm run build
```

Suite ciri merangkumi pengesahan, OTP ibu bapa, ansuran, pembayaran, notifikasi, laporan, QR analytics, pengurusan pengguna, kebenaran guru, pertukaran kelas dan aliran WhatsApp.

Aliran GitHub Actions menjalankan semakan gaya, ujian dan binaan. Workflow Pages menerbitkan kandungan `docs/` apabila perubahan berkaitan digabungkan ke `main`.

## Operasi produksi

Sasaran rujukan ialah Ubuntu + Nginx + PHP-FPM + MySQL + Supervisor:

1. sediakan pelayan, pangkalan data dan `.env` produksi;
2. pasang pergantungan dengan `composer install --no-dev --optimize-autoloader`;
3. bina aset menggunakan Node.js;
4. jalankan `php artisan migrate --force`;
5. cache konfigurasi, route dan view;
6. tetapkan kebenaran `storage/` serta `bootstrap/cache/`;
7. jalankan queue worker melalui Supervisor; dan
8. sahkan login, pembayaran sandbox, callback, resit dan laporan.

Arahan, konfigurasi Nginx/Supervisor, smoke test dan rollback tersedia dalam [panduan penerbitan pertama](docs/FIRST_LIVE_PUBLISH.md).

Sebelum migrasi atau arahan Artisan yang boleh mengubah data:

```bash
php artisan config:clear
php artisan about --only=environment,drivers
```

Sahkan persekitaran dan pangkalan data sebenar. Jangan gunakan `migrate:fresh` pada produksi.

## Keselamatan dan privasi

- Fortify mengurus pengesahan dan pilihan 2FA.
- Akses sensitif dilindungi oleh role middleware dan semakan kebenaran server-side.
- Kunci API guru perlu dianggap sebagai rahsia dan diputar jika terdedah.
- Callback pembayaran perlu disahkan serta diproses secara idempotent.
- Penyelesaian manual mesti merekodkan pentadbir, jumlah dan transaksi berkaitan.
- Notifikasi dan baris gilir tidak boleh mendedahkan nombor telefon atau token dalam log awam.
- Eksport, PDF dan ZIP mungkin mengandungi data peribadi; simpan dan kongsi melalui saluran yang diluluskan.
- Jangan masukkan data sebenar dalam fixture, tangkap layar, isu GitHub atau pull request.
- Jalankan sandaran sebelum migrasi produksi dan uji proses pemulihan secara berkala.

## Struktur projek

```text
app/
├── Http/Controllers/       # Endpoint web, callback dan API
├── Jobs/                   # Kerja baris gilir
├── Livewire/               # Halaman dan komponen interaktif
├── Models/                 # Model domain dan hubungan data
└── Services/               # Pembayaran, laporan, import dan notifikasi
database/
├── factories/
├── migrations/
└── seeders/
docs/                       # GitHub Pages dan panduan operasi
resources/
├── css/
├── js/
└── views/
routes/
├── console.php
└── web.php
tests/
├── Feature/
└── Unit/
```

## Dokumentasi tambahan

- [Katalog ciri visual](https://mmarifmz.github.io/portal_pengurusan_yuran_pibg/)
- [Penerbitan produksi pertama](docs/FIRST_LIVE_PUBLISH.md)
- [Audit peranan dan kebenaran guru](docs/internal/teacher-role-permission-audit.md)
- [Baris gilir notifikasi bayaran guru](docs/teacher-payment-notification-queue.md)

## Penyelesaian masalah

### Perubahan `.env` tidak berkuat kuasa

```bash
php artisan optimize:clear
php artisan config:cache
```

### Aset antaramuka hilang atau lama

```bash
npm ci
npm run build
```

Pastikan Nginx menghala ke direktori `public/`, bukan akar repositori.

### Mesej WhatsApp kekal tertunda

```bash
php artisan whatsapp:queue-status
php artisan queue:failed
```

Semak worker, pembekal yang dipilih, token, capaian rangkaian dan masa `scheduled_at`. Jangan tandakan mesej sebagai berjaya tanpa respons pembekal.

### Callback pembayaran tidak diterima

Semak `APP_URL`, URL callback pada gerbang, HTTPS, firewall dan log aplikasi. Gunakan transaksi sandbox dahulu dan pastikan pemprosesan callback tidak menggandakan transaksi.

### Ujian gagal selepas pertukaran versi PHP

```bash
php -v
composer check-platform-reqs
```

Gunakan PHP 8.4+ yang sepadan dengan `composer.lock`, kemudian pasang semula pergantungan tanpa mengubah fail kunci secara tidak sengaja.

## Sumbangan kod

1. Cipta branch berfokus daripada `main`.
2. Buat perubahan kecil yang serasi dengan corak sedia ada.
3. Tambah atau kemas kini ujian untuk tingkah laku yang berubah.
4. Jalankan `composer lint:check`, ujian terpilih dan `npm run build`.
5. Buka pull request dengan ringkasan, bukti ujian dan tangkap layar bagi perubahan visual.

Metadata Composer menyatakan lesen MIT. Tambah fail `LICENSE` yang diluluskan pemilik repositori sebelum bergantung pada terma lesen untuk pengedaran rasmi.
