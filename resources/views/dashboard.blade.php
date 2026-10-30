@extends('layouts.app')

@section('title', 'Beranda · Ultra Planner')

@section('content')
    <div class="home-page">
        <section class="home-hero">
            <div class="home-hero-copy">
                <p class="home-eyebrow"><span></span> RUANG RENCANA PELARI TRAIL</p>
                <h1>Jalur hebat dimulai dari <span>rencana yang matang.</span></h1>
                <p class="home-lede">Rancang rute trail dan ultra di peta, atur titik kontrol, lalu simpan rencana perjalananmu dalam satu tempat.</p>
                <div class="home-actions">
                    <a class="button button-primary" href="{{ route('routes.create') }}">Rancang rute</a>
                    <a class="home-secondary-link" href="{{ route('routes.index') }}">Lihat rute saya</a>
                </div>
                <div class="home-proofline">
                    <span class="home-proof-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="7.5"/><circle cx="12" cy="12" r="2"/><path d="M12 2v2.5M22 12h-2.5M12 22v-2.5M2 12h2.5"/></svg></span>
                    <span>Mulai dari peta. Bentuk jalur sesuai rencanamu.</span>
                </div>
            </div>

            <div class="home-hero-art home-hero-photo">
                <img src="{{ asset('images/trail-runner-hero.webp') }}" width="1600" height="900"
                    alt="Pelari trail menapaki jalur pegunungan hijau dengan jajaran gunung berkabut di kejauhan"
                    fetchpriority="high">
                <div class="home-photo-overlay"></div>
                <div class="home-photo-caption">
                    <span>ULTRA PLANNER · TRAIL ROUTE BUILDER</span>
                    <strong>Jalurmu dimulai dari sini.</strong>
                </div>
            </div>
        </section>

        <section class="home-how" aria-labelledby="home-how-title">
            <div class="home-section-heading">
                <div>
                    <p class="eyebrow">DARI PETA KE RUTE</p>
                    <h2 id="home-how-title">Rencanakan dengan cara yang sederhana.</h2>
                </div>
                <p>Semua dimulai dari titik-titik yang kamu pilih sendiri.</p>
            </div>

            <div class="home-step-grid">
                <article class="home-step-card">
                    <span class="home-step-icon home-step-icon-blue" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg></span>
                    <h3>Temukan area</h3>
                    <p>Cari lokasi atau gunakan peta untuk menentukan tempat mulai dan tujuan.</p>
                </article>
                <article class="home-step-card">
                    <span class="home-step-icon home-step-icon-cyan" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M5 19V5m14 14V5M5 12h14"/><circle cx="5" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="19" r="2"/></svg></span>
                    <h3>Bentuk jalurmu</h3>
                    <p>Tambah dan geser titik kontrol. Aktifkan Ikuti jalan untuk mencoba jalur pejalan kaki.</p>
                </article>
                <article class="home-step-card">
                    <span class="home-step-icon home-step-icon-violet" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M6 3.5h8l4 4V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4.5a1 1 0 0 1 1-1Z"/><path d="M14 3.5V8h4M8 13h8M8 17h5"/></svg></span>
                    <h3>Simpan rencana</h3>
                    <p>Simpan rute agar bisa dibuka lagi dan disempurnakan kapan pun.</p>
                </article>
            </div>
        </section>

        <section class="home-insights" aria-labelledby="home-insights-title">
            <div class="home-insights-heading">
                <div>
                    <p class="home-eyebrow">CATATAN SEBELUM BERANGKAT</p>
                    <h2 id="home-insights-title">Peta membantu merencanakan. Kamu tetap mengenal medannya.</h2>
                </div>
                <p>Rute trail punya banyak karakter. Sisakan ruang untuk memeriksa detailnya.</p>
            </div>
            <div class="home-insight-grid">
                <article>
                    <span class="home-insight-mark">A</span>
                    <div><h3>Periksa tiap ruas</h3><p>Data jalur pada peta bisa berbeda kelengkapan dan kondisinya. Tinjau jalur sebelum digunakan.</p></div>
                </article>
                <article>
                    <span class="home-insight-mark">B</span>
                    <div><h3>Titik kontrol itu penting</h3><p>Titik antara membantu membentuk jalur sesuai rencana, terutama saat rute punya banyak belokan.</p></div>
                </article>
                <article>
                    <span class="home-insight-mark">C</span>
                    <div><h3>GPX sedang disiapkan</h3><p>Ekspor GPX adalah langkah berikutnya agar rute dapat dibawa ke aplikasi atau perangkat yang mendukungnya.</p></div>
                </article>
            </div>
            <p class="home-map-disclaimer">Rute yang terlihat di peta bukan konfirmasi akses atau kondisi jalur terkini. Periksa informasi setempat sebelum berangkat.</p>
        </section>

        <section class="home-community">
            <div class="home-community-mark" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="m4 24 7-12 5 8 4-6 8 10H4Z"/><path d="M10 24c3-3 6-4 10-2 2 1 4 1 7 0"/></svg></div>
            <div>
                <p class="home-eyebrow">RUANG UNTUK CERITA TRAIL</p>
                <h2>Perjalanan trail lebih luas dari garis di peta.</h2>
                <p>Ke depan, Beranda bisa berkembang dengan panduan, cerita atlet, dan info lomba. Sekarang kita mulai dari alat merancang rute yang bisa diandalkan.</p>
            </div>
            <a class="button button-light" href="{{ route('routes.create') }}">Mulai rancang rute</a>
        </section>

        <footer class="home-footer">
            <span>ULTRA PLANNER <i>·</i> Rancang jalurmu sendiri</span>
            <a href="{{ route('routes.index') }}">Rute saya</a>
        </footer>
    </div>
@endsection
