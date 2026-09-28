<?php
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Fitur simulasi pembayaran sudah dinonaktifkan. Gunakan pembayaran Midtrans Sandbox.";
