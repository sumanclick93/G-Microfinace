<?php
// We require config.php to get the database connection and session start.
// Using require_once ensures it's included only one time.
require_once('config.php');

// Set default values for name and avatar
$admin_name = "Admin";
$avatar_path = "assets/images/users/default-avatar.png"; // A default image

// Check if the admin is logged in by checking the session
if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];

    // Prepare and execute a query to get the admin's details
    $stmt = $conn->prepare("SELECT first_name, last_name, avatar FROM admins WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
        // Construct the full name, handle cases where one might be empty
        $admin_name = trim($admin_data['first_name'] . ' ' . $admin_data['last_name']);
        
        // Construct the full path to the avatar image
        $avatar_path = 'uploads/avatars/' . $admin_data['avatar'];
    }
    $stmt->close();
}
?>

<style>
.samaj-foundation-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 10050;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(90deg, #0f5132 0%, #198754 55%, #0f5132 100%);
    color: #fff;
    font-family: "Public Sans", system-ui, sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
}
.page-header { top: 38px !important; }
.sidebar-wrapper { top: 38px !important; height: calc(100vh - 38px) !important; }
.page-body { margin-top: calc(90px + 38px) !important; }
@media (max-width: 991px) {
    .page-body { margin-top: calc(85px + 38px) !important; }
}
</style>
<div class="samaj-foundation-bar" role="banner">Samajbandhan Foundation</div>

<div class="page-header">
    <div class="header-wrapper m-0">
        <div class="header-logo-wrapper p-0">
            <div class="logo-wrapper">
                <a href="dashboard.php"> <img class="img-fluid main-logo" src="assets/images/logo/1.png" alt="logo">
                    <img class="img-fluid white-logo" src="assets/images/logo/1-white.png" alt="logo">
                </a>
            </div>
            <div class="toggle-sidebar">
                <i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i>
                <a href="dashboard.php"> <img src="assets/images/logo/1.png" class="img-fluid" alt="">
                </a>
            </div>
        </div>

        <form class="form-inline search-full" action="javascript:void(0)" method="get">
            <div class="form-group w-100">
                <div class="Typeahead Typeahead--twitterUsers">
                    <div class="u-posRelative">
                        <input class="demo-input Typeahead-input form-control-plaintext w-100" type="text"
                            placeholder="Search..." name="q" title="" autofocus>
                        <i class="close-search" data-feather="x"></i>
                        <div class="spinner-border Typeahead-spinner" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                    <div class="Typeahead-menu"></div>
                </div>
            </div>
        </form>
        <div class="nav-right col-6 pull-right right-header p-0">
            <ul class="nav-menus">
                <li>
                    <span class="header-search">
                        <i class="ri-search-line"></i>
                    </span>
                </li>

                <li>
                    <div class="mode">
                        <i class="ri-moon-line"></i>
                    </div>
                </li>
                <li class="profile-nav onhover-dropdown pe-0 me-0">
                    <div class="media profile-media">
                        <img class="user-profile rounded-circle" src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Admin Avatar">
                        <div class="user-name-hide media-body">
                            <span><?php echo htmlspecialchars($admin_name); ?></span>
                            <p class="mb-0 font-roboto">Admin<i class="middle ri-arrow-down-s-line"></i></p>
                        </div>
                    </div>
                    <ul class="profile-dropdown onhover-show-div">
                        <li>
                            <a href="profile-setting.php">
                                <i data-feather="settings"></i>
                                <span>Settings</span>
                            </a>
                        </li>
                        <li>
                            <a data-bs-toggle="modal" data-bs-target="#staticBackdrop"
                                href="javascript:void(0)">
                                <i data-feather="log-out"></i>
                                <span>Log out</span>
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</div>