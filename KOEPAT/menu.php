<?php
include 'db.php';

$search = isset($_GET['search']) ? $_GET['search'] : '';
if (!empty($search)) {
    $stmt = $conn->prepare("SELECT * FROM menu WHERE nama_Makanan LIKE ?");
    $search_term = "%" . $search . "%";
    $stmt->bind_param("s", $search_term);
    $stmt->execute();
    $result_menu = $stmt->get_result();
} else {
    $result_menu = $conn->query("SELECT * FROM menu");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pesanan - KOEPAT Kuliner</title>
    <style>
        :root {
            --primary: #0f172a; --accent: #facc15; --light: #f8fafc; --text-dark: #334155;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background-color: var(--light); color: var(--text-dark); }

        header {
            background-color: var(--primary); color: white; padding: 20px 5%;
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 4px solid var(--accent);
        }
        header h1 { font-size: 24px; color: var(--accent); }
        .back-home { color: white; text-decoration: none; font-weight: bold; font-size: 14px; }

        .menu-section { padding: 40px 5%; max-width: 1200px; margin: 0 auto; }
        
        /* Search Bar & Reset Button */
        .search-container { margin-bottom: 40px; display: flex; justify-content: center; }
        .search-container form { display: flex; width: 100%; max-width: 600px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); border-radius: 30px; overflow: hidden; background: white; border: 2px solid #e2e8f0; position: relative; }
        .search-input { flex: 1; padding: 15px 50px 15px 25px; border: none; outline: none; font-size: 16px; border-radius: 30px 0 0 30px; }
        
        .clear-search {
            position: absolute;
            right: 90px;
            top: 50%;
            transform: translateY(-50%);
            background: #e2e8f0;
            color: #64748b;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.2s;
        }
        .clear-search:hover { background: #cbd5e1; color: var(--primary); }

        .search-btn { background-color: var(--primary); color: white; border: none; padding: 0 25px; cursor: pointer; font-weight: bold; z-index: 2; border-radius: 0 30px 30px 0; transition: background 0.2s; }
        .search-btn:hover { background-color: #1e293b; }

        /* Menu Grid */
        .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; }
        .menu-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; display: flex; flex-direction: column; }
        
        .menu-img-box {
            width: 100%; height: 200px; background-color: #cbd5e1;
            display: flex; align-items: center; justify-content: center; text-align: center;
            position: relative; overflow: hidden; border-bottom: 1px solid #e2e8f0;
        }
        .menu-img { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 1; }
        .placeholder-text { font-size: 13px; color: #64748b; padding: 20px; font-style: italic; position: relative; z-index: 2; }

        .menu-info { padding: 20px; display: flex; flex-direction: column; flex: 1; }
        .menu-name { font-size: 20px; color: var(--primary); margin-bottom: 8px; }
        .menu-price { font-size: 18px; font-weight: bold; color: #0284c7; margin-bottom: 15px; }

        .btn-order-item {
            background-color: var(--primary); color: white; width: 100%; padding: 12px;
            border: none; border-radius: 8px; cursor: pointer; font-weight: 600; text-align: center;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-order-item:hover { background-color: var(--accent); color: var(--primary); }

        footer { background-color: var(--primary); color: white; padding: 40px 5%; text-align: center; border-top: 4px solid var(--accent); margin-top: 60px; }
    </style>
</head>
<body>

    <header style="background-color: #0f172a; padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid #facc15;">
    <h1 style="font-size: 24px; color: #facc15; margin: 0;">KOEPAT Kuliner 24</h1>
    
    <nav style="display: flex; gap: 15px; align-items: center;">
        <a href="index.php" style="font-weight: 600; color: #cbd5e1; text-decoration: none;">Beranda</a>
        <a href="login_admin.php" style="background-color: #facc15; color: #0f172a; text-decoration: none; font-weight: bold; padding: 6px 14px; border-radius: 6px; font-size: 14px;">Admin</a>
    </nav>
</header>

    <section class="menu-section">
        <!-- Search Bar -->
        <div class="search-container">
            <form action="menu.php" method="GET">
                <input type="text" name="search" class="search-input" placeholder="Cari makanan favoritmu disini..." value="<?php echo htmlspecialchars($search); ?>">
                
                <?php if (!empty($search)): ?>
                    <a href="menu.php" class="clear-search" title="Hapus Pencarian">&times;</a>
                <?php endif; ?>
                
                <button type="submit" class="search-btn">Cari</button>
            </form>
        </div>

        <div class="menu-grid">
            <?php
            if ($result_menu->num_rows > 0) {
                while($row = $result_menu->fetch_assoc()) {
                    $nama_raw = strtolower($row['nama_Makanan']);
                    
                    // --- PENYESUAIAN NAMA & HARGA BENAR ---
                    $nama_tampil = $row['nama_Makanan']; // Fallback
                    $harga_tampil = intval($row['harga']); // Fallback
                    $gambar_src = "";
                    $show_placeholder = true;

                if (strpos($nama_raw, 'wonton') !== false) {

    $nama_tampil = "Wonton";
    $harga_tampil = 10000;
    $gambar_src = "assets/img/wonton.jpg";
    $show_placeholder = false;

}
elseif (
    strpos($nama_raw,'indomie') !== false ||
    strpos($nama_raw,'sakura') !== false
){

    $nama_tampil = "Mie Sakura";
    $harga_tampil = 4000;
    $gambar_src = "assets/img/Mie Sakura.jpg";
    $show_placeholder = false;

}
elseif (strpos($nama_raw,'martabak') !== false){

    $nama_tampil = "Martabak Telor";
    $harga_tampil = 5000;
    $gambar_src = "assets/img/martabak_telor.jpg";
    $show_placeholder = false;

}
elseif (
    strpos($nama_raw,'cheese') !== false ||
    strpos($nama_raw,'chese') !== false
){

    $nama_tampil = "Cheese Roll";
    $harga_tampil = 1000;
    $gambar_src = "assets/img/CHESEROLL.jpg";
    $show_placeholder = false;

}
elseif (
    strpos($nama_raw,'air') !== false ||
    strpos($nama_raw,'mineral') !== false
){

    if (
        strpos($nama_raw,'gede') !== false ||
        strpos($nama_raw,'besar') !== false
    ){

        $nama_tampil = "Air Mineral Gede";
        $harga_tampil = 5000;
        $gambar_src = "assets/img/air_besar.jpg";

    }else{

        $nama_tampil = "Air Mineral Kecil";
        $harga_tampil = 3000;
        $gambar_src = "assets/img/air_kecil.jpg";

    }

    $show_placeholder = false;
}
                    ?>
                    <div class="menu-card">
                        <div class="menu-img-box">

    <?php if (!$show_placeholder) { ?>

        <img src="<?php echo $gambar_src; ?>"
             alt="<?php echo htmlspecialchars($nama_tampil); ?>"
             class="menu-img">

    <?php } else { ?>

        <span class="placeholder-text">
            Gambar tidak tersedia
        </span>

    <?php } ?>

</div>
                        <div class="menu-info">
                            <!-- NAMA MENJADI SESUAI FORMAT TERBARU -->
                            <h4 class="menu-name"><?php echo htmlspecialchars($nama_tampil); ?></h4>
                            <!-- HARGA MENJADI SESUAI FORMAT TERBARU -->
                            <div class="menu-price">Rp <?php echo number_format($harga_tampil, 0, ',', '.'); ?></div>
                            
                            <a href="proses_pesan.php?id=<?php echo $row['id_Menu']; ?>" class="btn-order-item">Pesan Makanan</a>
                        </div>
                    </div>
                    <?php
                }
            } else {
                echo "<p style='text-align:center; grid-column: 1/-1;'>Makanan tidak ditemukan.</p>";
            }
            ?>
        </div>
    </section>

    <footer>
        <p>&copy; 2026 KOEPAT Kuliner Dua Empat. Media Sosial: @koepat.kuliner24</p>
    </footer>

</body>
</html>