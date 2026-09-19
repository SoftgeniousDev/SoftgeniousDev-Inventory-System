<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentDirectory = basename(dirname($_SERVER['PHP_SELF']));

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'user';

function isSidebarActive(string $directory, string $page = ''): bool
{
    global $currentDirectory, $currentPage;

    if ($page !== '') {
        return $currentDirectory === $directory && $currentPage === $page;
    }

    return $currentDirectory === $directory;
}

require_once __DIR__ . '/../config/database.php';

$lowStockCount = 0;

try {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM products
        WHERE stock_quantity <= reorder_level
    ");

    $lowStockCount = (int) $stmt->fetchColumn();

} catch (Throwable $e) {

    $lowStockCount = 0;
}

?>

<aside class="sidebar" id="sidebar">

<div class="sidebar-brand">

    <a href="/softgeniousdev-inventory/index.php" class="brand-link">

        <div class="brand-logo">
            SG
        </div>

        <div class="brand-text">
            <strong>Softgenious</strong>
            <span>Inventory System</span>
        </div>

    </a>

    <button
        type="button"
        class="mobile-close"
        id="mobileClose"
        aria-label="Close navigation"
    >
        ×
    </button>

</div>


<div class="sidebar-user">

    <div class="user-avatar">
        <?= strtoupper(substr($userName, 0, 1)) ?>
    </div>

    <div class="user-info">

        <strong>
            <?= htmlspecialchars($userName) ?>
        </strong>

        <span>
            <?= htmlspecialchars(ucfirst($userRole)) ?> Account
        </span>

    </div>

</div>


<nav class="sidebar-nav">

    <div class="nav-section-title">
        MAIN
    </div>


    <!-- DASHBOARD -->

    <a
        href="/softgeniousdev-inventory/index.php"
        class="nav-item <?= ($currentDirectory === 'softgeniousdev-inventory' && $currentPage === 'index.php') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 13h8V3H3v10zm10 8h8V11h-8v10zM3 21h8v-6H3v6zm10-18v6h8V3h-8z"/>
            </svg>

        </span>

        <span>Dashboard</span>

    </a>


    <!-- PRODUCTS -->

    <a
        href="/softgeniousdev-inventory/products/index.php"
        class="nav-item <?= isSidebarActive('products') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 7.5 12 3 4 7.5v9L12 21l8-4.5v-9zM12 3v9m8-4.5-8 4.5-8-4.5"/>
            </svg>

        </span>

        <span>Products</span>

    </a>


    <!-- SALES -->

    <a
        href="/softgeniousdev-inventory/sales/index.php"
        class="nav-item <?= isSidebarActive('sales') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M6 2h12v20H6V2zm3 4h6m-6 4h6m-6 4h4"/>
            </svg>

        </span>

        <span>Sales</span>

    </a>


    <!-- PURCHASES -->

    <a
        href="/softgeniousdev-inventory/purchases/index.php"
        class="nav-item <?= isSidebarActive('purchases') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 6h18M5 6l1 14h12l1-14M9 6V4h6v2M9 10v6m6-6v6"/>
            </svg>

        </span>

        <span>Purchases</span>

    </a>


    <!-- PEOPLE -->

    <div class="nav-section-title">
        PEOPLE
    </div>


    <!-- CUSTOMERS -->

    <a
        href="/softgeniousdev-inventory/customers/index.php"
        class="nav-item <?= isSidebarActive('customers') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm7 0a3 3 0 1 0 0-6m4 16v-2a4 4 0 0 0-3-3.87"/>
            </svg>

        </span>

        <span>Customers</span>

    </a>


    <!-- SUPPLIERS -->

    <a
        href="/softgeniousdev-inventory/suppliers/index.php"
        class="nav-item <?= isSidebarActive('suppliers') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 21V8l9-5 9 5v13H3zm4 0v-6h10v6M8 9h2m4 0h2"/>
            </svg>

        </span>

        <span>Suppliers</span>

    </a>


    <!-- MANAGEMENT -->

    <div class="nav-section-title">
        MANAGEMENT
    </div>


    <!-- REPORTS -->

    <a
        href="/softgeniousdev-inventory/reports/index.php"
        class="nav-item <?= isSidebarActive('reports') ? 'active' : '' ?>"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M4 19V5m0 14h17M8 16v-5m4 5V7m4 9v-8m4 8V4"/>
            </svg>

        </span>

        <span>Reports</span>

    </a>


    <!-- STOCK ALERTS -->

    <a
        href="/softgeniousdev-inventory/products/index.php?filter=low_stock"
        class="nav-item stock-nav"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 3 2 21h20L12 3zm0 6v5m0 3v1"/>
            </svg>

        </span>

        <span>Stock Alerts</span>

        <?php if ($lowStockCount > 0): ?>

            <span class="stock-badge">
                <?= $lowStockCount > 99 ? '99+' : $lowStockCount ?>
            </span>

        <?php endif; ?>

    </a>


    <!-- ADMINISTRATION -->

    <?php if ($userRole === 'admin'): ?>

        <div class="nav-section-title admin-section-title">
            ADMINISTRATION
        </div>


        <!-- ADD USER -->

        <a
            href="/softgeniousdev-inventory/admin/add_user.php"
            class="nav-item <?= isSidebarActive('admin', 'add_user.php') ? 'active' : '' ?>"
        >

            <span class="nav-icon">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm7-4v6m-3-3h6"/>
                </svg>

            </span>

            <span>Add User</span>

            <span class="admin-badge">
                ADMIN
            </span>

        </a>


        <!-- MANAGE USERS -->

        <a
            href="/softgeniousdev-inventory/admin/users.php"
            class="nav-item <?= isSidebarActive('admin', 'users.php') ? 'active' : '' ?>"
        >

            <span class="nav-icon">

                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm7 0a3 3 0 1 0 0-6m4 16v-2a4 4 0 0 0-3-3.87"/>
                </svg>

            </span>

            <span>Manage Users</span>

        </a>

    <?php endif; ?>

