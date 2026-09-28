<?php
session_start();
include 'db.php';

// Validasi ID Menu dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: menu.php");
    exit;
}

$id_menu = intval($_GET['id']);
$stmt_menu = $conn->prepare("SELECT * FROM menu WHERE id_Menu = ?");
$stmt_menu->bind_param("i", $id_menu);
$stmt_menu->execute();
$menu = $stmt_menu->get_result()->fetch_assoc();

if (!$menu) {
    echo "Menu tidak ditemukan!";
    exit;
}

// Ambil Nama & Harga dari Database
$nama_menu = $menu['nama_Makanan'];
$harga_menu = intval($menu['harga']);

// Fallback / Koreksi Nama & Harga Otomatis berdasarkan Kata Kunci
$nama_lc = strtolower($nama_menu);

if (strpos($nama_lc, 'wonton') !== false) {
    $nama_menu = "Wonton";
    $harga_menu = 10000;
} elseif (strpos($nama_lc, 'sakura') !== false || strpos($nama_lc, 'indomie') !== false) {
    $nama_menu = "Mie Sakura";
    $harga_menu = 4000;
} elseif (strpos($nama_lc, 'martabak') !== false) {
    $nama_menu = "Martabak Telor";
    $harga_menu = 5000;
} elseif (strpos($nama_lc, 'cheese') !== false || strpos($nama_lc, 'chese') !== false) {
    $nama_menu = "Cheese Roll";
    $harga_menu = 1000;
} elseif (strpos($nama_lc, 'air') !== false || strpos($nama_lc, 'mineral') !== false) {
    if (strpos($nama_lc, 'besar') !== false || strpos($nama_lc, 'gede') !== false) {
        $nama_menu = "Air Mineral Gede";
        $harga_menu = 5000;
    } else {
        $nama_menu = "Air Mineral Kecil";
        $harga_menu = 3000;
    }
}

/* ========================================================
   1. PEMETAAN GAMBAR OTOMATIS BERDASARKAN KATA KUNCI
   ======================================================== */
$gambar_src = "";

if (!empty($menu['gambar'])) {
    $gambar_src = $menu['gambar'];
} else {
    if (strpos($nama_lc, 'wonton') !== false) {
        $gambar_src = "assets/img/wonton.jpg";
    } elseif (strpos($nama_lc, 'sakura') !== false || strpos($nama_lc, 'indomie') !== false) {
        $gambar_src = "assets/img/Mie Sakura.jpg";
    } elseif (strpos($nama_lc, 'martabak') !== false) {
        $gambar_src = "assets/img/martabak_telor.jpg";
    } elseif (strpos($nama_lc, 'cheese') !== false || strpos($nama_lc, 'chese') !== false) {
        $gambar_src = "assets/img/CHESEROLL.jpg";
    } elseif (strpos($nama_lc, 'air') !== false || strpos($nama_lc, 'mineral') !== false) {
        $gambar_src = "assets/img/air_besar.jpg";
    } else {
        $gambar_src = "default_food.jpg"; 
    }
}

