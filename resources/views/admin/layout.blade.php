<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard')</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .navbar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .navbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h1 {
            font-size: clamp(16px, 4vw, 20px);
            white-space: nowrap;
        }

        .navbar nav {
            display: grid;
            width: 100%;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
        }

        .nav-group {
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.22);
            border-radius: 10px;
            padding: 10px;
        }

        .nav-group-title {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.92);
            margin-bottom: 8px;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 7px 11px;
            border-radius: 4px;
            transition: background 0.3s;
            font-size: 13px;
            white-space: nowrap;
            background: rgba(255, 255, 255, 0.08);
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-link.is-active {
            background: rgba(255, 255, 255, 0.28);
            font-weight: 700;
        }

        .nav-link.is-danger {
            background: rgba(220, 53, 69, 0.75);
        }

        .nav-link.is-danger:hover {
            background: rgba(220, 53, 69, 0.9);
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: 5px;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background: white;
            margin: 3px 0;
            border-radius: 2px;
            transition: 0.3s;
        }

        /* Mobile Menu */
        @media (max-width: 768px) {
            .navbar-container {
                gap: 10px;
            }

            .hamburger {
                display: flex;
            }

            .navbar nav {
                display: none;
                width: 100%;
                flex-direction: column;
                gap: 5px;
                margin-top: 0;
                padding-top: 12px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
            }

            .navbar nav.active {
                display: flex;
            }

            .nav-group {
                width: 100%;
            }

            .nav-links {
                display: flex;
                flex-direction: column;
                gap: 6px;
            }

            .nav-link {
                width: 100%;
                text-align: left;
                padding: 10px 12px;
            }

            /* Hamburger Animation */
            .hamburger.active span:nth-child(1) {
                transform: rotate(45deg) translate(8px, 8px);
            }

            .hamburger.active span:nth-child(2) {
                opacity: 0;
            }

            .hamburger.active span:nth-child(3) {
                transform: rotate(-45deg) translate(7px, -7px);
            }
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        @media (max-width: 768px) {
            table {
                font-size: 14px;
            }

            table th,
            table td {
                padding: 8px;
            }
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <div class="navbar-container">
            <div class="navbar-top">
                <h1>📋 Order App Admin</h1>
                <div class="hamburger" onclick="toggleMenu()">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <nav id="navMenu">
                <div class="nav-group">
                    <div class="nav-group-title">Ringkasan</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">🏠 Dashboard</a>
                        <a class="nav-link {{ request()->routeIs('admin.current_order.*') ? 'is-active' : '' }}" href="{{ route('admin.current_order.index') }}">🧾 Current Order</a>
                        <a class="nav-link {{ request()->routeIs('admin.test_order') ? 'is-active' : '' }}" href="{{ route('admin.test_order') }}">🧪 Test Order</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Tim & Area</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.waiters.*') ? 'is-active' : '' }}" href="{{ route('admin.waiters.index') }}">👥 Waiters</a>
                        <a class="nav-link {{ request()->routeIs('admin.racks.*') ? 'is-active' : '' }}" href="{{ route('admin.racks.index') }}">📦 Racks</a>
                        <a class="nav-link" href="{{ route('waiter.login') }}" target="_blank" rel="noopener">🧑‍🍳 Portal Waiter</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Operasional</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.tasks.index') ? 'is-active' : '' }}" href="{{ route('admin.tasks.index') }}">📝 Tugas Umum</a>
                        <a class="nav-link {{ request()->routeIs('admin.tasks.rack.*') ? 'is-active' : '' }}" href="{{ route('admin.tasks.rack.index') }}">📦 Cek Rak</a>
                        <a class="nav-link {{ request()->routeIs('admin.cleanup') ? 'is-active' : '' }}" href="{{ route('admin.cleanup') }}">🧹 Cleanup</a>
                    </div>
                </div>

                <div class="nav-group">
                    <div class="nav-group-title">Sistem</div>
                    <div class="nav-links">
                        <a class="nav-link {{ request()->routeIs('admin.settings') ? 'is-active' : '' }}" href="{{ route('admin.settings') }}">⚙️ Settings</a>
                        <a class="nav-link is-danger" href="{{ route('admin.logout') }}">🚪 Logout</a>
                    </div>
                </div>
            </nav>
        </div>
    </div>

    <div class="container">
        @yield('content')
    </div>

    <script>
        function toggleMenu() {
            const nav = document.getElementById('navMenu');
            const hamburger = document.querySelector('.hamburger');
            nav.classList.toggle('active');
            hamburger.classList.toggle('active');
        }

        // Close menu when clicking outside
        document.addEventListener('click', function (event) {
            const nav = document.getElementById('navMenu');
            const hamburger = document.querySelector('.hamburger');
            const navbar = document.querySelector('.navbar');

            if (!navbar.contains(event.target)) {
                nav.classList.remove('active');
                hamburger.classList.remove('active');
            }
        });

        // Close menu when window is resized to desktop
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) {
                const nav = document.getElementById('navMenu');
                const hamburger = document.querySelector('.hamburger');
                nav.classList.remove('active');
                hamburger.classList.remove('active');
            }
        });
    </script>
</body>

</html>
