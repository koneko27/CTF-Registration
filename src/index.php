<?php
require_once __DIR__ . '/api/csp_nonce.php';
$nonce = generate_csp_nonce();
header('Content-Security-Policy: ' . get_csp_header($nonce));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koneko CTF - Capture The Flag Competition</title>
    <style nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
        :root { --bg-primary: #0a0a0f; --primary-color: #00ffff; --glass-border: rgba(255, 255, 255, 0.1); --text-secondary: #b8b8b8; --font-primary: 'Orbitron', monospace; }
        #page-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg-primary); z-index: 9999; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: opacity 0.2s ease, visibility 0.2s ease; }
        #page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
        .loader-spinner { width: 50px; height: 50px; border: 3px solid var(--glass-border); border-top-color: var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 1rem; }
        .loader-text { color: var(--text-secondary); font-family: var(--font-primary); font-size: 1.2rem; letter-spacing: 2px; animation: pulse 1.5s ease-in-out infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @keyframes pulse { 0%, 100% { opacity: 0.6; } 50% { opacity: 1; } }
    </style>
    <link rel="stylesheet" href="assets/css/styles.css">`n    <link rel="stylesheet" href="assets/css/utility.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div id="page-loader">
        <div class="loader-spinner"></div>
        <div class="loader-text">INITIALIZING SYSTEM...</div>
    </div>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <i class="fa-solid fa-shield-halved fa-flip"></i>
                <span>Koneko CTF</span>
            </div>
            <div class="nav-menu" id="nav-menu">
                <a href="#home" class="nav-link active" data-page="home">Home</a>
                <a href="#competitions" class="nav-link" data-page="competitions">Competitions</a>
                <a href="#help" class="nav-link" data-page="help">Help</a>
                <a href="#dashboard" class="nav-link" data-page="dashboard">Dashboard</a>
                <a href="#profile" class="nav-link" data-page="profile">Profile</a>
                <a href="#admin" class="nav-link admin-link display-none" id="admin-link" data-page="admin">
                    <i class="fas fa-crown"></i> Admin Panel
                </a>
                <a href="#logout" class="nav-link" data-page="logout">Log Out</a>
                <a href="#signin" class="nav-link" data-page="signin">Sign In</a>
                <a href="#signup" class="nav-link" data-page="signup">Sign Up</a>
            </div>
            <div class="nav-actions">
                <button class="theme-toggle" id="theme-toggle" aria-label="Switch to light mode" aria-pressed="false">
                    <i class="fas fa-sun"></i>
                </button>
                <div class="nav-toggle" id="nav-toggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </nav>

    <main id="main-content">
        <div id="not-found" class="page">
            <section class="auth-section">
                <div class="container">
                    <div class="auth-content" style="text-align: center; display: block;">
                        <div class="auth-mascot" style="margin-bottom: 2rem;">
                            <div class="cat-mascot">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye" style="height: 2px; border-radius: 0;"></div>
                                            <div class="eye right-eye" style="height: 2px; border-radius: 0;"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left" style="transform: rotate(15deg);"></div>
                                            <div class="mouth-right" style="transform: rotate(-15deg);"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left" style="transform: rotate(-20deg);"></div>
                                        <div class="ear ear-right" style="transform: rotate(20deg);"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble">
                                <span class="speech-text">Wait, where are we?</span>
                            </div>
                        </div>
                        
                        <h1 style="font-family: var(--font-primary); font-size: 4rem; color: var(--primary-color); margin-bottom: 1rem;">404</h1>
                        <h2 style="color: var(--text-primary); margin-bottom: 1rem;">Page Not Found</h2>
                        <p style="color: var(--text-secondary); margin-bottom: 2rem; font-size: 1.1rem;">
                            The page you are looking for seems to have vanished into the digital void.<br>
                            Or maybe the cat ate it.
                        </p>
                        
                        <button class="btn btn-primary" data-action="navigate" data-page="home">
                            <i class="fas fa-home"></i>
                            Return Home
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <div id="home" class="page active">
            <section class="hero">
                <div class="container">
                    <div class="hero-content">
                        <div class="hero-text">
                            <h1 class="hero-title">
                                <span class="glitch-text">Koneko</span>
                                <span class="accent-text">CTF</span>
                            </h1>
                            <p class="hero-description">
                                Join the ultimate cybersecurity challenge. Test your skills, learn new techniques, 
                                and compete with the best hackers worldwide.
                            </p>
                            <div class="hero-buttons">
                                <button class="btn btn-primary" data-action="navigate" data-page="signup">
                                    <i class="fas fa-rocket"></i>
                                    Get Started
                                </button>
                                <button class="btn btn-outline" data-action="navigate" data-page="competitions">
                                    <i class="fas fa-trophy"></i>
                                    View Competitions
                                </button>
                            </div>
                        </div>
                        <div class="hero-mascot">
                            <div class="cat-mascot" id="hero-cat">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                        <div class="cat-whiskers">
                                            <div class="whisker whisker-left"></div>
                                            <div class="whisker whisker-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                                <div class="cat-laptop">
                                    <div class="laptop-screen"></div>
                                    <div class="laptop-keyboard"></div>
                                </div>
                            </div>
                            <div class="speech-bubble" id="speech-bubble">
                                <span id="speech-text">Ready to hack the matrix?</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stats-section">
                        <div class="stat-item">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Platform Uptime</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">100%</div>
                            <div class="stat-label">Secure</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="features">
                <div class="container">
                    <h2 class="section-title">Why Choose Koneko CTF?</h2>
                    <p class="section-subtitle">Our platform provides everything you need to excel in cybersecurity competitions</p>
                    
                    <div class="features-grid">
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3>Secure Platform</h3>
                            <p>Advanced security measures to protect your data and submissions</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-trophy"></i>
                            </div>
                            <h3>Competitive Events</h3>
                            <p>Join exciting CTF competitions with various difficulty levels</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3>Community</h3>
                            <p>Connect with fellow hackers and cybersecurity enthusiasts</p>
                        </div>
                        <div class="feature-card">
                            <div class="feature-icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <h3>Real-time Updates</h3>
                            <p>Get instant notifications about competition updates and results</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="cta-section">
                <div class="container">
                    <div class="cta-content">
                        <div class="cta-mascot">
                            <div class="cat-mascot small">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble small">
                                <span class="speech-text">Join us meow!</span>
                            </div>
                        </div>
                        <h2>Ready to Start Your Journey?</h2>
                        <p>Join thousands of cybersecurity enthusiasts and start competing in exciting CTF challenges today.</p>
                        <div class="cta-buttons">
                            <button class="btn btn-primary" data-action="navigate" data-page="signup">
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </button>
                            <button class="btn btn-outline" data-action="navigate" data-page="signin">
                                <i class="fas fa-sign-in-alt"></i>
                                Sign In
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="competitions" class="page">
            <section class="page-header">
                <div class="container">
                    <div class="header-content">
                        <div class="header-mascot">
                            <div class="cat-mascot small">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble small">
                                <span class="speech-text">Ready for some challenges?</span>
                            </div>
                        </div>
                        <div class="header-text">
                            <h1>CTF Competitions</h1>
                            <p>Join exciting cybersecurity competitions and test your skills against hackers worldwide</p>
                        </div>
                    </div>
                    
                    <div class="filters">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search competitions..." id="search-input">
                        </div>
                        <div class="filter-buttons">
                            <button class="filter-btn active" data-filter="all">All Status</button>
                            <button class="filter-btn" data-filter="upcoming">Upcoming</button>
                            <button class="filter-btn" data-filter="ongoing">Ongoing</button>
                            <button class="filter-btn" data-filter="ended">Ended</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="competitions-grid">
                <div class="container">
                    <div class="competitions-list" id="competitions-list">
                    </div>
                </div>
            </section>
        </div>

        <div id="dashboard" class="page">
            <section class="dashboard-header">
                <div class="container">
                    <div class="dashboard-welcome">
                        <div class="welcome-mascot">
                            <div class="cat-mascot">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble">
                                <span class="speech-text"></span>
                            </div>
                        </div>
                        <div class="welcome-text">
                            <h1>Dashboard</h1>
                            <p>Monitor your progress, explore new challenges, and keep track of your cybersecurity journey all in one place.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-stats">
                <div class="container">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-flag"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-label">Joined Competitions</div>
                                <div class="stat-value">0</div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="dashboard-content">
                <div class="container">
                    <div class="dashboard-grid">
                        <div class="main-content">
                            <div class="section-header">
                                <h2>My Competitions</h2>
                                <button class="btn btn-outline btn-sm" data-action="navigate" data-page="competitions">
                                    <i class="fas fa-eye"></i>
                                    View All
                                </button>
                            </div>
                            <div class="competitions-dashboard" id="dashboard-competitions">
                            </div>
                            <div class="section-subheader">
                                <h3>Ongoing Competitions</h3>
                            </div>
                            <div class="competitions-banner-grid" id="dashboard-ongoing">
                            </div>
                        </div>
                        
                        <div class="sidebar">
                            
                            <div class="sidebar-section">
                                <h3>Recent Activity</h3>
                                <div class="sidebar-scroll" data-scroll="recent-activity">
                                    <div class="activity-feed" id="activity-feed">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="profile" class="page">
            <section class="profile-header">
                <div class="container">
                    <div class="profile-welcome">
                        <div class="profile-mascot">
                            <div class="cat-mascot small">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble small">
                                <span class="speech-text"></span>
                            </div>
                        </div>
                        <div class="profile-title">
                            <h1>Profile Management</h1>
                            <p>Update your information and security settings</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="profile-content">
                <div class="container">
                    <div class="profile-tabs">
                        <button class="tab-btn active" data-tab="info">
                            <i class="fas fa-user"></i>
                            Profile Info
                        </button>
                    </div>

                    <div class="tab-content">
                        <div id="info-tab" class="tab-pane active">
                            <div class="profile-form-section">
                                <div class="section-header">
                                    <h2>Profile Information</h2>
                                    <button class="btn btn-outline btn-sm" id="edit-profile-btn">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </button>
                                </div>
                                
                                <div class="profile-form">
                                    <div class="profile-avatar-section">
                                        <div class="avatar-upload">
                                            <div class="avatar-preview">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <button class="btn btn-sm btn-outline">
                                                <i class="fas fa-camera"></i>
                                                Change Avatar
                                            </button>
                                        </div>
                                        <div class="profile-basic-info">
                                            <h3>John "Koneko" Hacker</h3>
                                            <p>john@example.com</p>
                                            <p><i class="fas fa-map-marker-alt"></i> Jakarta, Indonesia</p>
                                            <p class="profile-bio profile-bio-hidden"><i class="fas fa-quote-left"></i> <span class="bio-text"></span></p>
                                            <p><i class="fas fa-calendar"></i> Joined 1/15/2024</p>
                                        </div>
                                    </div>
                                    
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>Full Name</label>
                                            <input type="text" name="full_name" value="" maxlength="30" disabled>
                                        </div>
                                        <div class="form-group">
                                            <label>Email Address</label>
                                            <input type="email" name="email" value="" disabled>
                                        </div>
                                        <div class="form-group">
                                            <label>Location</label>
                                            <input type="text" name="location" value="" disabled>
                                        </div>
                                        <div class="form-group full-width">
                                            <label>Bio</label>
                                            <textarea name="bio" disabled></textarea>
                                            <div class="char-counter">0/500</div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions" id="profile-actions">
                                        <button class="btn btn-primary">
                                            <i class="fas fa-save"></i>
                                            Save Changes
                                        </button>
                                        <button class="btn btn-outline" id="cancel-edit-btn">
                                            <i class="fas fa-times"></i>
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="profile-form-section">
                                <div class="section-header">
                                    <h2>Change Password</h2>
                                </div>
                                <div class="security-form">
                                    <form id="change-password-form">
                                        <div class="form-group">
                                            <label>Current Password</label>
                                            <div class="password-input">
                                                <input type="password" name="currentPassword" placeholder="Enter current password" required>
                                                <button class="password-toggle" type="button">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <div class="password-input">
                                                <input type="password" name="newPassword" placeholder="Enter new password" required>
                                                <button class="password-toggle" type="button">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm New Password</label>
                                            <div class="password-input">
                                                <input type="password" name="confirmPassword" placeholder="Confirm new password" required>
                                                <button class="password-toggle" type="button">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-key"></i>
                                            Update Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="signin" class="page">
            <section class="auth-section">
                <div class="container">
                    <div class="auth-content">
                        <div class="auth-mascot">
                            <div class="cat-mascot">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble">
                                <span class="speech-text">Welcome back, hacker!</span>
                            </div>
                        </div>
                        
                        <div class="auth-form">
                            <h1>Sign In</h1>
                            <p>Welcome back! Please sign in to your account.</p>
                            
                            <form id="signin-form">
                                <div class="form-group">
                                    <label>Email or Username</label>
                                    <input type="text" name="identifier" placeholder="Enter your email or username" required>
                                </div>
                                <div class="form-group">
                                    <label>Password</label>
                                    <div class="password-input">
                                        <input type="password" name="password" placeholder="Enter your password" required>
                                        <button class="password-toggle" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-options">
                                    <label class="checkbox">
                                        <input type="checkbox" name="rememberMe">
                                        <span class="checkmark"></span>
                                        Remember me for 30 days
                                    </label>
                                    <a href="#forgot-password" class="forgot-password">Forgot password?</a>
                                </div>
                                <button type="submit" class="btn btn-primary btn-full">
                                    <i class="fas fa-sign-in-alt"></i>
                                    Sign In
                                </button>
                            </form>
                            
                            <div class="auth-footer">
                                <p>Don't have an account? <a href="#signup" data-action="navigate" data-page="signup">Sign up here</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="signup" class="page">
            <section class="auth-section">
                <div class="container">
                    <div class="auth-content">
                        <div class="auth-mascot">
                            <div class="cat-mascot">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble">
                                <span class="speech-text">Join the hacker family!</span>
                            </div>
                        </div>
                        
                        <div class="auth-form">
                            <h1>Sign Up</h1>
                            <p>Create your account and start your cybersecurity journey.</p>
                            
                            <form id="signup-form">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" name="fullName" placeholder="Enter your full name" maxlength="30" required>
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" placeholder="Enter your email address" required>
                                </div>
                                <div class="form-group">
                                    <label>Username</label>
                                    <input type="text" name="username" placeholder="Choose a username" maxlength="30" required>
                                </div>
                                <div class="form-group">
                                    <label>Password</label>
                                    <div class="password-input">
                                        <input type="password" name="password" placeholder="Create a strong password" required>
                                        <button class="password-toggle" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="strength-bar">
                                            <div class="strength-fill"></div>
                                        </div>
                                        <span class="strength-text">Password strength: Weak</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm Password</label>
                                    <div class="password-input">
                                        <input type="password" name="confirmPassword" placeholder="Confirm your password" required>
                                        <button class="password-toggle" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-full">
                                    <i class="fas fa-user-plus"></i>
                                    Create Account
                                </button>
                            </form>
                            
                            <div class="auth-footer">
                                <p>Already have an account? <a href="#signin" data-action="navigate" data-page="signin">Sign in here</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>


        <div id="forgot-password" class="page">
            <section class="auth-section">
                <div class="container">
                    <div class="auth-content">
                        <div class="auth-mascot">
                            <div class="cat-mascot">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble">
                                <span class="speech-text">Need help with your password?</span>
                            </div>
                        </div>
                        
                        <div class="auth-form">
                            <h1>Forgot Password</h1>
                            <p>Enter your email address and we'll send you a link to reset your password.</p>
                            
                            <form id="forgot-password-form">
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" placeholder="Enter your email address" required>
                                </div>
                                <button type="submit" class="btn btn-primary btn-full">
                                    <i class="fas fa-paper-plane"></i>
                                    Send Reset Link
                                </button>
                            </form>
                            
                            <div class="auth-footer">
                                <p>Remember your password? <a href="#signin" data-action="navigate" data-page="signin">Sign in here</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="reset-password" class="page">
            <section class="auth-section">
                <div class="container">
                    <div class="auth-content">
                        <div class="auth-mascot">
                            <div class="cat-mascot">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble">
                                <span class="speech-text">Create a new password!</span>
                            </div>
                        </div>
                        
                        <div class="auth-form">
                            <h1>Reset Password</h1>
                            <p>Enter your new password below.</p>
                            
                            <form id="reset-password-form">
                                <input type="hidden" name="token" id="reset-token">
                                <div class="form-group">
                                    <label>New Password</label>
                                    <div class="password-input">
                                        <input type="password" name="newPassword" placeholder="Enter new password" required>
                                        <button class="password-toggle" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="strength-bar">
                                            <div class="strength-fill"></div>
                                        </div>
                                        <span class="strength-text">Password strength: Weak</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <div class="password-input">
                                        <input type="password" name="confirmPassword" placeholder="Confirm new password" required>
                                        <button class="password-toggle" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-full">
                                    <i class="fas fa-key"></i>
                                    Reset Password
                                </button>
                            </form>
                            
                            <div class="auth-footer">
                                <p>Back to <a href="#signin" data-action="navigate" data-page="signin">Sign in</a></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="admin" class="page">
            <section class="admin-section">
                <div class="container">
                    <div class="admin-header">
                        <h1><i class="fas fa-crown"></i> Admin Panel</h1>
                        <p>Manage competitions and verify payments</p>
                    </div>

                    <div class="admin-tabs">
                        <button class="admin-tab active" data-tab="competitions">Competitions</button>
                        <button class="admin-tab" data-tab="payments">Payment Verification</button>
                        <button class="admin-tab" data-tab="registrations">All Registrations</button>
                    </div>

                    <div class="admin-content">
                        <div id="admin-competitionsTab" class="admin-tab-content active">
                            <div class="admin-card">
                                <h2>Add New Competition</h2>
                                <form id="addCompetitionForm" action="api/register_competition.php">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Competition Name *</label>
                                            <input type="text" name="name" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Category *</label>
                                            <select name="category" required>
                                                <option value="international">International</option>
                                                <option value="national">National</option>
                                                <option value="junior">Junior</option>
                                                <option value="internal">Internal</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Description</label>
                                        <textarea name="description"></textarea>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Start Date *</label>
                                            <input type="datetime-local" name="start_date" required>
                                        </div>
                                        <div class="form-group">
                                            <label>End Date *</label>
                                            <input type="datetime-local" name="end_date" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Registration Deadline *</label>
                                            <input type="datetime-local" name="registration_deadline" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Difficulty Level</label>
                                            <select name="difficulty_level">
                                                <option value="beginner">Beginner</option>
                                                <option value="intermediate">Intermediate</option>
                                                <option value="advanced">Advanced</option>
                                                <option value="expert">Expert</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Max Participants</label>
                                            <input type="number" name="max_participants" min="1">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Prize Pool</label>
                                            <input type="text" name="prize_pool" placeholder="e.g. $10,000">
                                        </div>
                                        <div class="form-group">
                                            <label>Contact Person</label>
                                            <input type="text" name="contact_person">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label>Rules</label>
                                        <textarea name="rules"></textarea>
                                    </div>

                                    <div class="form-group">
                                        <label>Banner Image *</label>
                                        <input type="file" name="banner_file" accept="image/*" required>
                                        <small class="form-text">Upload a JPG, PNG, or WebP banner (required).</small>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Add Competition</button>
                                </form>
                            </div>

                            <div class="admin-card">
                                <h2>Existing Competitions</h2>
                                <div id="adminCompetitionsList" class="competitions-grid"></div>
                            </div>
                        </div>

                        <div id="admin-paymentsTab" class="admin-tab-content">
                            <div class="admin-card">
                                <h2>Pending Payment Verifications</h2>
                                <div id="paymentAlert"></div>
                                <div class="table-container">
                                    <table id="paymentsTable">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Competition</th>
                                                <th>Team Name</th>
                                                <th>Payment Status</th>
                                                <th>Registered At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="paymentsTableBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div id="admin-registrationsTab" class="admin-tab-content">
                            <div class="admin-card">
                                <h2>All Registrations</h2>
                                <div class="table-container">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>User</th>
                                                <th>Competition</th>
                                                <th>Team Name</th>
                                                <th>Status</th>
                                                <th>Payment</th>
                                                <th>Score</th>
                                                <th>Registered At</th>
                                            </tr>
                                        </thead>
                                        <tbody id="registrationsTableBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="editCompetitionModal" class="modal">
                        <div class="modal-content" style="max-width: 600px;">
                            <div class="modal-header">
                                <h2>Edit Competition</h2>
                                <span class="modal-close" data-action="close-modal" data-modal="editCompetitionModal">&times;</span>
                            </div>
                            <form id="editCompetitionForm">
                                <input type="hidden" name="id" id="editCompId">
                                <div class="form-group">
                                    <label>Competition Name *</label>
                                    <input type="text" name="name" id="editCompName" required>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea name="description" id="editCompDescription"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Category *</label>
                                        <select name="category" id="editCompCategory" required>
                                            <option value="international">International</option>
                                            <option value="national">National</option>
                                            <option value="junior">Junior</option>
                                            <option value="internal">Internal</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Difficulty Level</label>
                                        <select name="difficulty_level" id="editCompDifficulty">
                                            <option value="beginner">Beginner</option>
                                            <option value="intermediate">Intermediate</option>
                                            <option value="advanced">Advanced</option>
                                            <option value="expert">Expert</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Start Date *</label>
                                        <input type="datetime-local" name="start_date" id="editCompStart" required>
                                    </div>
                                    <div class="form-group">
                                        <label>End Date *</label>
                                        <input type="datetime-local" name="end_date" id="editCompEnd" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Registration Deadline *</label>
                                        <input type="datetime-local" name="registration_deadline" id="editCompDeadline" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Max Participants</label>
                                        <input type="number" name="max_participants" id="editCompMax" min="1">
                                    </div>
                                    <div class="form-group">
                                        <label>Prize Pool</label>
                                        <input type="text" name="prize_pool" id="editCompPrize" placeholder="e.g. $10,000">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Contact Person</label>
                                        <input type="text" name="contact_person" id="editCompContact">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Rules</label>
                                    <textarea name="rules" id="editCompRules"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Current Banner</label>
                                    <div id="editBannerPreview" class="current-banner-preview"></div>
                                    <input type="file" name="banner_file" accept="image/*">
                                    <small class="form-text">Upload a new JPG or PNG to replace the banner.</small>
                                </div>
                                <button type="submit" class="btn btn-primary">Update Competition</button>
                            </form>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div id="help" class="page">
            <section class="page-header">
                <div class="container">
                    <div class="header-content">
                        <div class="header-mascot">
                            <div class="cat-mascot small">
                                <div class="cat-body">
                                    <div class="cat-face">
                                        <div class="cat-eyes">
                                            <div class="eye left-eye"></div>
                                            <div class="eye right-eye"></div>
                                        </div>
                                        <div class="cat-nose"></div>
                                        <div class="cat-mouth">
                                            <div class="mouth-left"></div>
                                            <div class="mouth-right"></div>
                                        </div>
                                    </div>
                                    <div class="cat-ears">
                                        <div class="ear ear-left"></div>
                                        <div class="ear ear-right"></div>
                                    </div>
                                    <div class="cat-hoodie"></div>
                                </div>
                            </div>
                            <div class="speech-bubble small">
                                <span class="speech-text">Need guidance? Start here.</span>
                            </div>
                        </div>
                        <div class="header-text">
                            <h1>Help & Documentation</h1>
                            <p>Step-by-step guidance, FAQs, and contacts so you never get stuck.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="help-section">
                <div class="container">
                    <div class="help-grid">
                        <div class="help-card">
                            <div class="card-label">Quick Start</div>
                            <h3>Join a Competition</h3>
                            <ol class="help-steps">
                                <li>Sign Up or Sign In.</li>
                                <li>Buka menu <strong>Competitions</strong> dan pilih event.</li>
                                <li>Klik <strong>Register</strong>, isi <strong>Team Name</strong>, lalu submit.</li>
                                <li>Cek status di <strong>Dashboard → My Competitions</strong>.</li>
                            </ol>
                        </div>

                        <div class="help-card">
                            <div class="card-label">Before the Event</div>
                            <h3>Rules & Timeline</h3>
                            <ul class="help-list">
                                <li>Periksa <strong>Rules</strong> pada detail kompetisi.</li>
                                <li>Pastikan jadwal <strong>Start/End</strong> dan <strong>Registration Deadline</strong>.</li>
                                <li>Hubungi <strong>Contact Person</strong> jika ada kendala.</li>
                            </ul>
                        </div>

                        <div class="help-card">
                            <div class="card-label">During the Event</div>
                            <h3>Troubleshooting</h3>
                            <ul class="help-list">
                                <li>Jika tidak bisa login, gunakan <strong>Forgot Password</strong>.</li>
                                <li>Jika banner/halaman tidak memuat, refresh & cek koneksi.</li>
                                <li>Laporkan isu teknis ke kontak resmi kompetisi.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section class="faq-section">
                <div class="container">
                    <div class="section-header">
                        <h2>FAQ & How-to</h2>
                        <p>Pertanyaan umum dengan jawaban singkat supaya cepat.</p>
                    </div>
                    <div class="faq-grid">
                        <details class="faq-item" open>
                            <summary>Bagaimana cara mendaftar kompetisi?</summary>
                            <p>Buka <strong>Competitions</strong> → pilih event → klik <strong>Register</strong> → isi nama tim → submit. Status pendaftaran muncul di <strong>Dashboard → My Competitions</strong>.</p>
                        </details>
                        <details class="faq-item">
                            <summary>Apa arti status “registration_open”, “ongoing”, “completed”?</summary>
                            <p><strong>registration_open</strong>: pendaftaran masih dibuka. <strong>ongoing</strong>: kompetisi sedang berjalan. <strong>completed</strong>: event selesai; hasil biasanya diumumkan oleh panitia.</p>
                        </details>
                        <details class="faq-item">
                            <summary>Lupa password atau akun tidak bisa login?</summary>
                            <p>Gunakan <strong>Forgot password?</strong> di halaman Sign In. Masukkan email; jika terdaftar, tautan reset dikirim. Jika tidak menerima email, cek folder spam atau hubungi kontak resmi.</p>
                        </details>
                        <details class="faq-item">
                            <summary>Bagaimana mengubah profil atau avatar?</summary>
                            <p>Buka <strong>Profile</strong> → klik <strong>Edit</strong> untuk mengubah nama, lokasi, bio. Klik <strong>Change Avatar</strong> untuk unggah foto. Jangan lupa <strong>Save Changes</strong>.</p>
                        </details>
                        <details class="faq-item">
                            <summary>Siapa yang dihubungi untuk masalah pembayaran/konfirmasi?</summary>
                            <p>Lihat <strong>Contact</strong> di kartu kompetisi. Jika masih perlu bantuan, kirim email ke <a href="mailto:support@konekoctf.com">support@konekoctf.com</a>.</p>
                        </details>
                        <details class="faq-item">
                            <summary>Apakah ada panduan dasar CTF?</summary>
                            <p>Mulai dari kategori mudah/beginner, cek write-up publik, dan pastikan memahami aturan: dilarang menyerang infrastruktur, jangan berbagi flag, hormati peserta lain.</p>
                        </details>
                    </div>
                </div>
            </section>

            <section class="contact-section">
                <div class="container">
                    <div class="contact-card">
                        <div>
                            <div class="card-label">Still need help?</div>
                            <h3>Kontak Support</h3>
                            <p>Kirim pertanyaan teknis atau administratif ke alamat berikut:</p>
                            <ul class="contact-list">
                                <li>
                                    <a href="mailto:todidiang@gmail.com" aria-label="Email support" title="Email support">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://discord.gg/A6vq2N3VW" target="_blank" rel="noopener" aria-label="Join Discord" title="Join Discord">
                                        <i class="fa-brands fa-discord"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div class="contact-cta">
                            <button class="btn btn-outline" data-action="navigate" data-page="competitions">
                                <i class="fas fa-compass"></i>
                                Browse Competitions
                            </button>
                            <button class="btn btn-primary" data-action="navigate" data-page="signup">
                                <i class="fas fa-user-plus"></i>
                                Create Account
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <div class="background-effects">
        <div class="floating-icons">
            <div class="floating-icon">🔒</div>
            <div class="floating-icon">🛡️</div>
            <div class="floating-icon">💻</div>
            <div class="floating-icon">🔑</div>
            <div class="floating-icon">⚡</div>
            <div class="floating-icon">🎯</div>
            <div class="floating-icon">🚀</div>
            <div class="floating-icon">💎</div>
        </div>
        <canvas id="matrix-canvas"></canvas>
    </div>

    <div id="registrationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Competition Registration</h2>
                <span class="modal-close" id="closeRegistrationModal">&times;</span>
            </div>
            <form id="registrationForm">
                <input type="hidden" id="regCompetitionId" name="competition_id">
                <div id="registrationError" class="form-alert error"></div>
                <div class="form-group">
                    <label for="regTeamName">Team Name</label>
                    <input type="text" id="regTeamName" name="team_name" placeholder="Enter your team name" required>
                </div>
                <div class="form-group">
                    <label for="regNotes">Registration Notes</label>
                    <textarea id="regNotes" name="registration_notes" placeholder="Any special requirements or notes (optional)"></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-full">Register Now</button>
            </form>
        </div>
    </div>

    <script src="assets/js/script.js?v=2.1"></script>
    <script nonce="<?= htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') ?>">
        // Failsafe: Ensure loader is removed after 5 seconds even if script.js fails
        setTimeout(function() {
            var loader = document.getElementById('page-loader');
            if (loader && !loader.classList.contains('hidden')) {
                console.warn('Loader stuck detected. Forcing removal.');
                loader.style.opacity = '0';
                setTimeout(function() { loader.remove(); }, 200);
            }
        }, 5000);
    </script>
</body>
</html>