// Logika Tambah ke Session / Keranjang Sementara
if (isset($_POST['place_order'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $nama = $_POST['nama'];
    $kelas = $_POST['kelas'];
    $nohp = $_POST['nohp'];
    $waktu = $_POST['waktu'];
    $jumlah = intval($_POST['jumlah']);
    $level = isset($_POST['level']) ? $_POST['level'] : 'Tidak Ada Level';
    $catatan_raw = $_POST['catatan'];

    // Simpan data pemesan ke session pelanggan
    $_SESSION['customer_info'] = [
        'nama' => $nama,
        'kelas' => $kelas,
        'nohp' => $nohp,
        'waktu' => $waktu
    ];

    $subtotal = $harga_menu * $jumlah;

    // Simpan item pesanan ke session cart
    $_SESSION['cart'][] = [
        'id_menu' => $id_menu,
        'nama_menu' => $nama_menu,
        'harga' => $harga_menu,
        'jumlah' => $jumlah,
        'subtotal' => $subtotal,
        'level' => $level,
        'catatan_raw' => $catatan_raw,
        'gambar' => $gambar_src
    ];

    header("Location: checkout.php");
    exit;
}

// Ambil info pembeli dari session jika sudah ada sebelumnya
$cust = $_SESSION['customer_info'] ?? ['nama' => '', 'kelas' => '', 'nohp' => '', 'waktu' => ''];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Detail Pesanan - KOEPAT</title>
    <style>
        :root { 
            --primary: #0f172a; 
            --accent: #facc15; 
            --light: #f1f5f9; 
            --text-dark: #334155; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--light); color: var(--text-dark); padding-bottom: 60px; }

        header {
            background-color: var(--primary); color: white; padding: 20px 5%;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 4px solid var(--accent); margin-bottom: 30px;
        }
        header h1 { font-size: 24px; color: var(--accent); }

        .container { 
            max-width: 700px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            overflow: hidden; 
            border: 4px solid var(--accent);
        }
        .form-body { padding: 30px; }

        .product-summary { display: flex; gap: 20px; background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #e2e8f0; align-items: center; }
        
        .product-img-box { 
            width: 110px; 
            height: 110px; 
            background: #e2e8f0; 
            border-radius: 10px; 
            overflow: hidden; 
            flex-shrink: 0;
            border: 2px solid var(--accent);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .product-img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
            display: block;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
        .form-control { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; font-size: 15px; background-color: #fff; }

        .qty-container { display: flex; align-items: center; gap: 10px; }
        .qty-btn { background: #e2e8f0; border: none; width: 40px; height: 40px; border-radius: 8px; font-size: 20px; font-weight: bold; cursor: pointer; }
        
        .level-container { display: flex; gap: 10px; }
        .level-radio { display: none; }
        .level-label { padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; font-weight: bold; transition: all 0.2s ease; text-align: center; flex: 1; }
        
        #lv1:checked + .level-label { background: #84cc16; border-color: #84cc16; color: white; }
        #lv2:checked + .level-label { background: #f97316; border-color: #f97316; color: white; }
        #lv3:checked + .level-label { background: #ef4444; border-color: #ef4444; color: white; }
        #lv4:checked + .level-label { background: #991b1b; border-color: #991b1b; color: white; }

        .payment-options { display: grid; grid-template-columns: 1fr; gap: 15px; }
        .pay-method-label { border: 2px solid #0284c7; background-color: #f0f9ff; padding: 15px; border-radius: 8px; text-align: center; cursor: pointer; font-weight: bold; display: block; }
        .pay-radio { display: none; }

        .qris-box { display: block; text-align: center; margin-top: 15px; background: #f8fafc; padding: 20px; border: 2px dashed #cbd5e1; border-radius: 8px; }

        .total-price-box {
            background-color: #f8fafc;
            border: 1px dashed var(--accent);
            border-left: 5px solid var(--accent);
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total-price-box span { font-weight: 600; color: #64748b; }
        .total-price-box .price-display { font-size: 20px; font-weight: 800; color: #0284c7; }

        .btn-submit { background-color: var(--primary); color: white; width: 100%; padding: 15px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background 0.2s; }
        .btn-submit:hover { background-color: var(--accent); color: var(--primary); }
    </style>
</head>
<body>

    <header>
        <h1>Detail Pemesanan</h1>
        <a href="menu.php" style="color: white; text-decoration: none; font-weight: bold;">Kembali</a>
    </header>

    <div class="container">
        <form action="" method="POST">
            <div class="form-body">
                <div class="product-summary">
                    <div class="product-img-box">
                        <img src="<?php echo $gambar_src; ?>" alt="<?php echo htmlspecialchars($nama_menu); ?>" class="product-img">
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($nama_menu); ?></h3>
                        <p style="font-weight: bold; color: #0284c7; margin: 4px 0;">Rp <?php echo number_format($harga_menu, 0, ',', '.'); ?></p>
                        <p style="font-size: 13px; color: #64748b;"><?php echo isset($menu['deskripsi']) ? htmlspecialchars($menu['deskripsi']) : ''; ?></p>
                    </div>
                </div>

                <div class="form-group">
                    <label>Nama Pembeli</label>
                    <input type="text" name="nama" class="form-control" placeholder="Tulis nama lengkap Anda" value="<?php echo htmlspecialchars($cust['nama']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <input type="text" name="kelas" class="form-control" placeholder="Contoh: XII Kuliner 1" value="<?php echo htmlspecialchars($cust['kelas']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Nomor HP / WhatsApp</label>
                    <input type="tel" name="nohp" class="form-control" placeholder="Contoh: 08XXXXXXXXXX" value="<?php echo htmlspecialchars($cust['nohp']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Waktu Pengambilan</label>
                    <select name="waktu" class="form-control" required>
                        <option value="" disabled <?php echo empty($cust['waktu']) ? 'selected' : ''; ?>>-- Pilih Waktu Pengambilan --</option>
                        <option value="Jam Istirahat Pertama" <?php echo ($cust['waktu'] == 'Jam Istirahat Pertama') ? 'selected' : ''; ?>>Jam Istirahat pertama</option>
                        <option value="Jam Istirahat Kedua" <?php echo ($cust['waktu'] == 'Jam Istirahat Kedua') ? 'selected' : ''; ?>>Jam Istirahat kedua</option>
                        <option value="Pulang Sekolah" <?php echo ($cust['waktu'] == 'Pulang Sekolah') ? 'selected' : ''; ?>>Pulang Sekolah</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Jumlah Pesanan</label>
                    <div class="qty-container">
                        <button type="button" class="qty-btn" onclick="adjustQty(-1)">-</button>
                        <input type="number" name="jumlah" id="orderQty" class="form-control" value="1" min="1" style="width: 80px; text-align: center;" readonly>
                        <button type="button" class="qty-btn" onclick="adjustQty(1)">+</button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Level Kepedasan (Opsional)</label>
                    <div class="level-container">
                        <input type="radio" name="level" value="Level 1" id="lv1" class="level-radio" checked>
                        <label for="lv1" class="level-label">1</label>
                        
                        <input type="radio" name="level" value="Level 2" id="lv2" class="level-radio">
                        <label for="lv2" class="level-label">2</label>
                        
                        <input type="radio" name="level" value="Level 3" id="lv3" class="level-radio">
                        <label for="lv3" class="level-label">3</label>
                        
                        <input type="radio" name="level" value="Level 4" id="lv4" class="level-radio">
                        <label for="lv4" class="level-label">4</label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Catatan Tambahan</label>
                    <textarea name="catatan" class="form-control" rows="2" placeholder="contoh: aku gapake sumpit ya kak"></textarea>
                </div>

                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 25px 0;">

                <div class="form-group">
                    <label style="font-size: 16px;">Metode Pembayaran</label>
                    <div class="payment-options">
                        <div>
                            <input type="radio" name="metode_bayar" value="QRIS" id="payQris" class="pay-radio" checked>
                            <label for="payQris" class="pay-method-label">ONLY QRIS DIGITAL</label>
                        </div>
                    </div>

                    <div class="qris-box" id="qrisBox">
                        <p style="font-weight: bold; font-size: 14px;">QRIS akan dibuat otomatis oleh Midtrans.</p>
                        <p style="font-size: 13px; color: #64748b; margin-top: 8px;">Setelah menekan <strong>Lanjutkan Pesanan</strong>, halaman pembayaran QRIS akan terbuka dan status pembayaran akan diverifikasi otomatis.</p>
                    </div>
                </div>

                <div class="total-price-box">
                    <span>Total Pembayaran (<span id="qtySummary">1</span> barang):</span>
                    <div class="price-display" id="totalPriceDisplay">Rp <?php echo number_format($harga_menu, 0, ',', '.'); ?></div>
                </div>

                <button type="submit" name="place_order" class="btn-submit">Lanjutkan Pesanan</button>
            </div>
        </form>
    </div>

    <script>
        const hargaSatuan = <?php echo $harga_menu; ?>;
        const qtyInput = document.getElementById('orderQty');
        const qtySummary = document.getElementById('qtySummary');
        const totalPriceDisplay = document.getElementById('totalPriceDisplay');

        function formatRupiah(angka) {
            return 'Rp ' + angka.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateTotalPrice() {
            const currentQty = parseInt(qtyInput.value);
            const total = hargaSatuan * currentQty;
            
            qtySummary.textContent = currentQty;
            totalPriceDisplay.textContent = formatRupiah(total);
        }

        function adjustQty(amount) {
            let val = parseInt(qtyInput.value) + amount;
            if (val < 1) val = 1;
            qtyInput.value = val;
            
            updateTotalPrice();
        }
    </script>
</body>
</html>