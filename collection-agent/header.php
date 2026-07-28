<?php
require_once('config.php');

$agent_name = "Agent";
$avatar_path = "assets/images/users/default-avatar.png";

if (isset($_SESSION['collection_agent_id'])) {
    $agent_id = $_SESSION['collection_agent_id'];

    $stmt = $conn->prepare("SELECT first_name, last_name, avatar FROM agents WHERE id = ?");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $agent_data = $result->fetch_assoc();
        $agent_name = trim($agent_data['first_name'] . ' ' . $agent_data['last_name']);

        if (!empty($agent_data['avatar'])) {
            // Avatars are stored under the Agents portal uploads
            $avatar_path = '../Agents/uploads/avatars/' . $agent_data['avatar'];
        }
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
                            <p class="mb-0 font-roboto">Collection Agent<i class="middle ri-arrow-down-s-line"></i></p>
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