</nav>


<!-- SIDEBAR FOOTER -->

<div class="sidebar-footer">

    <div class="system-status">

        <span class="status-dot"></span>

        <span>System Online</span>

    </div>


    <a
        href="/softgeniousdev-inventory/auth/logout.php"
        class="logout-link"
    >

        <span class="nav-icon">

            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M10 17l5-5-5-5m5 5H3m9-9V3h9v18h-9v-3"/>
            </svg>

        </span>

        <span>Logout</span>

    </a>

</div>

</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<style>

/* SIDEBAR */

.sidebar {

    width: 260px;

    height: 100vh;

    position: fixed;

    left: 0;

    top: 0;

    background: rgba(255,255,255,0.96);

    border-right: 1px solid #e4eaf1;

    display: flex;

    flex-direction: column;

    z-index: 1000;

    box-shadow: 10px 0 35px rgba(30,50,80,0.05);

    backdrop-filter: blur(14px);

    transition:
        transform .3s ease,
        box-shadow .3s ease;

}


/* BRAND */

.sidebar-brand {

    height: 78px;

    padding: 0 20px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom: 1px solid #edf1f5;

}

.brand-link {

    display: flex;

    align-items: center;

    gap: 11px;

    text-decoration: none;

}

.brand-logo {

    width: 40px;

    height: 40px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: linear-gradient(135deg,#2563eb,#7c3aed);

    color: #fff;

    font-size: 15px;

    font-weight: 800;

    letter-spacing: -1px;

    box-shadow: 0 7px 18px rgba(59,91,219,.25);

    animation: logoFloat 4s ease-in-out infinite;

}

@keyframes logoFloat {

    0%,100% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-2px);
    }

}

.brand-text {

    display: flex;

    flex-direction: column;

    line-height: 1.15;

}

.brand-text strong {

    color: #172033;

    font-size: 15px;

}

.brand-text span {

    color: #8a96a8;

    font-size: 10px;

    margin-top: 4px;

}


/* USER */

.sidebar-user {

    margin: 18px 15px 10px;

    padding: 12px;

    border-radius: 14px;

    background: #f6f8fc;

    display: flex;

    align-items: center;

    gap: 11px;

}

