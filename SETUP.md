# Laravel Backend - Setup Instructions (Realtime Database)

## 1. Environment Variables

Tambahkan konfigurasi berikut ke file `.env`:

```env
# Admin Password
ADMIN_PASSWORD=your_secure_password_here

# Firebase Admin SDK (untuk Laravel backend)
FIREBASE_CREDENTIALS=../storage/app/firebase-credentials.json
FIREBASE_PROJECT_ID=your-project-id

# Firebase Web Config (untuk Cashier view real-time)
FIREBASE_API_KEY=AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXX
FIREBASE_AUTH_DOMAIN=your-project.firebaseapp.com
FIREBASE_STORAGE_BUCKET=your-project.appspot.com
FIREBASE_MESSAGING_SENDER_ID=123456789012
FIREBASE_APP_ID=1:123456789012:web:abcdef123456
FIREBASE_DATABASE_URL=https://your-project-default-rtdb.firebaseio.com
```

## 2. Firebase Credentials

1. Buka [Firebase Console](https://console.firebase.google.com)
2. Pilih project Anda
3. Pergi ke **Project Settings** > **Service Accounts**
4. Klik **Generate New Private Key**
5. Download file JSON dan simpan sebagai `storage/app/firebase-credentials.json`

## 3. Firebase Realtime Database Setup

### Enable Realtime Database:
1. Firebase Console → **Realtime Database**
2. Klik **"Create Database"**
3. Pilih location (contoh: `us-central1`)
4. Pilih **"Start in test mode"** (untuk development)
5. Klik **"Enable"**

### Get Database URL:
Setelah database dibuat, copy **Database URL** (contoh: `https://your-project-default-rtdb.firebaseio.com`) dan masukkan ke `.env` sebagai `FIREBASE_DATABASE_URL`.

## 4. Firebase Web Config (untuk Cashier View)

Dapatkan config dari **Firebase Console**:
1. Project Settings > General > Your apps
2. Pilih/buat Web App (ikon `</>`)
3. Copy nilai untuk:
   - `apiKey`, `authDomain`, `projectId`, `storageBucket`, `messagingSenderId`, `appId`
4. Masukkan ke `.env` (lihat step 1 di atas)

Config ini sudah otomatis diambil dari `.env`, jadi **tidak perlu edit file blade lagi**.

## 5. Security Rules (Opsional)

Di Firebase Console → Realtime Database → Rules, set rules:

```json
{
  "rules": {
    ".read": true,
    ".write": true
  }
}
```

⚠️ **Warning**: Ini untuk development only. Untuk production, gunakan rules yang lebih ketat.

## 6. Jalankan Aplikasi

```bash
php artisan serve
```

## 7. Akses Admin Panel

- URL: `http://localhost:8000/admin`
- Password: sesuai yang Anda set di `ADMIN_PASSWORD` di `.env`

## 8. Akses Cashier View

- URL: `http://localhost:8000/cashier`
- Tidak perlu login, langsung tampil real-time orders

---

## Perbedaan dengan Firestore

| Aspek | Firestore | Realtime Database |
|-------|-----------|-------------------|
| Enable Database | Kadang perlu Blaze plan | Lebih sering gratis |
| Database URL | Tidak perlu | **Perlu** `FIREBASE_DATABASE_URL` |
| Data Structure | Document/Collection | JSON tree |
| Real-time | Baik | Excellent (lebih cepat) |

---

## File Structure

```
order/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AdminController.php      ✅ Created
│   │   │   └── CashierController.php    ✅ Created
│   │   └── Middleware/
│   │       └── AdminAuthMiddleware.php  ✅ Created
│   └── Services/
│       └── FirebaseService.php          ✅ Updated (Realtime DB)
├── config/
│   └── firebase.php                     ✅ Created
├── resources/views/
│   ├── admin/
│   │   ├── layout.blade.php             ✅ Created
│   │   ├── login.blade.php              ✅ Created
│   │   ├── dashboard.blade.php          ✅ Created
│   │   ├── settings.blade.php           ✅ Created
│   │   └── waiters/
│   │       ├── index.blade.php          ✅ Created
│   │       ├── create.blade.php         ✅ Created
│   │       └── edit.blade.php           ✅ Created
│   └── cashier/
│       └── index.blade.php              ✅ Updated (Realtime DB)
└── routes/
    └── web.php                          ✅ Updated
```
