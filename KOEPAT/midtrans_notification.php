<?php
// Endpoint webhook Midtrans.
// Pasang URL file ini pada Midtrans Sandbox > Settings > Configuration
// bagian Payment Notification URL.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/midtrans_config.php';

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$orderId = $data['order_id'] ?? '';
$statusCode = $data['status_code'] ?? '';
$grossAmount = $data['gross_amount'] ?? '';
$signatureKey = $data['signature_key'] ?? '';

if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing notification fields']);
    exit;
}

// Verifikasi bahwa notification benar-benar berasal dari Midtrans.
$expectedSignature = hash('sha512', $orderId . $statusCode . $grossAmount . MIDTRANS_SERVER_KEY);

if (!hash_equals($expectedSignature, $signatureKey)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Invalid signature']);
    exit;
}

$transactionStatus = strtolower($data['transaction_status'] ?? '');
$fraudStatus = strtolower($data['fraud_status'] ?? 'accept');
$paymentType = $data['payment_type'] ?? 'QRIS';
$transactionId = $data['transaction_id'] ?? '';

// Ambil status pembayaran saat ini agar notification yang datang terlambat
// tidak menurunkan status transaksi yang sudah Lunas.
$stmt = $conn->prepare("SELECT id_Pesanan, status_Pembayaran FROM pembayaran WHERE midtrans_order_id = ? LIMIT 1");
$stmt->bind_param('s', $orderId);
$stmt->execute();
$result = $stmt->get_result();
$payment = $result->fetch_assoc();
$stmt->close();

if (!$payment) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

$currentStatus = $payment['status_Pembayaran'];

// Settlement = pembayaran berhasil. Capture juga sukses untuk tipe transaksi tertentu.
$isPaid = in_array($transactionStatus, ['settlement', 'capture'], true)
    && in_array($fraudStatus, ['', 'accept'], true)
    && $statusCode === '200';

if ($isPaid) {
    $newStatus = 'Lunas';
} elseif ($transactionStatus === 'expire') {
    $newStatus = 'Kadaluarsa';
} elseif (in_array($transactionStatus, ['cancel', 'deny', 'failure'], true)) {
    $newStatus = 'Gagal';
} else {
    $newStatus = 'Belum bayar';
}

// Idempotent: status Lunas tidak boleh diturunkan oleh notification lama.
if ($currentStatus === 'Lunas' && $newStatus !== 'Lunas') {
    echo json_encode(['status' => 'ok', 'message' => 'Ignored old notification']);
    exit;
}

$stmt = $conn->prepare("UPDATE pembayaran SET status_Pembayaran = ?, midtrans_transaction_id = ?, metode_Pembayaran = 'QRIS' WHERE midtrans_order_id = ?");
$stmt->bind_param('sss', $newStatus, $transactionId, $orderId);
$stmt->execute();
$stmt->close();

http_response_code(200);
echo json_encode(['status' => 'ok']);
