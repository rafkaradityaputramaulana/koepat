<?php
session_start();
include 'db.php';

// Proteksi Halaman Admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php");
    exit;
}

$pesan = "";
$tipe_pesan = "";

// 1. PROSES TAMBAH MENU BARU
if (isset($_POST['tambah_menu'])) {
    $nama_menu = trim($_POST['nama_menu']);
    $harga = intval($_POST['harga']);
    $deskripsi = trim($_POST['deskripsi']);
    $gambar = trim($_POST['gambar']);

    if (!empty($nama_menu) && $harga > 0) {
        $stmt_add = $conn->prepare("INSERT INTO menu (nama_Makanan, harga, deskripsi, gambar) VALUES (?, ?, ?, ?)");
        $stmt_add->bind_param("siss", $nama_menu, $harga, $deskripsi, $gambar);
        
        if ($stmt_add->execute()) {
            $pesan = "Menu baru berhasil ditambahkan!";
            $tipe_pesan = "success";
        } else {
            $pesan = "Gagal menambahkan menu.";
            $tipe_pesan = "danger";
        }
    } else {
        $pesan = "Mohon isi nama menu dan harga dengan benar.";
        $tipe_pesan = "danger";
    }
}

// 2. PROSES HAPUS SATU PESANAN
if (isset($_GET['hapus_pesanan'])) {
    $id_p = intval($_GET['hapus_pesanan']);
    
    // Hapus relasi detail & pembayaran terlebih dahulu
    $conn->query("DELETE FROM detailpesanan WHERE id_Pesanan = $id_p");
    $conn->query("DELETE FROM pembayaran WHERE id_Pesanan = $id_p");
    
    // Hapus pesanan utama
    $stmt_del = $conn->prepare("DELETE FROM pesanan WHERE id_Pesanan = ?");
    $stmt_del->bind_param("i", $id_p);
    
    if ($stmt_del->execute()) {
        $pesan = "Data pesanan #$id_p berhasil dihapus.";
        $tipe_pesan = "success";
    }
}

// 3. PROSES BERSIHKAN / HAPUS SEMUA PESANAN (SUPAYA TIDAK NUMPUK)
if (isset($_POST['hapus_semua_pesanan'])) {
    $conn->query("DELETE FROM detailpesanan");
    $conn->query("DELETE FROM pembayaran");
    $conn->query("DELETE FROM pesanan");
    
    $pesan = "Semua histori pesanan berhasil dibersihkan!";
    $tipe_pesan = "success";
}

// Rekap Total Pemasukan
$query_total = "SELECT SUM(total_bayar) AS total_pemasukan FROM pembayaran WHERE status_Pembayaran = 'Lunas'";
$res_total = $conn->query($query_total);
$total_pemasukan = $res_total->fetch_assoc()['total_pemasukan'] ?? 0;

// Rekap Jumlah Transaksi
$query_count = "SELECT COUNT(*) AS total_transaksi FROM pembayaran WHERE status_Pembayaran = 'Lunas'";
$res_count = $conn->query($query_count);
$total_transaksi = $res_count->fetch_assoc()['total_transaksi'] ?? 0;

// Ambil Semua Data Transaksi
$query_orders = "
    SELECT p.id_Pesanan, p.nama_Pembeli, p.kelas_Pembeli, p.catatan, p.tanggal_Pesanan, p.waktu_Pengambilan,
           pem.metode_Pembayaran, pem.total_bayar, pem.status_Pembayaran
    FROM pesanan p
    INNER JOIN pembayaran pem ON p.id_Pesanan = pem.id_Pesanan
    WHERE pem.status_Pembayaran = 'Lunas'
    ORDER BY p.tanggal_Pesanan DESC
