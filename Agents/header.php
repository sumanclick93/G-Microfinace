<?php
// We require the config file to get the database connection and session start.
// Using require_once ensures it's included only one time.
require_once('config.php');

// Set default values for the agent's name and avatar
$agent_name = "Agent";
$avatar_path = "assets/images/users/default-avatar.png"; // A default image in case one isn't set

// Check if the agent is logged in by checking the session
if (isset($_SESSION['agent_id'])) {
    $agent_id = $_SESSION['agent_id'];

    // Prepare and execute a query to get the agent's details from the 'agents' table
    $stmt = $conn->prepare("SELECT first_name, last_name, avatar FROM agents WHERE id = ?");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $agent_data = $result->fetch_assoc();
        // Construct the full name
        $agent_name = trim($agent_data['first_name'] . ' ' . $agent_data['last_name']);
        
        // Construct the full path to the avatar image, assuming it's in the Agent/upload/avatars folder
        if (!empty($agent_data['avatar'])) {
            $avatar_path = 'uploads/avatars/' . $agent_data['avatar'];
        }
    }
    $stmt->close();
}
?>

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
            </form>

        <div class="nav-right col-6 pull-right right-header p-0">
            <ul class="nav-menus">
                <li>
                    <div class="mode">
                        <i class="ri-moon-line"></i>
                    </div>
                </li>
                <li class="profile-nav onhover-dropdown pe-0 me-0">
                    <div class="media profile-media">
                        <img class="user-profile rounded-circle" src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Agent Avatar">
                        <div class="user-name-hide media-body">
                            <span><?php echo htmlspecialchars($agent_name); ?></span>
                            <p class="mb-0 font-roboto">Agent<i class="middle ri-arrow-down-s-line"></i></p>
                        </div>
                    </div>
                    <ul class="profile-dropdown onhover-show-div">
                        <li>
                            <a href="dashboard.php">
                                <i data-feather="home"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="profile-setting.php"> <i data-feather="settings"></i>
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