<?php
include 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - SmartLearn</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #3498db 0%, #3498db 100%);
            color: #fff;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: white;
        }

        .header-content h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .content-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }

        .content-card h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 32px;
            text-align: center;
        }

        .content-card h2 {
            color: #3498db;
            margin: 30px 0 15px 0;
            font-size: 24px;
        }

        .content-card p {
            margin-bottom: 15px;
            color: #555;
            font-size: 16px;
        }

        .mission-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .mission-icon {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 20px;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .feature-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            transition: transform 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 36px;
            color: #3498db;
            margin-bottom: 15px;
        }

        .feature-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .stats-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            margin-top: 40px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            opacity: 0.9;
            font-size: 14px;
        }

        .footer {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: #fff;
            padding: 40px 24px 20px;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
            margin-top: 40px;
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
            background: #3498db;
            transform: translateY(-2px);
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

            .container {
                padding: 0 15px;
            }

            .content-card {
                padding: 20px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-links {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php" class="logo">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <div class="logo-text">SmartLearn</div>
        </a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="login.php">Login</a>
            <a href="registration.php">Register</a>
        </div>
    </div>

    <div class="container">
        <div class="content-card">
            <div class="mission-section">
                <div class="mission-icon">🎓</div>
                <h1>About SmartLearn Academy</h1>
                <p>SmartLearn Academy is a cutting-edge educational platform designed to revolutionize the way students learn and teachers teach. We combine innovative technology with proven educational methodologies to create an engaging, personalized learning experience.</p>
            </div>

            <h2>Our Mission</h2>
            <p>Our mission is to democratize quality education by providing accessible, affordable, and effective learning tools to students worldwide. We believe that every student deserves the opportunity to reach their full potential, regardless of their location, background, or circumstances.</p>

            <h2>Our Vision</h2>
            <p>To become the world's leading educational technology platform, fostering a global community of lifelong learners and empowering educators with the tools they need to inspire the next generation.</p>

            <h2>What Sets Us Apart</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🤖</div>
                    <h3>AI-Powered Learning</h3>
                    <p>Our intelligent algorithms adapt to each student's learning style and pace, providing personalized recommendations and feedback.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Comprehensive Analytics</h3>
                    <p>Detailed progress tracking and analytics help students and teachers monitor performance and identify areas for improvement.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🌐</div>
                    <h3>Global Accessibility</h3>
                    <p>Access our platform from anywhere in the world with an internet connection, breaking down geographical barriers to education.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Collaborative Learning</h3>
                    <p>Interactive features encourage peer-to-peer learning and collaboration, fostering a supportive learning community.</p>
                </div>
            </div>

            <h2>Our Impact</h2>
            <p>Since our inception, SmartLearn Academy has helped thousands of students achieve their academic goals. Our platform supports both primary and secondary education levels, offering comprehensive coverage of core subjects and specialized topics.</p>

            <p>We work closely with educational institutions, teachers, and parents to ensure our platform aligns with curriculum standards and supports the holistic development of students.</p>
        </div>

        <div class="stats-section">
            <h2>By the Numbers</h2>
            <div class="stats-grid">
                <div>
                    <div class="stat-number">10,000+</div>
                    <div class="stat-label">Students Enrolled</div>
                </div>
                <div>
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Subjects Covered</div>
                </div>
                <div>
                    <div class="stat-number">1,000+</div>
                    <div class="stat-label">Practice Questions</div>
                </div>
                <div>
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Student Satisfaction</div>
                </div>
            </div>
        </div>
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
                        <li><a href="index.php">Home</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="registration.php">Register</a></li>
                        <li><a href="about.php">About Us</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul class="footer-nav">
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <div class="contact-info">
                        <p><i class="fas fa-envelope"></i> support@smartlearn.academy</p>
                        <p><i class="fas fa-phone"></i> +260-211-123456</p>
                        <p><i class="fas fa-map-marker-alt"></i> Lusaka, Zambia</p>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-copyright">
                    <p>&copy; 2024 SmartLearn Academy. All rights reserved.</p>
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
</body>
</html>