<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>G-Microfinance | Empowering Communities, Growing Futures</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Google Fonts (Outfit) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Remixicons for premium icons -->
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --primary: #0f5132; /* Forest Green */
            --primary-light: #d1e7dd;
            --primary-dark: #0a3622;
            --accent: #ffc107; /* Gold */
            --accent-hover: #e0a800;
            --bg-glass: rgba(255, 255, 255, 0.85);
            --bg-glass-dark: rgba(15, 81, 50, 0.05);
            --shadow-premium: 0 10px 30px -10px rgba(15, 81, 50, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            color: #2b303a;
            background-color: #f8faf9;
            overflow-x: hidden;
        }

        /* Navbar Styling */
        .navbar {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(15, 81, 50, 0.08);
            padding: 0.8rem 0;
            transition: var(--transition);
        }

        .navbar-brand img {
            max-height: 55px;
            object-fit: contain;
            transition: var(--transition);
        }

        .navbar-brand img:hover {
            transform: scale(1.02);
        }

        .nav-link {
            font-weight: 500;
            color: #4a5568 !important;
            padding: 0.5rem 1.2rem !important;
            border-radius: 8px;
            transition: var(--transition);
        }

        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background: rgba(15, 81, 50, 0.05);
        }

        .btn-portal-agent {
            border: 2px solid var(--primary);
            color: var(--primary) !important;
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            transition: var(--transition);
            margin-right: 0.5rem;
        }

        .btn-portal-agent:hover {
            background: var(--primary);
            color: white !important;
            transform: translateY(-2px);
        }

        .btn-portal-admin {
            background: var(--primary);
            color: white !important;
            font-weight: 600;
            padding: 0.5rem 1.2rem;
            border-radius: 8px;
            border: 2px solid var(--primary);
            transition: var(--transition);
        }

        .btn-portal-admin:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(15, 81, 50, 0.2);
        }

        /* Hero Section */
        .hero-section {
            padding: 8rem 0 6rem 0;
            background: radial-gradient(circle at 10% 20%, rgba(209, 231, 221, 0.3) 0%, rgba(255, 255, 255, 0) 90%);
            position: relative;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
        }

        .hero-title span {
            background: linear-gradient(135deg, var(--primary) 0%, #2b8a3e 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-subtitle {
            font-size: 1.15rem;
            color: #5a6578;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .hero-ctas {
            display: flex;
            gap: 1rem;
        }

        .btn-premium {
            background: var(--primary);
            color: white;
            padding: 0.8rem 2rem;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 10px;
            border: none;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(15, 81, 50, 0.15);
        }

        .btn-premium:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 81, 50, 0.25);
        }

        .btn-premium-outline {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 0.8rem 2rem;
            font-size: 1.05rem;
            font-weight: 600;
            border-radius: 10px;
            transition: var(--transition);
        }

        .btn-premium-outline:hover {
            background: rgba(15, 81, 50, 0.05);
            transform: translateY(-2px);
        }

        .hero-image-container {
            position: relative;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }

        /* Section Title Styling */
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-header h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }

        .section-header p {
            color: #64748b;
            max-width: 600px;
            margin: 0 auto;
            font-size: 1.1rem;
        }

        /* Interactive Schemes Section */
        .schemes-section {
            padding: 6rem 0;
            background: #ffffff;
            border-top: 1px solid rgba(15, 81, 50, 0.05);
        }

        .scheme-nav {
            display: inline-flex;
            background: #f1f5f9;
            padding: 0.4rem;
            border-radius: 50px;
            margin-bottom: 3rem;
        }

        .scheme-nav-btn {
            border: none;
            background: transparent;
            padding: 0.6rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            color: #64748b;
            transition: var(--transition);
        }

        .scheme-nav-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 10px rgba(15, 81, 50, 0.15);
        }

        .scheme-card {
            background: #ffffff;
            border: 1px solid rgba(15, 81, 50, 0.08);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            transition: var(--transition);
            box-shadow: var(--shadow-premium);
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .scheme-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary);
            opacity: 0;
            transition: var(--transition);
        }

        .scheme-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -10px rgba(15, 81, 50, 0.2);
            border-color: rgba(15, 81, 50, 0.15);
        }

        .scheme-card:hover::before {
            opacity: 1;
        }

        .scheme-icon-box {
            width: 60px;
            height: 60px;
            background: var(--primary-light);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
        }

        .scheme-rate {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 1rem 0;
        }

        .scheme-rate span {
            font-size: 0.95rem;
            color: #64748b;
            font-weight: 500;
        }

        .scheme-features-list {
            list-style: none;
            padding: 0;
            margin: 1.5rem 0;
            text-align: left;
        }

        .scheme-features-list li {
            padding: 0.4rem 0;
            color: #475569;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .scheme-features-list li i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        /* About Us Section */
        .about-section {
            padding: 6rem 0;
        }

        .feature-icon-wrapper {
            width: 48px;
            height: 48px;
            background: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            color: var(--primary);
            box-shadow: var(--shadow-premium);
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid rgba(15, 81, 50, 0.05);
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-premium);
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        /* Mobile App Section */
        .app-section {
            padding: 6rem 0;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 30px;
            margin: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .app-section::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .app-feature-item {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .app-feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .btn-download-apk {
            background: var(--accent);
            color: var(--primary-dark) !important;
            font-weight: 700;
            padding: 0.9rem 2.2rem;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1.05rem;
            border: none;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.25);
        }

        .btn-download-apk:hover {
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.2);
        }

        /* Contact Section */
        .contact-section {
            padding: 6rem 0;
        }

        .contact-info-card {
            background: #ffffff;
            border: 1px solid rgba(15, 81, 50, 0.08);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow-premium);
            height: 100%;
        }

        .contact-details-list {
            list-style: none;
            padding: 0;
            margin: 2rem 0 0 0;
        }

        .contact-details-list li {
            display: flex;
            gap: 1.2rem;
            margin-bottom: 1.8rem;
            align-items: flex-start;
        }

        .contact-icon-box {
            width: 46px;
            height: 46px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }

        .contact-details-list h6 {
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 0.2rem;
        }

        .contact-details-list p {
            color: #64748b;
            margin: 0;
            font-size: 0.95rem;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: var(--primary);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(15, 81, 50, 0.15);
        }

        .contact-form-card {
            background: #ffffff;
            border: 1px solid rgba(15, 81, 50, 0.08);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: var(--shadow-premium);
        }

        /* Footer */
        .footer {
            background: var(--primary-dark);
            color: rgba(255, 255, 255, 0.8);
            padding: 4rem 0 2rem 0;
            border-top: 5px solid var(--accent);
        }

        .footer-logo {
            max-height: 60px;
            margin-bottom: 1.5rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: var(--transition);
        }

        .footer-links a:hover {
            color: var(--accent);
            padding-left: 5px;
        }

        .social-btn {
            width: 38px;
            height: 38px;
            background: rgba(255, 255, 255, 0.08);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            transition: var(--transition);
            text-decoration: none;
        }

        .social-btn:hover {
            background: var(--accent);
            color: var(--primary-dark);
            transform: translateY(-3px);
        }

        .footer-bottom {
            margin-top: 3rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <img src="Super/assets/images/logo/1.png?v=<?php echo time(); ?>" alt="G-Microfinance Logo" style="max-height: 45px;">
                <span class="fw-bold fs-4" style="color: var(--primary-dark); font-family: 'Outfit', sans-serif; letter-spacing: -0.5px;">G-Microfinance</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center mb-3 mb-lg-0">
                    <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#schemes">Schemes</a></li>
                    <li class="nav-item"><a class="nav-link" href="#app">Mobile App</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row ms-lg-3 gap-2 align-items-stretch">
                    <a href="Agents/index.php" class="btn btn-portal-agent text-center"><i class="ri-user-follow-line me-1"></i> Agent Login</a>
                    <a href="Super/index.php" class="btn btn-portal-admin text-center"><i class="ri-shield-user-line me-1"></i> Admin Login</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 text-start">
                    <div class="hero-badge">
                        <i class="ri-sparkling-fill"></i> Financial Inclusion & Growth
                    </div>
                    <h1 class="hero-title">Empowering Your <span>Financial Journey</span></h1>
                    <p class="hero-subtitle">
                        Your trusted, transparent, and community-focused microfinance partner. We offer direct lending support and deposit savings schemes to help local communities prosper.
                    </p>
                    <div class="hero-ctas">
                        <a href="#schemes" class="btn btn-premium">View Our Schemes</a>
                        <a href="#contact" class="btn btn-premium-outline">Contact An Agent</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="hero-image-container">
                        <img src="assets/images/microfinance_hero.png" class="img-fluid rounded-4 shadow-sm" alt="Empowering Communities">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Container -->
    <main>

        <!-- About Us Section -->
        <section id="about" class="about-section container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 text-start">
                    <div class="section-header text-start mb-4">
                        <h2>Fostering Community Progress</h2>
                        <p class="m-0">G-Microfinance is dedicated to providing fair and accessible financial micro-loans and deposit services to build small businesses and empower individuals.</p>
                    </div>
                    
                    <div class="d-flex gap-3 mb-4">
                        <div class="feature-icon-wrapper">
                            <i class="ri-seedling-line"></i>
                        </div>
                        <div>
                            <h5>Local Support Networks</h5>
                            <p class="text-muted">Our dedicated local agents visit you directly to help with paperwork, deposits, and payments.</p>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="feature-icon-wrapper">
                            <i class="ri-shake-hands-line"></i>
                        </div>
                        <div>
                            <h5>Absolute Transparency</h5>
                            <p class="text-muted">No hidden fees or surprises. View your EMI due structure, collections, and statuses clearly at any time.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="row g-4 text-start">
                        <div class="col-sm-6">
                            <div class="stat-card">
                                <div class="stat-number">10K+</div>
                                <h6 class="fw-bold">Active Members</h6>
                                <p class="text-muted mb-0">Relying on us daily for their financial growth.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="stat-card">
                                <div class="stat-number">98%</div>
                                <h6 class="fw-bold">Repayment Rate</h6>
                                <p class="text-muted mb-0">Fostered through supportive and flexible terms.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="stat-card">
                                <div class="stat-number">100+</div>
                                <h6 class="fw-bold">Field Agents</h6>
                                <p class="text-muted mb-0">Delivering doorstep assistance daily.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="stat-card">
                                <div class="stat-number">₹15Cr+</div>
                                <h6 class="fw-bold">Disbursed</h6>
                                <p class="text-muted mb-0">Directly supporting small community enterprise.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Dynamic Schemes Section -->
        <section id="schemes" class="schemes-section">
            <div class="container text-center">
                <div class="section-header">
                    <h2>Our Financial Schemes</h2>
                    <p>Select a product to view structured interest rates and terms tailored to match your specific needs.</p>
                </div>

                <div class="scheme-nav">
                    <button class="scheme-nav-btn active" id="loan-tab-btn" onclick="switchTab('loan')">Loan Products</button>
                    <button class="scheme-nav-btn" id="rd-tab-btn" onclick="switchTab('rd')">Recurring Deposits</button>
                </div>

                <!-- Loan Cards Container -->
                <div class="row g-4 justify-content-center" id="loan-cards-container">
                    <div class="col-md-4 text-start">
                        <div class="scheme-card">
                            <div>
                                <div class="scheme-icon-box">
                                    <i class="ri-user-3-line"></i>
                                </div>
                                <h4 class="fw-bold">Personal Loan</h4>
                                <p class="text-muted">Designed for personal requirements, emergency bills, or home upgrades.</p>
                                <div class="scheme-rate">12.0% <span>p.a.</span></div>
                            </div>
                            <ul class="scheme-features-list">
                                <li><i class="ri-checkbox-circle-line"></i> Short/medium tenure (12-24m)</li>
                                <li><i class="ri-checkbox-circle-line"></i> Flexible daily/weekly EMIs</li>
                                <li><i class="ri-checkbox-circle-line"></i> Doorstep agent collections</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4 text-start">
                        <div class="scheme-card">
                            <div>
                                <div class="scheme-icon-box">
                                    <i class="ri-store-2-line"></i>
                                </div>
                                <h4 class="fw-bold">Business Loan</h4>
                                <p class="text-muted">Capital support to expand retail inventory, workshops, or machinery.</p>
                                <div class="scheme-rate">10.5% <span>p.a.</span></div>
                            </div>
                            <ul class="scheme-features-list">
                                <li><i class="ri-checkbox-circle-line"></i> Tenure up to 36 months</li>
                                <li><i class="ri-checkbox-circle-line"></i> Quick admin review & approval</li>
                                <li><i class="ri-checkbox-circle-line"></i> Minimal document validation</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4 text-start">
                        <div class="scheme-card">
                            <div>
                                <div class="scheme-icon-box">
                                    <i class="ri-plant-line"></i>
                                </div>
                                <h4 class="fw-bold">Agriculture Loan</h4>
                                <p class="text-muted">Supporting local farming, seed purchasing, or irrigation upgrades.</p>
                                <div class="scheme-rate">8.0% <span>p.a.</span></div>
                            </div>
                            <ul class="scheme-features-list">
                                <li><i class="ri-checkbox-circle-line"></i> Dynamic crop cycle terms</li>
                                <li><i class="ri-checkbox-circle-line"></i> Extremely low interest metrics</li>
                                <li><i class="ri-checkbox-circle-line"></i> Collateral-free options</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- RD Cards Container (Hidden by Default) -->
                <div class="row g-4 justify-content-center d-none" id="rd-cards-container">
                    <div class="col-md-4 text-start">
                        <div class="scheme-card">
                            <div>
                                <div class="scheme-icon-box">
                                    <i class="ri-calendar-todo-line"></i>
                                </div>
                                <h4 class="fw-bold">Short-Term RD</h4>
                                <p class="text-muted">Perfect for festive purchases or short financial savings targets.</p>
                                <div class="scheme-rate">6.5% <span>p.a.</span></div>
                            </div>
                            <ul class="scheme-features-list">
                                <li><i class="ri-checkbox-circle-line"></i> 12 Months maturity tenure</li>
                                <li><i class="ri-checkbox-circle-line"></i> Daily/weekly deposit options</li>
                                <li><i class="ri-checkbox-circle-line"></i> Guaranteed principal + returns</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4 text-start">
                        <div class="scheme-card">
                            <div>
                                <div class="scheme-icon-box">
                                    <i class="ri-time-line"></i>
                                </div>
                                <h4 class="fw-bold">Medium-Term RD</h4>
                                <p class="text-muted">Targeted savings for asset building, education funding, or appliances.</p>
                                <div class="scheme-rate">7.2% <span>p.a.</span></div>
                            </div>
                            <ul class="scheme-features-list">
                                <li><i class="ri-checkbox-circle-line"></i> 24 to 36 Months tenure</li>
                                <li><i class="ri-checkbox-circle-line"></i> Higher compound yield</li>
                                <li><i class="ri-checkbox-circle-line"></i> Doorstep agent log collection</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4 text-start">
                        <div class="scheme-card">
                            <div>
                                <div class="scheme-icon-box">
                                    <i class="ri-safe-2-line"></i>
                                </div>
                                <h4 class="fw-bold">Long-Term RD</h4>
                                <p class="text-muted">Securing future goals, pension backing, or marriage requirements.</p>
                                <div class="scheme-rate">8.0% <span>p.a.</span></div>
                            </div>
                            <ul class="scheme-features-list">
                                <li><i class="ri-checkbox-circle-line"></i> 60+ Months maturity</li>
                                <li><i class="ri-checkbox-circle-line"></i> Maximum interest multipliers</li>
                                <li><i class="ri-checkbox-circle-line"></i> Secure vault verification</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mobile App Section -->
        <section id="app" class="container">
            <div class="app-section px-4 px-md-5">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6 text-start">
                        <h2 class="fw-extrabold mb-3" style="font-size: 2.5rem; font-weight: 800;">Get the G-Microfinance App</h2>
                        <p class="mb-4" style="color: rgba(255,255,255,0.85); font-size: 1.05rem;">
                            Empower your pocket. Download our Android application to view loan histories, verify deposit schedules, track remaining EMIs, and receive immediate receipt notifications directly on your phone.
                        </p>
                        
                        <div class="app-feature-item">
                            <div class="app-feature-icon"><i class="ri-barcode-box-line"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1">Instant QR Code Receipts</h6>
                                <p class="mb-0 text-white-50" style="font-size: 0.9rem;">Scan agent codes and get secure, digitally signed payments logs instantly.</p>
                            </div>
                        </div>

                        <div class="app-feature-item">
                            <div class="app-feature-icon"><i class="ri-line-chart-line"></i></div>
                            <div>
                                <h6 class="fw-bold mb-1">Live Savings Progress</h6>
                                <p class="mb-0 text-white-50" style="font-size: 0.9rem;">Track maturity progress of all your active RD accounts on a visual chart.</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="app/gmicrofinance.apk" class="btn btn-download-apk">
                                <i class="ri-android-line" style="font-size: 1.4rem;"></i>
                                <span>Download Customer App APK</span>
                            </a>
                        </div>
                    </div>
                    <div class="col-lg-6 text-center">
                        <img src="assets/images/mobile_app_mockup.png" class="img-fluid" alt="Mobile App Screen Mockup" style="max-height: 480px; object-fit: contain;">
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Us Section -->
        <section id="contact" class="contact-section container">
            <div class="row g-5">
                <div class="col-lg-5 text-start">
                    <div class="contact-info-card">
                        <h3 class="fw-bold" style="color: var(--primary-dark);">Reach Out To Us</h3>
                        <p class="text-muted">Have any queries regarding our loan products, RD interest plans, or portals access? Get in touch with our administrative office directly.</p>
                        
                        <ul class="contact-details-list">
                            <li>
                                <div class="contact-icon-box"><i class="ri-map-pin-line"></i></div>
                                <div>
                                    <h6>Corporate Head Office</h6>
                                    <p>G-Microfinance Building, 4th Floor, Commercial Block, Sector-V, Kolkata, WB, India</p>
                                </div>
                            </li>
                            <li>
                                <div class="contact-icon-box"><i class="ri-phone-line"></i></div>
                                <div>
                                    <h6>Support Line Phone</h6>
                                    <p>+91 (033) 2456-7890 / +91 98765 43210</p>
                                </div>
                            </li>
                            <li>
                                <div class="contact-icon-box"><i class="ri-mail-send-line"></i></div>
                                <div>
                                    <h6>Support Email Address</h6>
                                    <p>support@gmicrofinancefoundation.com</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="col-lg-7 text-start">
                    <div class="contact-form-card">
                        <h4 class="fw-bold mb-4" style="color: var(--primary-dark);">Send Us a Message</h4>
                        <form action="#" method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="floatingName" placeholder="Your Name" required>
                                        <label for="floatingName">Your Full Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="floatingEmail" placeholder="name@example.com" required>
                                        <label for="floatingEmail">Email Address</label>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="floatingSubject" placeholder="Subject" required>
                                        <label for="floatingSubject">Subject / Query Topic</label>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-floating">
                                        <textarea class="form-control" placeholder="Leave a message here" id="floatingTextarea" style="height: 120px" required></textarea>
                                        <label for="floatingTextarea">Your Message</label>
                                    </div>
                                </div>
                                <div class="col-12 mt-4">
                                    <button class="btn btn-premium w-100 py-3" type="submit">Send Message <i class="ri-send-plane-line ms-1"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container text-start">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <img src="Super/assets/images/logo/1.png?v=<?php echo time(); ?>" alt="G-Microfinance Logo" style="max-height: 50px;">
                        <span class="fw-bold fs-4 text-white" style="font-family: 'Outfit', sans-serif;">G-Microfinance</span>
                    </div>
                    <p class="mb-4 text-white-50" style="max-width: 380px;">Empowering marginalized sectors, local businesses, and households by building robust micro-credit networks and transparent recurring deposits.</p>
                    <div>
                        <a href="#" class="social-btn"><i class="ri-facebook-fill"></i></a>
                        <a href="#" class="social-btn"><i class="ri-twitter-x-fill"></i></a>
                        <a href="#" class="social-btn"><i class="ri-linkedin-fill"></i></a>
                        <a href="#" class="social-btn"><i class="ri-youtube-fill"></i></a>
                    </div>
                </div>
                
                <div class="col-sm-6 col-lg-3">
                    <h5 class="fw-bold mb-4 text-white">Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#">Home</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#schemes">Schemes & Rates</a></li>
                        <li><a href="#app">Customer Mobile App</a></li>
                        <li><a href="#contact">Contact Support</a></li>
                    </ul>
                </div>

                <div class="col-sm-6 col-lg-4">
                    <h5 class="fw-bold mb-4 text-white">Associated Portals</h5>
                    <p class="text-white-50 mb-3">Login to access dedicated dashboard management systems.</p>
                    <div class="d-flex flex-column gap-2" style="max-width: 240px;">
                        <a href="Agents/index.php" class="btn btn-premium-outline text-white border-white-50 text-center py-2" style="font-size: 0.9rem;"><i class="ri-user-settings-line me-1"></i> Field Agent Console</a>
                        <a href="Super/index.php" class="btn btn-premium text-center py-2" style="font-size: 0.9rem;"><i class="ri-admin-line me-1"></i> Super Admin Control</a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom text-center text-white-50">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> G-Microfinance. All Rights Reserved. Designed for premium community empowerment.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Interactive Scripts -->
    <script>
        function switchTab(tabType) {
            const loanBtn = document.getElementById('loan-tab-btn');
            const rdBtn = document.getElementById('rd-tab-btn');
            const loanContainer = document.getElementById('loan-cards-container');
            const rdContainer = document.getElementById('rd-cards-container');

            if (tabType === 'loan') {
                loanBtn.classList.add('active');
                rdBtn.classList.remove('active');
                loanContainer.classList.remove('d-none');
                rdContainer.classList.add('d-none');
            } else {
                rdBtn.classList.add('active');
                loanBtn.classList.remove('active');
                rdContainer.classList.remove('d-none');
                loanContainer.classList.add('d-none');
            }
        }
    </script>
</body>
</html>