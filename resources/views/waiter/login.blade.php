<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Waiter Login - Order App</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
            padding: 20px;
        }
        .login-container {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
        }
        h1 { text-align: center; margin-bottom: 10px; color: #1f2d3d; }
        p { text-align: center; margin-bottom: 24px; color: #5d6b7a; font-size: 14px; }
        .group { margin-bottom: 16px; }
        label { display: block; margin-bottom: 8px; color: #334155; font-weight: 600; }
        input {
            width: 100%;
            border: 2px solid #dbe3ef;
            border-radius: 10px;
            padding: 11px 13px;
            font-size: 15px;
        }
        input:focus { outline: none; border-color: #5b86e5; }
        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, #36d1dc 0%, #5b86e5 100%);
        }
        .error {
            background: #fff5f5;
            border: 1px solid #fecaca;
            color: #b91c1c;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 15px;
            font-size: 14px;
            display: none;
        }
        .error.show {
            display: block;
        }
        .google-btn {
            width: 100%;
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 12px 14px;
            color: #1f2d3d;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            background: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            transition: all 0.2s ease;
        }
        .google-btn:hover {
            border-color: #9db2d6;
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.15);
        }
        .google-btn:disabled {
            opacity: 0.7;
            cursor: wait;
        }
        .muted {
            margin-top: 14px;
            margin-bottom: 0;
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <h1>🧑‍🍳 Login Waiter</h1>
        <p>Masuk dengan akun Google waiter yang sudah didaftarkan supervisor.</p>

        <div id="error-box" class="error"></div>

        <button id="google-login-btn" class="google-btn" type="button">
            <span>🔐</span>
            <span>Login dengan Google</span>
        </button>

        <p class="muted">Hanya email waiter yang aktif di master data yang bisa masuk.</p>
    </div>

    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
        import { getAuth, GoogleAuthProvider, signInWithPopup } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js';

        const firebaseConfig = {
            apiKey: "{{ env('FIREBASE_API_KEY') }}",
            authDomain: "{{ env('FIREBASE_AUTH_DOMAIN') }}",
            databaseURL: "{{ env('FIREBASE_DATABASE_URL') }}",
            projectId: "{{ env('FIREBASE_PROJECT_ID') }}",
            storageBucket: "{{ env('FIREBASE_STORAGE_BUCKET') }}",
            messagingSenderId: "{{ env('FIREBASE_MESSAGING_SENDER_ID') }}",
            appId: "{{ env('FIREBASE_APP_ID') }}"
        };

        const app = initializeApp(firebaseConfig);
        const auth = getAuth(app);
        auth.languageCode = 'id';

        const loginEndpoint = "{{ route('waiter.login.google', [], false) }}";
        const tasksUrl = "{{ route('waiter.tasks', [], false) }}";
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const button = document.getElementById('google-login-btn');
        const errorBox = document.getElementById('error-box');

        function showError(message) {
            errorBox.textContent = message;
            errorBox.classList.add('show');
        }

        function clearError() {
            errorBox.textContent = '';
            errorBox.classList.remove('show');
        }

        async function loginWithGoogle() {
            clearError();
            button.disabled = true;
            button.innerHTML = '<span>⏳</span><span>Sedang login...</span>';

            try {
                const provider = new GoogleAuthProvider();
                provider.setCustomParameters({ prompt: 'select_account' });

                const result = await signInWithPopup(auth, provider);
                const idToken = await result.user.getIdToken(true);

                const response = await fetch(loginEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ id_token: idToken }),
                });

                const payload = await response.json();
                if (!response.ok || !payload?.success) {
                    throw new Error(payload?.message || 'Login Google gagal.');
                }

                window.location.href = payload?.redirect || tasksUrl;
            } catch (error) {
                showError(error?.message || 'Tidak bisa login Google. Coba lagi.');
                button.disabled = false;
                button.innerHTML = '<span>🔐</span><span>Login dengan Google</span>';
            }
        }

        button.addEventListener('click', loginWithGoogle);
    </script>
</body>

</html>