.user-avatar {

    width: 38px;

    height: 38px;

    min-width: 38px;

    border-radius: 50%;

    background: linear-gradient(135deg,#dbeafe,#ede9fe);

    color: #3156a8;

    display: flex;

    align-items: center;

    justify-content: center;

    font-weight: 800;

    font-size: 14px;

}

.user-info {

    min-width: 0;

    display: flex;

    flex-direction: column;

}

.user-info strong {

    color: #263247;

    font-size: 12px;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}

.user-info span {

    color: #8a96a8;

    font-size: 10px;

    margin-top: 3px;

}


/* NAVIGATION */

.sidebar-nav {

    flex: 1;

    padding: 8px 13px;

    overflow-y: auto;

}

.nav-section-title {

    color: #a0a9b8;

    font-size: 9px;

    font-weight: 800;

    letter-spacing: 1.2px;

    padding: 16px 12px 7px;

}

.admin-section-title {

    margin-top: 4px;

    color: #7652b8;

}


/* NAV ITEMS */

.nav-item {

    position: relative;

    min-height: 44px;

    margin: 3px 0;

    padding: 0 12px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    gap: 11px;

    color: #637085;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;

}

.nav-item:hover {

    background: #f3f6fb;

    color: #263d69;

    transform: translateX(2px);

}

.nav-item.active {

    color: #2856bd;

    background: linear-gradient(
        90deg,
        #edf4ff,
        #f4f0ff
    );

}

.nav-item.active::before {

    content: "";

    position: absolute;

    left: -13px;

    top: 9px;

    width: 3px;

    height: 26px;

    border-radius: 0 5px 5px 0;

    background: linear-gradient(
        180deg,
        #2563eb,
        #7c3aed
    );

}

.nav-icon {

    width: 19px;

    height: 19px;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

}

.nav-icon svg {

    width: 18px;

    height: 18px;

    fill: none;

    stroke: currentColor;

    stroke-width: 1.7;

    stroke-linecap: round;

    stroke-linejoin: round;

}

.nav-item.active .nav-icon {

    transform: scale(1.05);

}


/* BADGES */

.admin-badge {

    margin-left: auto;

    padding: 3px 6px;

    border-radius: 5px;

    background: #f0eaff;

    color: #7044c7;

    font-size: 7px;

    font-weight: 800;

    letter-spacing: .5px;

}

.stock-badge {

    margin-left: auto;

    min-width: 20px;

    height: 20px;

    padding: 0 5px;

    border-radius: 20px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #fff0f0;

    color: #dc3545;

    font-size: 9px;

    font-weight: 800;

    animation: alertPulse 2s ease-in-out infinite;

}

@keyframes alertPulse {

    0%,100% {

        box-shadow: 0 0 0 0 rgba(220,53,69,0);

    }

    50% {

        box-shadow: 0 0 0 4px rgba(220,53,69,.08);

    }

}


/* FOOTER */

.sidebar-footer {

    padding: 12px 13px 15px;

    border-top: 1px solid #edf1f5;

}

.system-status {

    display: flex;

    align-items: center;

    gap: 8px;

    padding: 9px 12px;

    margin-bottom: 6px;

    color: #7d8899;

    font-size: 10px;

}

.status-dot {

    width: 7px;

    height: 7px;

    border-radius: 50%;

    background: #22c55e;

    box-shadow: 0 0 0 4px rgba(34,197,94,.10);

}

.logout-link {

    min-height: 42px;

    padding: 0 12px;

    border-radius: 11px;

    display: flex;

    align-items: center;

    gap: 11px;

    color: #7b8493;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;

    transition: all .2s ease;

}

.logout-link:hover {

    background: #fff1f1;

    color: #dc3545;

    transform: translateX(2px);

}


/* MOBILE */

.mobile-close {

    display: none;

    border: none;

    background: transparent;

    color: #697586;

    font-size: 27px;

    cursor: pointer;

}

.sidebar-overlay {

    display: none;

}

@media (max-width: 900px) {

    .sidebar {

        transform: translateX(-100%);

        box-shadow: 15px 0 45px rgba(20,40,70,.15);

    }

    .sidebar.open {

        transform: translateX(0);

    }

    .mobile-close {

        display: block;

    }

    .sidebar-overlay {

        position: fixed;

        inset: 0;

        background: rgba(20,30,45,.25);

        backdrop-filter: blur(2px);

        z-index: 999;

    }

    .sidebar-overlay.show {

        display: block;

    }

}

</style>

<script>

(function () {

    function initializeSidebar() {

        const sidebar =
            document.getElementById('sidebar');

        const overlay =
            document.getElementById('sidebarOverlay');

        const closeButton =
            document.getElementById('mobileClose');

        const menuButton =
            document.getElementById('mobileMenu');


        if (!sidebar) {
            return;
        }


        function openSidebar() {

            sidebar.classList.add('open');

            if (overlay) {
                overlay.classList.add('show');
            }

            document.body.style.overflow = 'hidden';

        }


        function closeSidebar() {

            sidebar.classList.remove('open');

            if (overlay) {
                overlay.classList.remove('show');
            }

            document.body.style.overflow = '';

        }


        /*
        ----------------------------------------------------------
        OPEN MOBILE SIDEBAR
        ----------------------------------------------------------
        */

        if (menuButton) {

            menuButton.addEventListener(
                'click',
                openSidebar
            );

        }


        /*
        ----------------------------------------------------------
        CLOSE MOBILE SIDEBAR
        ----------------------------------------------------------
        */

        if (closeButton) {

            closeButton.addEventListener(
                'click',
                closeSidebar
            );

        }


        /*
        ----------------------------------------------------------
        CLOSE WHEN CLICKING OVERLAY
        ----------------------------------------------------------
        */

        if (overlay) {

            overlay.addEventListener(
                'click',
                closeSidebar
            );

        }


        /*
        ----------------------------------------------------------
        CLOSE AFTER CLICKING A NAVIGATION LINK
        ----------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.nav-item, .logout-link'
            )
            .forEach(function (link) {

                link.addEventListener(
                    'click',
                    function () {

                        if (
                            window.innerWidth <= 900
                        ) {

                            closeSidebar();

                        }

                    }
                );

            });


        /*
        ----------------------------------------------------------
        CLOSE SIDEBAR WHEN RESIZING TO DESKTOP
        ----------------------------------------------------------
        */

        window.addEventListener(
            'resize',
            function () {

                if (
                    window.innerWidth > 900
                ) {

                    closeSidebar();

                }

            }
        );

    }


    /*
    --------------------------------------------------------------
    IMPORTANT:
    The sidebar is loaded before the dashboard's mobile menu
    button, so wait until the entire page has loaded.
    --------------------------------------------------------------
    */

    if (
        document.readyState === 'loading'
    ) {

        document.addEventListener(
            'DOMContentLoaded',
            initializeSidebar
        );

    } else {

        initializeSidebar();

    }

})();

</script>
