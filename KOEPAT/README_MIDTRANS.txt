INTEGRASI MIDTRANS QRIS - KOEPAT

1. Buka database koepat1 di phpMyAdmin.
2. Jalankan isi database_midtrans.sql SATU KALI.
3. Buka midtrans_config.php.
4. Isi:
   MIDTRANS_SERVER_KEY = Server Key Sandbox kamu
   MIDTRANS_CLIENT_KEY = Client Key Sandbox kamu
5. Pastikan PHP/XAMPP memiliki ekstensi cURL aktif.
6. Buka KOEPAT dan lakukan checkout.
7. Setelah klik Buat Pesanan, Snap Midtrans akan muncul dan hanya QRIS yang ditampilkan.
8. Setelah pembayaran sukses, sistem mengecek status ke Midtrans dan mengubah status pembayaran menjadi Lunas.
9. admin_dashboard.php hanya menampilkan pesanan dengan status Lunas.

WEBHOOK (disarankan agar status tetap otomatis walaupun halaman customer ditutup)

File webhook:
   midtrans_notification.php

URL webhook harus dapat diakses dari internet. Jika project masih localhost, gunakan ngrok, contoh:
   ngrok http 80

Jika URL publik menjadi:
   https://abc123.ngrok-free.app

maka Payment Notification URL di Midtrans Sandbox diarahkan ke:
   https://abc123.ngrok-free.app/KOEPAT/midtrans_notification.php

Letak pengaturan di Midtrans:
   Settings > Configuration > Payment Notification URL

CATATAN KEAMANAN
- Jangan membagikan Server Key.
- Gunakan Server Key hanya di backend (midtrans_config.php).
- Client Key boleh digunakan pada frontend.
