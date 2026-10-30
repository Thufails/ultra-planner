# 🏔️ Ultra Planner

Web planner untuk merancang jalur lari trail dan ultra di peta, melihat ringkasan medan, menyimpan rute, lalu mengunduhnya sebagai file GPX.

## 🗺️ Fitur

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

## ⚙️ Teknologi

- PHP 8.3 atau lebih baru
- Laravel 13
- MySQL
- Node.js dan npm
- Vite
- Leaflet
- Layanan publik OpenStreetMap/Nominatim, routing, dan OpenTopoData

## 🚀 Menjalankan secara lokal

Pastikan PHP 8.3+, Composer, MySQL, Node.js, dan npm tersedia. Laragon bisa digunakan untuk menyiapkan PHP dan MySQL di Windows.

- Pasang dependensi PHP dengan `composer install`.
- Pastikan `.env` sudah dikonfigurasi sesuai MySQL lokal dan databasenya sudah dibuat. Jika `.env` belum ada, salin dari `.env.example`.
- Buat application key dengan `php artisan key:generate`.
- Jalankan migrasi database dengan `php artisan migrate`.
- Pasang dependensi frontend dengan `npm install`.
- Jalankan `php artisan serve` dan `npm run dev` di dua terminal terpisah.
- Buka alamat yang ditampilkan Artisan, biasanya `http://127.0.0.1:8000`.
- Untuk membuat aset frontend production, jalankan `npm run build`.

## 🌐 Peta dan data medan

Aplikasi memakai layanan publik berikut. Endpoint dapat diubah melalui file `.env`; nilai bawaan tersedia di `config/services.php`.

| Pengaturan | Nilai bawaan | Kegunaan |
| --- | --- | --- |
| `NOMINATIM_ENDPOINT` | `https://nominatim.openstreetmap.org/search` | Saran dan pencarian tempat |
| `ROUTING_ENDPOINT` | `https://routing.openstreetmap.de/routed-foot/route/v1/driving` | Menghitung jalur saat **Ikuti jalan** aktif |
| `ELEVATION_ENDPOINT` | `https://api.opentopodata.org/v1/srtm30m` | Mengambil estimasi elevasi medan |

Pencarian lokasi menggunakan cache dan jeda permintaan. Layanan routing dan elevasi juga memiliki batas serta dapat lambat atau tidak tersedia. Jika routing gagal, pengguna masih dapat menyusun rute dengan garis langsung. Atribusi peta OpenStreetMap ditampilkan pada halaman peta.

## 🥾 Alur merancang rute

1. Buat rute baru, beri nama, lalu cari lokasi atau arahkan peta.
2. Klik peta untuk menambahkan titik kontrol. Geser titik pada peta untuk mengubahnya; gunakan **Undo** untuk membatalkan titik terakhir.
3. Secara bawaan, titik disambungkan dengan garis langsung. Aktifkan **Ikuti jalan** untuk meminta jalur pejalan kaki dari layanan routing.
4. Periksa jarak, checkpoint, dan estimasi profil elevasi. Elevasi berasal dari model medan SRTM, bukan pengukuran GPS atau altimeter.
5. Simpan rute. Setelah tersimpan, rute dapat dibuka kembali dari **Rute Saya** untuk diedit atau ditambahkan waypoint.
6. Unduh GPX dari editor atau halaman detail rute.

Checkpoint otomatis dihitung dari geometri lintasan dan tidak disimpan sebagai tabel tersendiri. Waypoint manual disimpan pada tabel `waypoints` dan jaraknya dihitung terhadap track.

## 📦 Catatan GPX

- File GPX berisi nama rute, geometri track, dan elevasi hanya jika tersedia.
- Waypoint manual dan checkpoint otomatis ikut diekspor sebagai waypoint GPX. Aplikasi atau smartwatch tujuan dapat menampilkan waypoint dengan cara yang berbeda.
- Pace, waktu aktivitas, dan timestamp per titik tidak dibuat karena aplikasi ini merancang rute, bukan merekam aktivitas GPS.
- GPX ini tidak menjanjikan navigasi turn-by-turn. Kompatibilitas impor perlu diperiksa pada aplikasi tujuan; uji impor Strava belum dicatat dalam proyek.
- Ultra Planner saat ini berfokus pada pembuatan dan ekspor rute; impor GPX belum tersedia.

## 🧱 Struktur penting

```text
app/Http/Controllers/   Endpoint halaman, pencarian, routing, waypoint, checkpoint, dan ekspor
app/Models/             Route, RouteControlPoint, RoutePoint, dan Waypoint
app/Services/           Perhitungan geometri, checkpoint, dan profil elevasi
database/migrations/    Skema routes, route_points, route_control_points, dan waypoints
resources/views/        Beranda, daftar/detail rute, dan editor
resources/js/           Interaksi peta dan editor rute
routes/web.php          Definisi endpoint aplikasi
```

## 🔭 Status MVP

Autentikasi pengguna, impor GPX, rencana pace/ETA, fueling dan hidrasi, serta ekspor FIT/TCX belum termasuk fitur yang tersedia saat ini. Aplikasi ini masih dikembangkan bertahap.
