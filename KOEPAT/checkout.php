<?php
session_start();

include 'db.php';
require_once 'midtrans_config.php';
require_once 'midtrans_create.php';

/*
|--------------------------------------------------------------------------
| CEK KERANJANG
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: menu.php");
    exit;
}

$cust = $_SESSION['customer_info'];
$cart = $_SESSION['cart'];

/*
|--------------------------------------------------------------------------
| HITUNG TOTAL
|--------------------------------------------------------------------------
*/

$total_semua = 0;

foreach ($cart as $item) {
    $total_semua += (int) $item['subtotal'];
}

/*
|--------------------------------------------------------------------------
| VARIABLE
|--------------------------------------------------------------------------
*/

$order_success = false;

$opsi_pengiriman = '';

$qr_url = '';

$midtrans_transaction_id = '';

$midtrans_order_id = '';

$error_checkout = '';

/*
|--------------------------------------------------------------------------
| PROSES CHECKOUT
|--------------------------------------------------------------------------
*/

if (isset($_POST['confirm_checkout'])) {

    $nama = $cust['nama'];
    $kelas = $cust['kelas'];
    $nohp = $cust['nohp'];
    $waktu = $cust['waktu'];

    $metode_pengiriman = $_POST['opsi_pengiriman'] ?? 'Ambil Langsung';

    $opsi_pengiriman = $metode_pengiriman;

    /*
    |----------------------------------------------------------------------
    | Gabungkan catatan pesanan
    |----------------------------------------------------------------------
    */

    $catatan_gabungan =
        "[Pengiriman: " .
        strtoupper($metode_pengiriman) .
        "] ";

    foreach ($cart as $item) {

        $catatan_gabungan .=
            "[" .
            $item['nama_menu'] .
            " - Level: " .
            $item['level'] .
            " | " .
            $item['catatan_raw'] .
            "] ";
    }

    /*
    |--------------------------------------------------------------------------
    | MULAI TRANSAKSI DATABASE
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();

    try {

        /*
        |----------------------------------------------------------------------
        | 1. INSERT PESANAN
        |----------------------------------------------------------------------
        */

        $stmt_order = $conn->prepare("
            INSERT INTO pesanan
            (
                nama_Pembeli,
                kelas_Pembeli,
                nomor_Kontak,
                catatan,
                tanggal_Pesanan,
                waktu_Pengambilan
            )
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");

        if (!$stmt_order) {
            throw new Exception(
                'Prepare pesanan gagal: ' . $conn->error
            );
        }

        $stmt_order->bind_param(
            "sssss",
            $nama,
            $kelas,
            $nohp,
            $catatan_gabungan,
            $waktu
        );

        if (!$stmt_order->execute()) {
            throw new Exception(
                'Gagal menyimpan pesanan: ' .
                $stmt_order->error
            );
        }

        $id_pesanan = $conn->insert_id;

        $stmt_order->close();

        /*
        |----------------------------------------------------------------------
        | 2. BUAT ORDER ID MIDTRANS
        |----------------------------------------------------------------------
        */

        $midtrans_order_id =
            'KOEPAT-' .
            $id_pesanan .
            '-' .
            date('YmdHis');

        /*
        |----------------------------------------------------------------------
        | 3. INSERT DETAIL PESANAN
        |----------------------------------------------------------------------
        */

        $stmt_detail = $conn->prepare("
            INSERT INTO detailpesanan
            (
                id_Pesanan,
                id_Menu,
                jumlah,
                subtotal
            )
            VALUES (?, ?, ?, ?)
        ");

        if (!$stmt_detail) {
            throw new Exception(
                'Prepare detail pesanan gagal: ' .
                $conn->error
            );
        }

        foreach ($cart as $item) {

            $id_menu = (int) $item['id_menu'];
            $jumlah = (int) $item['jumlah'];
            $subtotal = (int) $item['subtotal'];

            $stmt_detail->bind_param(
                "iiii",
                $id_pesanan,
                $id_menu,
                $jumlah,
                $subtotal
            );

            if (!$stmt_detail->execute()) {

                throw new Exception(
                    'Gagal menyimpan detail pesanan: ' .
                    $stmt_detail->error
                );
            }
        }

        $stmt_detail->close();

        /*
        |----------------------------------------------------------------------
        | 4. INSERT PEMBAYARAN
        |----------------------------------------------------------------------
        */

        $status_pending = 'Belum bayar';

        $stmt_pay = $conn->prepare("
            INSERT INTO pembayaran
            (
                id_Pesanan,
                metode_Pembayaran,
                total_bayar,
                status_Pembayaran,
                midtrans_order_id
            )
            VALUES (?, 'QRIS', ?, ?, ?)
        ");

        if (!$stmt_pay) {

            throw new Exception(
                'Prepare pembayaran gagal: ' .
                $conn->error
            );
        }

        $stmt_pay->bind_param(
            "iiss",
            $id_pesanan,
            $total_semua,
            $status_pending,
            $midtrans_order_id
        );

        if (!$stmt_pay->execute()) {

            throw new Exception(
                'Gagal menyimpan pembayaran: ' .
                $stmt_pay->error
            );
        }

        $stmt_pay->close();

        /*
        |----------------------------------------------------------------------
        | 5. BUAT TRANSAKSI MIDTRANS
        |----------------------------------------------------------------------
        */

        $midtrans = createMidtransTransaction(
            $midtrans_order_id,
            $total_semua,
            $cust,
            $cart
        );

        if (!is_array($midtrans)) {

            throw new Exception(
                'Response Midtrans tidak valid.'
            );
        }

        if (
            !isset($midtrans['success']) ||
            $midtrans['success'] !== true
        ) {

            throw new Exception(
                $midtrans['message']
                ?? 'Gagal membuat transaksi Midtrans.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | 6. AMBIL DATA MIDTRANS
        |----------------------------------------------------------------------
        */

        $qr_url =
            $midtrans['qr_url']
            ?? '';

        $midtrans_transaction_id =
            $midtrans['transaction_id']
            ?? '';

        if (empty($qr_url)) {

            throw new Exception(
                'QRIS berhasil dibuat tetapi URL QR tidak ditemukan.'
            );
        }

        /*
        |----------------------------------------------------------------------
        | 7. UPDATE TRANSACTION ID
        |----------------------------------------------------------------------
        */

        $stmt_update_pay = $conn->prepare("
            UPDATE pembayaran

            SET midtrans_transaction_id = ?

            WHERE midtrans_order_id = ?
        ");

        if (!$stmt_update_pay) {

            throw new Exception(
                'Prepare update pembayaran gagal: ' .
                $conn->error
            );
        }

        $stmt_update_pay->bind_param(
            "ss",
            $midtrans_transaction_id,
            $midtrans_order_id
        );

        if (!$stmt_update_pay->execute()) {

            throw new Exception(
                'Gagal menyimpan Transaction ID: ' .
                $stmt_update_pay->error
            );
        }

        $stmt_update_pay->close();

        /*
        |----------------------------------------------------------------------
        | 8. COMMIT
        |----------------------------------------------------------------------
        */

        $conn->commit();

        $order_success = true;

        /*
        |----------------------------------------------------------------------
        | HAPUS SESSION
        |----------------------------------------------------------------------
        */

        unset($_SESSION['cart']);
        unset($_SESSION['customer_info']);

    } catch (Throwable $e) {

        $conn->rollback();

        $error_checkout = $e->getMessage();
    }
}

?>
<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Ringkasan Pesanan - KOEPAT</title>

<style>

:root {

    --primary: #0f172a;
    --accent: #facc15;
    --light: #f1f5f9;
    --text-dark: #334155;

}

* {

    margin: 0;
    padding: 0;
    box-sizing: border-box;

    font-family: 'Segoe UI', sans-serif;
}

body {

    background-color: var(--light);

    color: var(--text-dark);

    padding-bottom: 60px;
}

/* HEADER */

header {

    background-color: var(--primary);

    color: white;

    padding: 20px 5%;

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-bottom: 4px solid var(--accent);

    margin-bottom: 30px;
}

header h1 {

    font-size: 24px;

    color: var(--accent);
}

/* CONTAINER */

.container {

    max-width: 700px;

    margin: 0 auto;

    background: white;

    border-radius: 16px;

    box-shadow:
        0 4px 20px rgba(0,0,0,0.08);

    overflow: hidden;

    border: 4px solid var(--accent);
}

.form-body {

    padding: 30px;
}

/* PRODUCT */

.product-summary {

    display: flex;

    gap: 20px;

    background: #f8fafc;

    padding: 15px;

    border-radius: 12px;

    margin-bottom: 15px;

    border: 1px solid #e2e8f0;

    align-items: center;
}

.product-img-box {

    width: 80px;

    height: 80px;

    background: #e2e8f0;

    border-radius: 10px;

    overflow: hidden;

    flex-shrink: 0;

    border: 2px solid var(--accent);
}

.product-img {

    width: 100%;

    height: 100%;

    object-fit: cover;

    display: block;
}

/* INFO */

.info-box {

    background: #f8fafc;

    border: 1px solid #e2e8f0;

    border-radius: 8px;

    padding: 15px;

    margin-bottom: 20px;

    font-size: 14px;
}

/* SHIPPING */

.shipping-options {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;

    margin-bottom: 20px;
}

.shipping-card {

    position: relative;
}

.shipping-card input[type="radio"] {

    display: none;
}

.shipping-label {

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    padding: 14px;

    background: #f8fafc;

    border: 2px solid #cbd5e1;

    border-radius: 10px;

    cursor: pointer;

    transition: all 0.2s ease;

    font-weight: bold;

    font-size: 14px;

    color: var(--text-dark);

    text-align: center;
}

.shipping-label .sub-text {

    font-size: 11px;

    color: #64748b;

    font-weight: normal;

    margin-top: 4px;
}

.shipping-card input[type="radio"]:checked
+ .shipping-label {

    border-color: #0284c7;

    background-color: #e0f2fe;

    color: #0369a1;

    box-shadow:
        0 4px 10px rgba(2,132,199,0.15);
}

/* TOTAL */

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

.total-price-box .price-display {

    font-size: 20px;

    font-weight: 800;

    color: #0284c7;
}

/* BUTTON */

.btn-group {

    display: flex;

    gap: 10px;

    margin-top: 15px;
}

.btn-add {

    background-color: #0284c7;

    color: white;

    width: 100%;

    padding: 15px;

    border: none;

    border-radius: 8px;

    cursor: pointer;

    font-size: 16px;

    font-weight: bold;

    text-align: center;

    text-decoration: none;
}

.btn-submit {

    background-color: var(--primary);

    color: white;

    width: 100%;

    padding: 15px;

    border: none;

    border-radius: 8px;

    cursor: pointer;

    font-size: 16px;

    font-weight: bold;

    text-align: center;

    text-decoration: none;

    display: block;
}

.btn-submit:hover {

    background-color: var(--accent);

    color: var(--primary);
}

/* OVERLAY */

.success-overlay {

    position: fixed;

    top: 0;

    left: 0;

    width: 100%;

    height: 100%;

    background:
        rgba(15,23,42,0.95);

    z-index: 2000;

    display: flex;

    justify-content: center;

    align-items: center;

    text-align: center;

    overflow-y: auto;

    padding: 20px;
}

.success-card {

    background: white;

    padding: 30px;

    border-radius: 16px;

    max-width: 480px;

    width: 100%;

    border-top: 8px solid #22c55e;
}

/* QR */

.qr-container {

    margin: 20px 0;

    text-align: center;
}

.qr-box {

    background: white;

    padding: 15px;

    border-radius: 12px;

    display: inline-block;

    border: 2px solid #e2e8f0;
}

.qr-box img {

    width: 280px;

    height: 280px;

    display: block;
}

.qr-url {

    width: 100%;

    margin-top: 12px;

    padding: 10px;

    border: 1px solid #cbd5e1;

    border-radius: 6px;

    font-size: 12px;
}

/* MOBILE */

@media(max-width:600px) {

    .shipping-options {

        grid-template-columns: 1fr;
    }

    .btn-group {

        flex-direction: column;
    }

    .qr-box img {

        width: 230px;

        height: 230px;
    }

}

</style>

</head>

<body>

<!-- =========================================================
     POPUP PEMBAYARAN
========================================================= -->

<?php if ($order_success): ?>

<div
    class="success-overlay"
    id="paymentOverlay"
>

    <div class="success-card">

        <div
            id="paymentIcon"
            style="
                font-size:60px;
                color:#0284c7;
            "
        >
            ⌛
        </div>

        <h2 id="paymentTitle">

            Menunggu Pembayaran

        </h2>

        <p
            id="paymentMessage"
            style="
                margin:15px 0;
                color:#64748b;
            "
        >

            Silakan lakukan pembayaran QRIS.

        </p>

        <!-- DETAIL -->

        <div
            style="
                background:#f1f5f9;
                padding:15px;
                border-radius:8px;
                text-align:left;
                font-size:14px;
                margin-bottom:20px;
            "
        >

            <strong>
                Detail Transaksi:
            </strong>

            <br>

            Nama:
            <?php
            echo htmlspecialchars($cust['nama']);
            ?>

            <br>

            Pengiriman:

            <span
                style="
                    color:#0284c7;
                    font-weight:bold;
                "
            >

                <?php
                echo htmlspecialchars(
                    $opsi_pengiriman
                );
                ?>

            </span>

            <br>

            Waktu Pengambilan:

            <?php
            echo htmlspecialchars(
                $cust['waktu']
            );
            ?>

            <br>

            Metode:

            QRIS DIGITAL

            <br>

            Total Bayar:

            Rp
            <?php
            echo number_format(
                $total_semua,
                0,
                ',',
                '.'
            );
            ?>

            <br>

            ID Pesanan:

            <strong>

                <?php
                echo htmlspecialchars(
                    $midtrans_order_id
                );
                ?>

            </strong>

            <br>

            Status:

            <span
                id="paymentStatus"
                style="
                    color:#f59e0b;
                    font-weight:bold;
                "
            >

                MENUNGGU PEMBAYARAN

            </span>

        </div>

        <!-- =====================================================
             QR
        ====================================================== -->

        <div class="qr-container">

            <p
                style="
                    font-weight:bold;
                    margin-bottom:10px;
                "
            >

                Scan QRIS untuk melakukan pembayaran

            </p>

            <div class="qr-box">

                <img
                    src="<?php
                    echo htmlspecialchars(
                        $qr_url
                    );
                    ?>"
                    alt="QRIS Pembayaran"
                >

            </div>

            <input
                type="text"
                id="qrUrl"
                class="qr-url"
                value="<?php
                echo htmlspecialchars(
                    $qr_url
                );
                ?>"
                readonly
            >

            <button
                type="button"
                id="copyQrUrl"
                class="btn-submit"
                style="margin-top:10px;"
            >

                Salin URL QRIS

            </button>

            <div
                style="
                    margin-top:10px;
                    padding:12px;
                    background:#fff7ed;
                    border:1px solid #fed7aa;
                    border-radius:8px;
                    color:#9a3412;
                    font-size:13px;
                "
            >
                Setelah pembayaran QRIS berhasil, sistem akan mengecek
                status transaksi Midtrans secara otomatis.
                Jangan tutup halaman ini sebelum status berubah menjadi LUNAS.
            </div>


        </div>

        <!-- FINISH -->

        <a
            id="finishButton"
            href="menu.php"
            class="btn-submit"
            style="
                display:none;
                margin-top:10px;
            "
        >

            Kembali ke Menu Utama

        </a>

    </div>

</div>

<?php elseif (!empty($error_checkout)): ?>

<!-- =========================================================
     ERROR
========================================================= -->

<div
    style="
        max-width:700px;
        margin:20px auto;
        padding:15px 20px;
        background:#fee2e2;
        color:#b91c1c;
        border:1px solid #fecaca;
        border-radius:8px;
    "
>

    <strong>
        Checkout gagal:
    </strong>

    <?php
    echo htmlspecialchars(
        $error_checkout
    );
    ?>

</div>

<?php endif; ?>


<!-- =========================================================
     HEADER
========================================================= -->

<header>

    <h1>
        Konfirmasi Pesanan
    </h1>

    <a
        href="menu.php"
        style="
            color:white;
            text-decoration:none;
            font-weight:bold;
        "
    >

        Tambah Menu

    </a>

</header>


<!-- =========================================================
     CHECKOUT CONTENT
========================================================= -->

<div class="container">

    <div class="form-body">

        <h3
            style="margin-bottom:15px;"
        >

            Data Pemesan

        </h3>

        <div class="info-box">

            <strong>
                Nama:
            </strong>

            <?php
            echo htmlspecialchars(
                $cust['nama']
            );
            ?>

            <br>

            <strong>
                Kelas:
            </strong>

            <?php
            echo htmlspecialchars(
                $cust['kelas']
            );
            ?>

            <br>

            <strong>
                No HP:
            </strong>

            <?php
            echo htmlspecialchars(
                $cust['nohp']
            );
            ?>

            <br>

            <strong>
                Waktu Pengambilan:
            </strong>

            <?php
            echo htmlspecialchars(
                $cust['waktu']
            );
            ?>

        </div>


        <!-- FORM -->

        <form method="POST">

            <h3
                style="
                    margin-bottom:12px;
                "
            >

                Metode Pengambilan

            </h3>


            <div class="shipping-options">

                <div class="shipping-card">

                    <input
                        type="radio"
                        id="ambil"
                        name="opsi_pengiriman"
                        value="Ambil Langsung"
                        checked
                    >

                    <label
                        for="ambil"
                        class="shipping-label"
                    >

                        Ambil Langsung

                        <span class="sub-text">

                            Ambil sendiri ke UP

                        </span>

                    </label>

                </div>


                <div class="shipping-card">

                    <input
                        type="radio"
                        id="antar"
                        name="opsi_pengiriman"
                        value="Diantarkan"
                    >

                    <label
                        for="antar"
                        class="shipping-label"
                    >

                        Diantarkan

                        <span class="sub-text">

                            Diantar sesuai kelas

                        </span>

                    </label>

                </div>

            </div>


            <h3
                style="
                    margin-bottom:15px;
                "
            >

                Daftar Pesanan

            </h3>


            <?php foreach ($cart as $item): ?>

                <div class="product-summary">

                    <div class="product-img-box">

                        <img
                            src="<?php
                            echo htmlspecialchars(
                                $item['gambar']
                            );
                            ?>"
                            alt="<?php
                            echo htmlspecialchars(
                                $item['nama_menu']
                            );
                            ?>"
                            class="product-img"
                        >

                    </div>


                    <div>

                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $item['nama_menu']
                            );
                            ?>

                            (x<?php
                            echo $item['jumlah'];
                            ?>)

                        </h3>

                        <p
                            style="
                                font-weight:bold;
                                color:#0284c7;
                                margin:4px 0;
                            "
                        >

                            Rp
                            <?php
                            echo number_format(
                                $item['subtotal'],
                                0,
                                ',',
                                '.'
                            );
                            ?>

                        </p>

                        <p
                            style="
                                font-size:12px;
                                color:#64748b;
                            "
                        >

                            <?php
                            echo htmlspecialchars(
                                $item['level']
                            );
                            ?>

                            |

                            Catatan:

                            <?php
                            echo htmlspecialchars(
                                $item['catatan_raw']
                            );
                            ?>

                        </p>

                    </div>

                </div>

            <?php endforeach; ?>


            <!-- TOTAL -->

            <div class="total-price-box">

                <span>

                    Total Bayar Semua Pesanan:

                </span>

                <div class="price-display">

                    Rp
                    <?php
                    echo number_format(
                        $total_semua,
                        0,
                        ',',
                        '.'
                    );
                    ?>

                </div>

            </div>


            <!-- BUTTON -->

            <div class="btn-group">

                <a
                    href="menu.php"
                    class="btn-add"
                >

                    Tambah Pesanan

                </a>

                <button
                    type="submit"
                    name="confirm_checkout"
                    class="btn-submit"
                >

                    Buat Pesanan

                </button>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     JAVASCRIPT PAYMENT CHECK
========================================================= -->

<?php if ($order_success): ?>

<script>

const midtransOrderId =
    <?php
    echo json_encode(
        $midtrans_order_id
    );
    ?>;


const paymentTitle =
    document.getElementById(
        'paymentTitle'
    );


const paymentMessage =
    document.getElementById(
        'paymentMessage'
    );


const paymentStatus =
    document.getElementById(
        'paymentStatus'
    );


const paymentIcon =
    document.getElementById(
        'paymentIcon'
    );


const copyQrUrl =
    document.getElementById(
        'copyQrUrl'
    );


const qrUrl =
    document.getElementById(
        'qrUrl'
    );


const finishButton =
    document.getElementById(
        'finishButton'
    );


let paymentFinished = false;


/*
|--------------------------------------------------------------------------
| COPY QR URL
|--------------------------------------------------------------------------
*/

copyQrUrl.addEventListener(
    'click',
    async function () {

        try {

            await navigator.clipboard.writeText(
                qrUrl.value
            );

            copyQrUrl.textContent =
                'URL QRIS Tersalin ✓';

            setTimeout(
                function () {

                    copyQrUrl.textContent =
                        'Salin URL QRIS';

                },
                2000
            );

        } catch (error) {

            qrUrl.select();

            document.execCommand(
                'copy'
            );

            copyQrUrl.textContent =
                'URL QRIS Tersalin ✓';
        }

    }
);


/*
|--------------------------------------------------------------------------
| CEK STATUS
|--------------------------------------------------------------------------
*/

async function cekStatus() {

    if (paymentFinished) {
        return;
    }

    try {

        const response =
            await fetch(
                'cek_status.php?order_id=' +
                encodeURIComponent(
                    midtransOrderId
                ),
                {
                    cache: 'no-store'
                }
            );


        const data =
            await response.json();


        console.log(
            'Status pembayaran:',
            data
        );


        /*
        |--------------------------------------------------------------------------
        | LUNAS
        |--------------------------------------------------------------------------
        */

        if (
            data.payment_status ===
            'Lunas'
        ) {

            paymentFinished = true;


            paymentIcon.textContent =
                '✓';

            paymentIcon.style.color =
                '#22c55e';


            paymentTitle.textContent =
                'Pembayaran Berhasil!';


            paymentMessage.textContent =
                'Pembayaran sudah terverifikasi. Pesanan sekarang masuk ke dashboard admin.';


            paymentStatus.textContent =
                'LUNAS';


            paymentStatus.style.color =
                '#16a34a';


            copyQrUrl.style.display =
                'none';


            finishButton.style.display =
                'block';

        }


        /*
        |--------------------------------------------------------------------------
        | GAGAL / EXPIRED
        |--------------------------------------------------------------------------
        */

        else if (

            data.payment_status ===
                'Kadaluarsa'

            ||

            data.payment_status ===
                'Gagal'

        ) {

            paymentTitle.textContent =
                'Pembayaran Tidak Berhasil';


            paymentMessage.textContent =
                'Pembayaran tidak berhasil atau sudah kadaluarsa.';


            paymentStatus.textContent =
                data.payment_status.toUpperCase();


            paymentStatus.style.color =
                '#dc2626';

        }

    }

    catch (error) {

        console.log(
            'Gagal mengecek status:',
            error
        );

    }

}


/*
|--------------------------------------------------------------------------
| CEK PERTAMA
|--------------------------------------------------------------------------
*/

cekStatus();


/*
|--------------------------------------------------------------------------
| CEK SETIAP 3 DETIK
|--------------------------------------------------------------------------
*/

setInterval(
    cekStatus,
    3000
);

</script>

<?php endif; ?>

</body>

</html>