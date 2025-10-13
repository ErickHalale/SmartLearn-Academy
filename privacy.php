<?php
include 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - SmartLearn</title>
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
            color: #333;
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
            max-width: 900px;
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
            border-bottom: 2px solid #3498db;
            padding-bottom: 15px;
        }

        .last-updated {
            text-align: center;
            color: #666;
            font-style: italic;
            margin-bottom: 30px;
        }

        .content-card h2 {
            color: #3498db;
            margin: 30px 0 15px 0;
            font-size: 24px;
        }

        .content-card h3 {
            color: #2c3e50;
            margin: 25px 0 10px 0;
            font-size: 18px;
        }

        .content-card p {
            margin-bottom: 15px;
            color: #555;
            font-size: 16px;
        }

        .content-card ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }

        .content-card li {
            margin-bottom: 8px;
            color: #555;
        }

        .highlight-box {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .contact-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }

        .contact-section h3 {
            margin-bottom: 15px;
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
            <h1>Privacy Policy</h1>
            <p class="last-updated">Last updated: October 6, 2024</p>

            <p>This Privacy Policy describes how SmartLearn Academy ("we," "us," or "our") collects, uses, and protects your information when you use our educational platform.</p>

            <h2>Information We Collect</h2>

            <h3>Personal Information</h3>
            <p>We collect personal information that you provide directly to us, including:</p>
            <ul>
                <li>Name and contact information</li>
                <li>Educational background and level</li>
                <li>School and location information</li>
                <li>Account credentials</li>
            </ul>

            <h3>Usage Information</h3>
            <p>We automatically collect certain information about your use of our platform:</p>
            <ul>
                <li>Learning progress and quiz results</li>
                <li>Time spent on different subjects and topics</li>
                <li>Device and browser information</li>
                <li>IP address and location data</li>
            </ul>

            <h2>How We Use Your Information</h2>
            <p>We use the collected information to:</p>
            <ul>
                <li>Provide and personalize your learning experience</li>
                <li>Track and report on educational progress</li>
                <li>Improve our platform and educational content</li>
                <li>Communicate with you about your account and our services</li>
                <li>Ensure platform security and prevent fraud</li>
                <li>Comply with legal obligations</li>
            </ul>

            <h2>Information Sharing and Disclosure</h2>
            <p>We do not sell, trade, or rent your personal information to third parties. We may share your information only in the following circumstances:</p>
            <ul>
                <li>With your explicit consent</li>
                <li>To comply with legal requirements</li>
                <li>To protect our rights and prevent fraud</li>
                <li>In connection with a business transfer</li>
            </ul>

            <h2>Data Security</h2>
            <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p>
            <ul>
                <li>Encryption of sensitive data</li>
                <li>Secure server infrastructure</li>
                <li>Regular security audits</li>
                <li>Access controls and authentication</li>
            </ul>

            <h2>Your Rights</h2>
            <p>You have the following rights regarding your personal information:</p>
            <ul>
                <li><strong>Access:</strong> Request a copy of your personal data</li>
                <li><strong>Correction:</strong> Request correction of inaccurate data</li>
                <li><strong>Deletion:</strong> Request deletion of your personal data</li>
                <li><strong>Portability:</strong> Request transfer of your data</li>
                <li><strong>Objection:</strong> Object to processing of your data</li>
            </ul>

            <h2>Cookies and Tracking</h2>
            <p>We use cookies and similar technologies to enhance your experience on our platform. You can control cookie settings through your browser preferences.</p>

            <h2>Children's Privacy</h2>
            <p>Our platform serves both primary and secondary students. We are committed to protecting children's privacy and comply with applicable children's privacy laws. Parental consent may be required for certain features.</p>

            <h2>International Data Transfers</h2>
            <p>Your information may be transferred to and processed in countries other than your own. We ensure appropriate safeguards are in place for such transfers.</p>

            <h2>Data Retention</h2>
            <p>We retain your personal information for as long as necessary to provide our services and comply with legal obligations. You can request deletion of your account at any time.</p>

            <h2>Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time. We will notify you of any material changes by posting the new policy on this page and updating the "Last updated" date.</p>

            <div class="highlight-box">
                <h3>📞 Contact Us About Privacy</h3>
                <p>If you have any questions about this Privacy Policy or our data practices, please contact our Data Protection Officer at privacy@smartlearn.academy.</p>
            </div>
        </div>

        <div class="contact-section">
            <h3>Questions About Your Privacy?</h3>
            <p>If you have concerns about how we handle your data or wish to exercise your privacy rights, please don't hesitate to contact us. We're here to help and ensure your trust in our platform.</p>
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