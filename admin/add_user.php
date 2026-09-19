<?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>

    <div class="sidebar-section">

        <div class="sidebar-section-title">
            Administration
        </div>


        <!-- Add User -->

        <a
            href="/softgeniousdev-inventory/admin/add_user.php"
            class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'add_user.php' ? 'active' : '' ?>"
        >

            <span class="sidebar-icon">

                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/>
                    <line x1="22" y1="11" x2="16" y2="11"/>
                </svg>

            </span>

            <span>
                Add User
            </span>

        </a>


        <!-- Manage Users -->

        <a
            href="/softgeniousdev-inventory/admin/users.php"
            class="sidebar-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>"
        >

            <span class="sidebar-icon">

                <svg
                    width="19"
                    height="19"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>

            </span>

            <span>
                Manage Users
            </span>

        </a>


        <!-- System Status -->

        <div class="sidebar-status">

            <span class="status-dot"></span>

            <div>
                <strong>
                    System Online
                </strong>

                <small>
                    All services operational
                </small>
            </div>

        </div>

    </div>

<?php endif; ?>