";
$orders = $conn->query($query_orders);
?>
<!DOCTYPE html>
<html lang="id"> 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - KOEPAT</title>
    <style>
        :root { --primary: #0f172a; --accent: #facc15; --light: #f1f5f9; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }
        body { background: var(--light); color: #334155; padding-bottom: 50px; }
        
        header { background: var(--primary); color: white; padding: 15px 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 4px solid var(--accent); }
        .logo-brand { display: flex; align-items: center; gap: 10px; }
        .logo-brand img { height: 40px; width: auto; }
        .logo-brand h1 { font-size: 20px; color: var(--accent); }

        .btn-logout { background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; font-size: 14px; }
        
        .container { max-width: 1100px; margin: 30px auto; padding: 0 20px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border-left: 6px solid var(--accent); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 14px; color: #64748b; margin-bottom: 8px; }
        .stat-card .value { font-size: 24px; font-weight: 800; color: var(--primary); }

        .table-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .table-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .table-card h2 { font-size: 18px; color: var(--primary); }

        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { background: #f8fafc; padding: 12px; border-bottom: 2px solid #e2e8f0; font-weight: 600; color: #475569; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }

        .badge { padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: bold; display: inline-block; }
        .badge-cash { background: #e0f2fe; color: #0369a1; }
        .badge-qris { background: #fef3c7; color: #b45309; }
        .badge-success { background: #dcfce7; color: #15803d; }

        .btn-action-danger { background: #ef4444; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 12px; border: none; cursor: pointer; }
        .btn-action-danger:hover { background: #dc2626; }
        .btn-primary-add { background: var(--primary); color: white; padding: 10px 18px; border-radius: 6px; border: none; font-weight: bold; cursor: pointer; }
        .btn-primary-add:hover { background: var(--accent); color: var(--primary); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: span 2; }
        .form-control { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; font-size: 14px; }

        .alert-box { padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
    </style>
</head>
<body>

    <header>
        <div class="logo-brand">
            <h1>Panel Admin KOEPAT</h1>
        </div>
        <div>
            <span style="margin-right: 15px;">Halo, <strong><?php echo htmlspecialchars($_SESSION['admin_user']); ?></strong></span>
            <a href="logout_admin.php" class="btn-logout">Logout</a>
        </div>
    </header>

    <div class="container">
        
        <?php if (!empty($pesan)): ?>
            <div class="alert-box alert-<?php echo $tipe_pesan; ?>">
                <?php echo $pesan; ?>
            </div>
        <?php endif; ?>

        <!-- Statistik Uang & Transaksi -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>TOTAL PEMASUKAN AUTOMATIS</h3>
                <div class="value" style="color: #16a34a;">Rp <?php echo number_format($total_pemasukan, 0, ',', '.'); ?></div>
            </div>
            <div class="stat-card" style="border-left-color: #0284c7;">
                <h3>TOTAL TRANSAKSI</h3>
                <div class="value"><?php echo $total_transaksi; ?> Pesanan</div>
            </div>
        </div>

        <!-- Form Tambah Menu Baru -->
        <div class="table-card">
            <h2>Tambah Menu Makanan / Minuman Baru</h2>
            <form action="" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label><strong>Nama Menu</strong></label>
                        <input type="text" name="nama_menu" class="form-control" placeholder="Contoh: Wonton Kuah" required>
                    </div>
                    <div class="form-group">
                        <label><strong>Harga (Rp)</strong></label>
                        <input type="number" name="harga" class="form-control" placeholder="Contoh: 10000" required>
                    </div>
                    <div class="form-group full">
                        <label><strong>Path / Link Gambar</strong></label>
                        <input type="text" name="gambar" class="form-control" placeholder="Contoh: assets/img/wonton.jpg">
                    </div>
                    <div class="form-group full">
                        <label><strong>Deskripsi Singkat</strong></label>
                        <textarea name="deskripsi" class="form-control" rows="2" placeholder="Tuliskan deskripsi rasa / porsi makanan..."></textarea>
                    </div>
                </div>
                <button type="submit" name="tambah_menu" class="btn-primary-add" style="margin-top: 15px;">+ Tambah Menu Baru</button>
            </form>
        </div>

        <!-- Tabel Riwayat Pembayaran -->
        <div class="table-card">
            <div class="table-card-header">
                <h2>Daftar Pesanan & Pembayaran Masuk</h2>
                <?php if ($orders && $orders->num_rows > 0): ?>
                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus SELURUH riwayat pesanan? Data yang dihapus tidak bisa dikembalikan.');">
                        <button type="submit" name="hapus_semua_pesanan" class="btn-action-danger">Bersihkan Semua Pesanan</button>
                    </form>
                <?php endif; ?>
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Tanggal</th>
                            <th>Pembeli</th>
                            <th>Kelas</th>
                            <th>Waktu Ambil</th>
                            <th>Metode</th>
                            <th>Total Bayar</th>
                            <th>Status</th>
                            <th>Catatan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders && $orders->num_rows > 0): ?>
                            <?php while ($row = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $row['id_Pesanan']; ?></strong></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($row['tanggal_Pesanan'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['nama_Pembeli']); ?></td>
                                    <td><?php echo htmlspecialchars($row['kelas_Pembeli']); ?></td>
                                    <td><?php echo htmlspecialchars($row['waktu_Pengambilan']); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($row['metode_Pembayaran'] === 'QRIS') ? 'badge-qris' : 'badge-cash'; ?>">
                                            <?php echo htmlspecialchars($row['metode_Pembayaran']); ?>
                                        </span>
                                    </td>
                                    <td><strong>Rp <?php echo number_format($row['total_bayar'], 0, ',', '.'); ?></strong></td>
                                    <td><span class="badge badge-success">LUNAS</span></td>
                                    <td style="font-size: 12px; color: #64748b; max-width: 200px;"><?php echo htmlspecialchars($row['catatan']); ?></td>
                                    <td>
                                        <a href="?hapus_pesanan=<?php echo $row['id_Pesanan']; ?>" class="btn-action-danger" onclick="return confirm('Hapus transaksi ini?')">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #94a3b8; padding: 20px;">Belum ada pesanan yang sudah dibayar.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>