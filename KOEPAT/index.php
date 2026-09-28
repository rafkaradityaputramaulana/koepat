<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KOEPAT Kuliner 24 - Dashboard</title>
    <style>
        :root {
            --primary: #0f172a; /* Navy Blue */
            --accent: #facc15;  /* Yellow */
            --light: #f8fafc;   /* White / Light Gray */
            --text-dark: #334155;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--light); color: var(--text-dark); }

        header {
            background-color: var(--primary); color: white; padding: 20px 5%;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 4px solid var(--accent);
        }
        header h1 { font-size: 24px; color: var(--accent); }

        /* Banner Template */
        .banner-container {
            width: 100%;
            height: 400px;
            background-color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        .banner-img {
            width: 100%; height: 100%; object-fit: cover;
            position: absolute; top: 0; left: 0; z-index: 1;
        }
        .banner-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(15, 23, 42, 0.75), rgba(15, 23, 42, 0.9));
            z-index: 2;
        }
        .banner-content {
            position: relative; z-index: 3; color: white; text-align: center; padding: 0 20px;
        }
        .banner-content h2 { font-size: 42px; margin-bottom: 15px; color: #fff; }
        .banner-content p { font-size: 18px; max-width: 600px; margin: 0 auto 30px auto; color: #cbd5e1; }

        .btn-primary {
            background-color: var(--accent); color: var(--primary);
            padding: 14px 35px; border: none; border-radius: 30px;
            font-size: 18px; font-weight: bold; cursor: pointer;
            text-decoration: none; display: inline-block; transition: all 0.3s ease;
        }
        .btn-primary:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(250, 204, 21, 0.4); }

        /* ========================================================
           MODIFIKASI: TATA LETAK FORMASI DADU 4 (2x2)
           ======================================================== */
        .instruction-section { 
            padding: 60px 5%; 
            max-width: 1000px; 
            margin: 0 auto; 
        }
        .section-title { text-align: center; font-size: 32px; margin-bottom: 40px; color: var(--primary); }
        
        .steps-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); /* Dikunci menjadi persis 2 kolom (Kiri & Kanan) */
            gap: 30px; 
            max-width: 700px; /* Membatasi lebar agar bentuk kotak dadunya proporsional & rapi */
            margin: 0 auto; /* Supaya seluruh formasi dadu berada tepat di tengah halaman */
        }
        
        .step-card { 
            background: white; 
            padding: 30px 20px; 
            border-radius: 12px; 
            text-align: center; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            border-top: 4px solid var(--accent); 
        }
        .step-number { width: 40px; height: 40px; background: var(--primary); color: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; font-weight: bold; font-size: 18px; }
        .step-card h4 { margin-bottom: 10px; color: var(--primary); }
        .step-card p { font-size: 14px; color: #64748b; line-height: 1.5; }

        /* Responsif: Jika dibuka di HP yang layarnya kecil, otomatis kembali berbaris vertikal 1 kolom agar tidak sempit */
        @media (max-width: 600px) {
            .steps-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        footer { background-color: var(--primary); color: white; padding: 40px 5%; text-align: center; border-top: 4px solid var(--accent); margin-top: 60px; }
        .footer-socials a { color: var(--accent); margin: 0 10px; text-decoration: none; font-weight: 600; }

        /* Styling Animasi Splash Screen */
        #splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background-color: var(--primary);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 1;
            transition: opacity 0.8s ease-in-out;
        }

        .splash-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            animation: spinAndGrow 1.8s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }

        .chef-hat-icon {
            width: 80px;
            height: 80px;
            fill: var(--accent);
            filter: drop-shadow(0 4px 10px rgba(250, 204, 21, 0.3));
        }

        .welcome-text {
            color: var(--accent);
            font-size: 2.5rem;
            font-weight: 800;
            text-align: center;
            letter-spacing: 2px;
            text-shadow: 0 4px 15px rgba(250, 204, 21, 0.3);
        }

        @keyframes spinAndGrow {
            0% { transform: rotate(-180deg) scale(0.3); opacity: 0; }
            70% { transform: rotate(10deg) scale(1.1); opacity: 1; }
            100% { transform: rotate(0deg) scale(1); opacity: 1; }
        }

        .fade-out { opacity: 0 !important; pointer-events: none; }

        #dashboard-wrapper {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.8s ease-in-out;
        }

        #dashboard-wrapper.show-dashboard { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>

    <!-- Splash Screen -->
    <div id="splash-screen">
        <div class="splash-content">
            <svg class="chef-hat-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 2A4.5 4.5 0 0 0 8.05 4.29a4.5 4.5 0 0 0-3.3 6.94A2 2 0 0 0 4 13v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2a2 2 0 0 0-.75-1.77 4.5 4.5 0 0 0-3.3-6.94A4.5 4.5 0 0 0 12 2zm6 17H6v2h12v-2z"/>
            </svg>
            <div class="welcome-text">Selamat Datang DI KOEPAT</div>
        </div>
    </div>

    <!-- Konten Dashboard Utama -->
    <div id="dashboard-wrapper">

       <header style="background-color: #0f172a; padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #facc15;">
    <h1 style="font-size: 24px; color: #facc15; margin: 0;">KOEPAT Kuliner 24</h1>
    
    <nav style="display: flex; gap: 15px; align-items: center;">
        <a href="index.php" style="font-weight: 600; color: #cbd5e1; text-decoration: none;">Beranda</a>
        <a href="login_admin.php" style="background-color: #facc15; color: #0f172a; text-decoration: none; font-weight: bold; padding: 6px 14px; border-radius: 6px; font-size: 14px;">Admin</a>
    </nav>
