<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/midtrans_config.php';

$orderId = trim($_GET['order_id'] ?? '');

if ($orderId === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Order ID tidak ditemukan.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil pembayaran dari database
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare("
    SELECT id_Pesanan, total_bayar, status_Pembayaran
    FROM pembayaran
    WHERE midtrans_order_id = ?
    LIMIT 1
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param('s', $orderId);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Transaksi tidak ditemukan di database.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Tanya status langsung ke Midtrans
|--------------------------------------------------------------------------
*/
$url = MIDTRANS_API_URL . '/v2/' . rawurlencode($orderId) . '/status';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghubungi Midtrans: ' . $curlError
    ]);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data)) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Response Midtrans tidak valid.',
        'http_code' => $httpCode
    ]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => false,
        'message' => $data['status_message'] ?? 'Midtrans menolak permintaan status.',
        'midtrans_status' => $data['transaction_status'] ?? null
    ]);
    exit;
}

$transactionStatus = strtolower($data['transaction_status'] ?? 'pending');
$fraudStatus = strtolower($data['fraud_status'] ?? '');
$midtransGrossAmount = isset($data['gross_amount'])
    ? (int) round((float) $data['gross_amount'])
    : 0;

$dbGrossAmount = (int) $payment['total_bayar'];

/*
|--------------------------------------------------------------------------
| Pastikan nominal transaksi sama
|--------------------------------------------------------------------------
*/
if ($midtransGrossAmount !== $dbGrossAmount) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Nominal transaksi Midtrans tidak cocok dengan database.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Tentukan status aplikasi
|--------------------------------------------------------------------------
*/
if (
    ($transactionStatus === 'settlement') ||
    (
        $transactionStatus === 'capture' &&
        in_array($fraudStatus, ['', 'accept'], true)
    )
) {
    $paymentStatus = 'Lunas';
} elseif ($transactionStatus === 'expire') {
    $paymentStatus = 'Kadaluarsa';
} elseif (in_array($transactionStatus, ['cancel', 'deny', 'failure'], true)) {
    $paymentStatus = 'Gagal';
} else {
    $paymentStatus = 'Belum bayar';
}

/*
|--------------------------------------------------------------------------
| Simpan status + transaction ID
|--------------------------------------------------------------------------
*/
$transactionId = $data['transaction_id'] ?? '';
$paymentType = strtoupper($data['payment_type'] ?? 'QRIS');

$stmt = $conn->prepare("
    UPDATE pembayaran
    SET status_Pembayaran = ?,
        midtrans_transaction_id = ?,
        metode_Pembayaran = ?
    WHERE midtrans_order_id = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menyiapkan update database: ' . $conn->error
    ]);
    exit;
}

$stmt->bind_param(
    'ssss',
    $paymentStatus,
    $transactionId,
    $paymentType,
    $orderId
);

$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'payment_status' => $paymentStatus,
    'midtrans_status' => $transactionStatus,
    'order_id' => $orderId,
    'transaction_id' => $transactionId
]);
