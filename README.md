# Ultra Planner

Ultra Planner adalah aplikasi web MVP untuk merancang jalur lari trail dan ultra di peta, melihat ringkasan medan, menyimpan rute, serta mengunduhnya sebagai file GPX.

## Fitur

- Beranda dan halaman **Rute Saya** untuk membuka, membuat, mengedit, dan menghapus rute.
- Peta interaktif berbasis Leaflet dengan peta OpenStreetMap.
- Penentuan lokasi melalui pencarian nama tempat dan fitur **Lokasi saya** pada browser.
- Dua cara menggambar rute:
  - **Garis langsung** menghubungkan titik kontrol sesuai urutan. Mode ini tidak dibatasi 40 titik kontrol.
  - **Ikuti jalan** meminta layanan routing publik menghitung jalur pejalan kaki. Setiap permintaan dibatasi maksimal 40 titik kontrol.
- Jarak lintasan, jumlah titik kontrol, serta checkpoint otomatis setiap 5 km dan titik finis.
- Estimasi elevasi dan grafik profil dari data SRTM 30 m melalui OpenTopoData.
- Waypoint manual pada rute tersimpan dengan jenis **Water station**, **Checkpoint**, dan **Food station**. Lokasi waypoint dapat dipilih dan dipindahkan di peta.
- Ekspor GPX dari rute tersimpan, termasuk track, elevasi yang tersedia, waypoint manual, dan checkpoint otomatis.

## Teknologi

- PHP 8.3 atau lebih baru
- Laravel 13
- MySQL
- Node.js dan npm
- Vite
- Leaflet
- Layanan publik OpenStreetMap/Nominatim, routing, dan OpenTopoData

## Menjalankan di lokal

Pastikan PHP, Composer, MySQL, Node.js, dan npm sudah tersedia. Di Windows, Laragon dapat digunakan untuk PHP dan MySQL.

1. Pasang dependensi PHP:

   ```bash
   composer install
   ```

2. Buat file environment lokal. Di PowerShell:

   ```powershell
   Copy-Item .env.example .env
   ```

   Jika `.env` sudah ada, jangan menimpanya; cukup perbarui nilai konfigurasi yang diperlukan.

3. Buat application key:

   ```bash
   php artisan key:generate
   ```

4. Buat database MySQL, lalu atur `.env` sesuai konfigurasi lokal. Contoh:

   ```dotenv
   APP_NAME="Ultra Planner"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://127.0.0.1:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ultra_planner
   DB_USERNAME=root
   DB_PASSWORD=
   ```

   Sesuaikan nama database, username, dan password dengan MySQL milikmu.

5. Jalankan migrasi:

   ```bash
   php artisan migrate
   ```

6. Pasang dependensi frontend dan jalankan Vite:

   ```bash
   npm install
   npm run dev
   ```

7. Di terminal lain, jalankan Laravel:

   ```bash
   php artisan serve
   ```

   Buka alamat yang ditampilkan Artisan, biasanya `http://127.0.0.1:8000`.

Untuk membuat aset frontend production:

```bash
npm run build
```

## Layanan peta dan data medan

Aplikasi memakai layanan publik berikut. Endpoint dapat diubah melalui file `.env`; nilai bawaan tersedia di `config/services.php`.

| Pengaturan | Nilai bawaan | Kegunaan |
| --- | --- | --- |
| `NOMINATIM_ENDPOINT` | `https://nominatim.openstreetmap.org/search` | Saran dan pencarian tempat |
| `ROUTING_ENDPOINT` | `https://routing.openstreetmap.de/routed-foot/route/v1/driving` | Menghitung jalur saat **Ikuti jalan** aktif |
| `ELEVATION_ENDPOINT` | `https://api.opentopodata.org/v1/srtm30m` | Mengambil estimasi elevasi medan |

Pencarian lokasi menggunakan cache dan jeda permintaan. Layanan routing dan elevasi juga memiliki batas serta dapat lambat atau tidak tersedia. Jika routing gagal, pengguna masih dapat menyusun rute dengan garis langsung. Atribusi peta OpenStreetMap ditampilkan pada halaman peta.

## Cara kerja rute

1. Buat rute baru, beri nama, lalu cari lokasi atau arahkan peta.
2. Klik peta untuk menambahkan titik kontrol. Geser titik pada peta untuk mengubahnya; gunakan **Undo** untuk membatalkan titik terakhir.
3. Secara bawaan, titik disambungkan dengan garis langsung. Aktifkan **Ikuti jalan** untuk meminta jalur pejalan kaki dari layanan routing.
4. Periksa jarak, checkpoint, dan estimasi profil elevasi. Elevasi berasal dari model medan SRTM, bukan pengukuran GPS atau altimeter.
5. Simpan rute. Setelah tersimpan, rute dapat dibuka kembali dari **Rute Saya** untuk diedit atau ditambahkan waypoint.
6. Unduh GPX dari editor atau halaman detail rute.

Checkpoint otomatis dihitung dari geometri lintasan dan tidak disimpan sebagai tabel tersendiri. Waypoint manual disimpan pada tabel `waypoints` dan jaraknya dihitung terhadap track.

## Catatan GPX

- File GPX berisi nama rute, geometri track, dan elevasi hanya jika tersedia.
- Waypoint manual dan checkpoint otomatis ikut diekspor sebagai waypoint GPX. Aplikasi atau smartwatch tujuan dapat menampilkan waypoint dengan cara yang berbeda.
- Pace, waktu aktivitas, dan timestamp per titik tidak dibuat karena aplikasi ini merancang rute, bukan merekam aktivitas GPS.
- GPX ini tidak menjanjikan navigasi turn-by-turn. Kompatibilitas impor perlu diperiksa pada aplikasi tujuan; uji impor Strava belum dicatat dalam proyek.
- Ultra Planner saat ini berfokus pada pembuatan dan ekspor rute; impor GPX belum tersedia.

## Struktur penting

```text
app/Http/Controllers/   Endpoint halaman, pencarian, routing, waypoint, checkpoint, dan ekspor
app/Models/             Route, RouteControlPoint, RoutePoint, dan Waypoint
app/Services/           Perhitungan geometri, checkpoint, dan profil elevasi
database/migrations/    Skema routes, route_points, route_control_points, dan waypoints
resources/views/        Beranda, daftar/detail rute, dan editor
resources/js/           Interaksi peta dan editor rute
routes/web.php          Definisi endpoint aplikasi
```

## Status MVP

Autentikasi pengguna, impor GPX, rencana pace/ETA, fueling dan hidrasi, serta ekspor FIT/TCX belum termasuk fitur yang tersedia saat ini. Aplikasi ini masih dikembangkan bertahap.
