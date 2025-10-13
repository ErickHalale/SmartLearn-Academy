<?php
include 'includes/config.php';

// Basic gate: ensure only logged-in admins can access (adjust as needed)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

// Get pupil counts
$primary_pupil_count = 0;
$secondary_pupil_count = 0;

try {
    $primary_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM primary_users");
    $primary_count_stmt->execute();
    $primary_pupil_count = $primary_count_stmt->fetchColumn();

    $secondary_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM secondary_users");
    $secondary_count_stmt->execute();
    $secondary_pupil_count = $secondary_count_stmt->fetchColumn();
} catch (PDOException $e) {
    // Continue without counts
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="60" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            z-index: -1;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            color: #2c3e50;
            padding: 30px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .header-content h1 {
            font-size: 28px;
            margin-bottom: 5px;
            font-weight: 700;
            color: #2c3e50;
        }

        .header-content div {
            font-size: 16px;
            opacity: 0.8;
            color: #667eea;
        }
        
        .nav-links { 
            display: flex; 
            gap: 15px; 
        }
        
        .nav-links a {
            color: #667eea;
            text-decoration: none;
            background: rgba(102, 126, 234, 0.1);
            padding: 12px 18px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .nav-links a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        a.btn.logout {
            background: rgba(231, 76, 60, 0.8);
            color: white;
        }

        a.btn.logout:hover {
            background: #e74c3c;
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }
        
        .main-container {
            display: flex;
            flex: 1;
        }
        
        .sidebar {
            width: 320px;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 0 25px 25px 0;
        }
        
        .sidebar.primary {
            border-right: 1px solid #e0e0e0;
        }
        
        .sidebar.secondary {
            border-left: 1px solid #e0e0e0;
            margin-left: auto;
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 30px 25px;
            text-align: center;
            border-radius: 0 25px 0 0;
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);
        }

        .sidebar-header h3 {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .sidebar.primary .sidebar-header h3:before {
            content: "🏫";
            font-size: 18px;
        }
        
        .sidebar.secondary .sidebar-header h3:before {
            content: "🎓";
            font-size: 18px;
        }
        
        .sidebar-content {
            flex: 1;
            padding: 25px;
            background: rgba(248, 249, 250, 0.8);
        }

        .menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu li {
            margin-bottom: 12px;
        }

        .menu a {
            display: block;
            padding: 18px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border-left: 5px solid transparent;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            margin-bottom: 8px;
        }
        
        .sidebar.primary .menu a {
            border-left-color: #e74c3c;
        }
        
        .sidebar.secondary .menu a {
            border-left-color: #3498db;
        }
        
        .menu a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .sidebar.primary .menu a:hover {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .sidebar.secondary .menu a:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .center-space {
            flex: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            border-radius: 20px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logo-section {
            text-align: center;
            color: #2c3e50;
        }

        .logo-section h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-section p {
            font-size: 18px;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .logo-icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        
        .footer {
            background: rgba(44, 62, 80, 0.98);
            backdrop-filter: blur(15px);
            color: #fff;
            padding: 60px 40px 30px;
            box-shadow: 0 -15px 40px rgba(0,0,0,0.15);
            border-top: 2px solid rgba(102, 126, 234, 0.1);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-main {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-section {
            padding: 0 15px;
        }

        .footer-logo h3 {
            font-size: 24px;
            margin-bottom: 10px;
            font-weight: 700;
            color: #fff;
        }

        .footer-logo p {
            opacity: 0.8;
            font-size: 14px;
            line-height: 1.4;
        }

        .footer-section h4 {
            color: #fff;
            font-size: 18px;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 8px;
        }

        .footer-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-nav li {
            margin-bottom: 8px;
        }

        .footer-nav a {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .footer-nav a:hover {
            color: #3498db;
            transform: translateX(5px);
        }

        .contact-info p {
            margin-bottom: 8px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .contact-info i {
            color: #3498db;
            width: 16px;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            flex-wrap: wrap;
            gap: 15px;
        }

        .footer-copyright {
            font-size: 14px;
            opacity: 0.8;
            margin: 0;
        }

        .footer-social {
            display: flex;
            gap: 15px;
        }

        .social-link {
            color: #ecf0f1;
            text-decoration: none;
            font-size: 18px;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }

        .social-link:hover {
            background: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        @media (max-width: 1024px) {
            .main-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                border-radius: 0;
            }

            .sidebar.secondary {
                margin-left: 0;
                border-left: none;
                border-top: 2px solid rgba(102, 126, 234, 0.1);
            }

            .center-space {
                display: none;
            }

            .sidebar-header {
                border-radius: 0;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }
        }
        #theme-toggle {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            border: 1px solid rgba(102, 126, 234, 0.2);
            padding: 12px 18px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 16px;
        }
        #theme-toggle:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .dark-mode {
            --bg-gradient: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            --header-bg: rgba(30, 30, 30, 0.98);
            --sidebar-bg: rgba(30, 30, 30, 0.98);
            --center-bg: rgba(30, 30, 30, 0.95);
            --footer-bg: rgba(20, 20, 20, 0.98);
            --text-color: #e0e0e0;
            --accent: #667eea;
        }
        .dark-mode body {
            background: var(--bg-gradient);
            color: var(--text-color);
        }
        .dark-mode .header {
            background: var(--header-bg);
            color: var(--text-color);
        }
        .dark-mode .sidebar {
            background: var(--sidebar-bg);
        }
        .dark-mode .center-space {
            background: var(--center-bg);
        }
        .dark-mode .footer {
            background: var(--footer-bg);
        }
        .dark-mode .menu a {
            background: rgba(40, 40, 40, 0.95);
            color: var(--text-color);
        }
        .dark-mode .menu a:hover {
            background: var(--accent);
        }
        .dark-mode .logo-section h2 {
            color: var(--text-color);
        }
        .dark-mode .footer-nav a {
            color: var(--text-color);
        }
        .dark-mode .footer-nav a:hover {
            color: var(--accent);
        }
        .dark-mode .contact-info p {
            color: var(--text-color);
        }
        .dark-mode .header-content h1 {
            color: var(--text-color);
        }
        .dark-mode .header-content div {
            color: var(--accent);
        }
        .dark-mode .sidebar-content {
            background: rgba(30, 30, 30, 0.8);
        }
        .dark-mode .logo-section p {
            color: var(--text-color);
        }
        .dark-mode .footer-logo p {
            color: var(--text-color);
        }
        .dark-mode .footer-copyright {
            color: var(--text-color);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Admin Dashboard</h1>
            <div>Welcome, <?php echo htmlspecialchars($_SESSION['admin_email'] ?? 'Admin'); ?></div>
        </div>
        <div class="nav-links">
              <a href="manage_facts_secondary.php">Manage Facts For Secondary</a>
              <a href="manage_facts_primary.php">Manage Facts For primary</a>
             <a href="add_admin.php">Add Admin</a>
             <a href="manage_admin.php">Manage Admin</a>
             <button id="theme-toggle" class="btn">🌙</button>
             <a class="btn logout" href="logout.php">Log out</a>
        </div>
    </div>

    <div class="main-container">
        <aside class="sidebar primary">
            <div class="sidebar-header">
                <h3>Primary Level</h3>
            </div>
            <div class="sidebar-content">
                <ul class="menu">
                    <li><a href="add_grade.php">Add Grade</a></li>
                    <li><a href="manage_grade.php">Manage Grade</a></li>
                    <li><a href="create_subject.php">Add Subject</a></li>
                    <li><a href="manage_subject_primary.php">Manage Subject</a></li>
                    <li><a href="view_pupils_primary.php">View Pupils</a></li>
                </ul>
            </div>
        </aside>
        
        <div class="center-space">
            <div class="logo-section">
                <div class="logo-icon">🎓</div>
                <h2>SmartLearn Academy</h2>
                <p>Admin Control Center</p>
                <p>Manage Primary & Secondary Education</p>
            </div>
        </div>
        
        <aside class="sidebar secondary">
            <div class="sidebar-header">
                <h3>Secondary Level</h3>
            </div>
            <div class="sidebar-content">
                <ul class="menu">
                    <li><a href="add_form.php">Add Form</a></li>
                    <li><a href="manage_form.php">Manage Form</a></li>
                    <li><a href="add_subject_secondary.php">Add Subject</a></li>
                    <li><a href="manage_subject_secondary.php">Manage Subject</a></li>
                    <li><a href="view_pupils_secondary.php">View Pupils</a></li>
                </ul>
            </div>
        </aside>
    </div>

    <footer class="footer">
        <div class="footer-content">
            <div class="footer-main">
                <div class="footer-section">
                    <div class="footer-logo">
                        <h3>SmartLearn Academy</h3>
                        <p>Empowering Education Through Technology</p>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul class="footer-nav">
                        <li><a href="admin.php">Dashboard</a></li>
                        <li><a href="manage_subject_primary.php">Primary Subjects</a></li>
                        <li><a href="manage_subject_secondary.php">Secondary Subjects</a></li>
                        <li><a href="view_pupils_primary.php">Primary Pupils</a></li>
                        <li><a href="view_pupils_secondary.php">Secondary Pupils</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul class="footer-nav">
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> support@smartlearn.academy</p>
                        <p><i class="fas fa-phone"></i> +260-976-01962</p>
                        <p><i class="fas fa-map-marker-alt"></i> Lusaka, Zambia</p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-copyright">
                    <p>&copy; 2025 SmartLearn Academy. All rights reserved.</p>
                </div>
                <div class="footer-social">
                    <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </footer>
    <script>
        document.getElementById('theme-toggle').addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            this.textContent = isDark ? '☀️' : '🌙';
        });
    </script>
    </body>
    </html>