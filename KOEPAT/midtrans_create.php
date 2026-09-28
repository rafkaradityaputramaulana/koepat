<?php

require_once __DIR__ . '/midtrans_config.php';

function createMidtransTransaction(
    $orderId,
    $grossAmount,
    $cust = null,
    $cart = null
) {
    $serverKey = MIDTRANS_SERVER_KEY;

    $url = MIDTRANS_API_URL . '/v2/charge';

    $payload = [
        'payment_type' => 'qris',

        'transaction_details' => [
            'order_id' => $orderId,
            'gross_amount' => (int) $grossAmount
        ],

        'qris' => [
            'acquirer' => 'gopay'
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Basic ' .
                base64_encode($serverKey . ':')
        ],

        CURLOPT_POSTFIELDS => json_encode($payload),

        CURLOPT_TIMEOUT => 30,

        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);

    $curlError = curl_error($ch);

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);


    // =========================
    // CURL ERROR
    // =========================

    if ($response === false) {

        return [
            'success' => false,
            'message' => 'cURL Error: ' . $curlError
        ];
    }


    // =========================
    // DECODE RESPONSE
    // =========================

    $data = json_decode(
        $response,
        true
    );


    if (!is_array($data)) {

        return [
            'success' => false,
            'message' => 'Response Midtrans tidak valid.',
            'raw_response' => $response,
            'http_code' => $httpCode
        ];
    }


    // =========================
    // MIDTRANS ERROR
    // =========================

    if ($httpCode < 200 || $httpCode >= 300) {

        return [
            'success' => false,

            'message' =>
                $data['status_message']
                ?? 'Transaksi Midtrans gagal.',

            'http_code' => $httpCode,

            'response' => $data
        ];
    }


    // =========================
    // CARI QR
    // =========================

    $qrUrl = '';

    if (
        isset($data['actions']) &&
        is_array($data['actions'])
    ) {

        foreach ($data['actions'] as $action) {

            $name = $action['name'] ?? '';

            if (
                $name === 'generate-qr-code-v2' ||
                $name === 'generate-qr-code'
            ) {

                if (!empty($action['url'])) {

                    $qrUrl = $action['url'];

                    break;
                }
            }
        }
    }


    // =========================
    // QR TIDAK DITEMUKAN
    // =========================

    if (empty($qrUrl)) {

        return [
            'success' => false,

            'message' =>
                'Transaksi berhasil dibuat tetapi QRIS tidak ditemukan.',

            'response' => $data
        ];
    }


    // =========================
    // BERHASIL
    // =========================

    return [

        'success' => true,

        'order_id' =>
            $data['order_id']
            ?? $orderId,

        'transaction_id' =>
            $data['transaction_id']
            ?? '',

        'transaction_status' =>
            $data['transaction_status']
            ?? 'pending',

        'gross_amount' =>
            $data['gross_amount']
            ?? $grossAmount,

        'qr_url' =>
            $qrUrl,

        'expiry_time' =>
            $data['expiry_time']
            ?? null,

        'response' =>
            $data
    ];
}