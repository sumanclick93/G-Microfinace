<div class="sidebar-wrapper">
    <div id="sidebarEffect"></div>
    <div>
        <div class="logo-wrapper logo-wrapper-center">
            <a href="index.html" data-bs-original-title="" title="">
                <img class="img-fluid for-white" src="assets/images/logo/full-white.png?v=<?php echo time(); ?>" alt="logo">
            </a>
            <div class="back-btn">
                <i class="fa fa-angle-left"></i>
            </div>
            <div class="toggle-sidebar">
                <i class="ri-apps-line status_toggle middle sidebar-toggle"></i>
            </div>
        </div>
        <div class="logo-icon-wrapper">
            <a href="index.html">
                <img class="img-fluid main-logo main-white" src="assets/images/logo/logo.png?v=<?php echo time(); ?>" alt="logo">
                <img class="img-fluid main-logo main-dark" src="assets/images/logo/logo-white.png?v=<?php echo time(); ?>"
                    alt="logo">
            </a>
        </div>
        <nav class="sidebar-main">
            <div class="left-arrow" id="left-arrow">
                <i data-feather="arrow-left"></i>
            </div>
    
            <div id="sidebar-menu">
                <ul class="sidebar-links" id="simple-bar">
                    <li class="back-btn"></li>
    
                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title link-nav" href="dashboard.php">
                            <i data-feather="home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title" href="javascript:void(0)">
                            <i data-feather="briefcase"></i>
                            <span>Agents</span>
                        </a>
                        <ul class="sidebar-submenu">
                            <li>
                                <a href="all-agents.php">All Agents</a>
                            </li>
                            <li>
                                <a href="agent-financials.php">Agent Wallets & Financials</a>
                            </li>
                            <li>
                                <a href="add-new-agents.php">Add new agents</a>
                            </li>
                        </ul>
                    </li>
                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title link-nav" href="all-customers-loans.php">
                            <i data-feather="users"></i>
                            <span>Customer Overview</span>
                        </a>
                    </li>
                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title link-nav" href="all-loans.php">
                            <i data-feather="list"></i>
                            <span>Loans</span>
                        </a>
                    </li>
                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title link-nav" href="pending-loan-payments.php">
                            <i data-feather="list"></i>
                            <span>Pending Loan Payments</span>
                        </a>
                    </li>
                    <li class="sidebar-list-item">
                        <a class="sidebar-link" href="reports.php">
                            <i data-feather="bar-chart-2"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title link-nav" href="collection-report.php">
                            <i class="ri-file-chart-line"></i>
                            <span>Collection Report</span>
                        </a>
                    </li>
                    <li class="sidebar-list-item">
                        <a class="sidebar-link" href="all-rds.php">
                            <i data-feather="database"></i> <span>All RDs</span>
                        </a>
                    </li>
                    <li class="sidebar-list">
                        <a class="sidebar-link sidebar-title link-nav" href="pending-rd-payments.php">
                            <i data-feather="list"></i>
                            <span>Pending Rd Payments</span>
                        </a>
                    </li>
                    <li class="sidebar-list-item">
                        <a class="sidebar-link" href="profile-setting.php">
                            <i data-feather="settings"></i>
                            <span>Settings</span>
                        </a>
                    </li>
        
                     <li class="sidebar-list-item">
                        <a class="sidebar-link" href="logout.php">
                            <i data-feather="log-out"></i>
                            <span>Logout</span>
                        </a>
                    </li>
                    
                </ul>
            </div>
    
            <div class="right-arrow" id="right-arrow">
                <i data-feather="arrow-right"></i>
            </div>
        </nav>
    </div>
</div>