</header>

        <!-- Banner Area -->
<div class="banner-container">
    <img src="assets\img\banner.jpg" alt="Banner KOEPAT Kuliner" class="banner-img">
    <div class="banner-overlay"></div>
    <div class="banner-content">
        <h2>Selamat Datang di KOEPAT</h2>
        <p>Platform pemesanan online praktis Jajanan Dua Empat. Pilih menu favoritmu sekarang, bayar secara fleksibel, dan ambil langsung di resto tanpa antre!</p>
        <a href="menu.php" class="btn-primary">Ambil di UP Kuliner: Pesan Sekarang</a>
    </div>
</div>

        <!-- Instruksi Pemesanan (FORMASI DADU 4) -->
        <section class="instruction-section">
            <h3 class="section-title">Cara Mudah Memesan</h3>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <h4>Pilih Menu</h4>
                    <p>Klik tombol pesan sekarang dan cari menu jajanan KOEPAT yang kamu inginkan.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">2</div>
                    <h4>Isi Detail</h4>
                    <p>Masukkan Nama, Kelas, Jam Pengambilan, Jumlah Porsi, serta Level Kepedasan.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">3</div>
                    <h4>Pembayaran</h4>
                    <p>Pilih metode bayar secara Cash langsung atau scan QRIS digital yang tersedia.</p>
                </div>
                <div class="step-card">
                    <div class="step-number">4</div>
                    <h4>Ambil Pesanan</h4>
                    <p>Tunjukkan bukti validasi sukses ke kasir pada waktu yang telah kamu tentukan.</p>
                </div>
            </div>
        </section>

        <footer>
            <div class="footer-content">
                <h4>KOEPAT Kuliner Dua Empat</h4>
                <p style="font-size: 14px; color: #cbd5e1; margin-top: 5px;">Menyajikan kudapan lezat, higienis, dan terjangkau.</p>
                <div class="footer-socials">
                    <a href="#">Instagram: @kuliner24jkt_</a> | 
                    <a href="#">WhatsApp: +62 811-9631-0643</a>
                </div>
            </div>
        </footer>

    </div> 

    <!-- JavaScript Transisi Splash Screen -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const splash = document.getElementById('splash-screen');
            const wrapper = document.getElementById('dashboard-wrapper');

            setTimeout(() => {
                splash.classList.add('fade-out');
                wrapper.classList.add('show-dashboard');
                setTimeout(() => {
                    splash.remove();
                }, 800);
            }, 2500); 
        });
    </script>

</body>
</html>