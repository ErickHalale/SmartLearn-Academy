<?php
include 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - SmartLearn</title>
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

        .important-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
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
            <h1>Terms of Service</h1>
            <p class="last-updated">Last updated: October 6, 2024</p>

            <p>Welcome to SmartLearn Academy. These Terms of Service ("Terms") govern your use of our educational platform and services. By accessing or using our platform, you agree to be bound by these Terms.</p>

            <h2>Acceptance of Terms</h2>
            <p>By creating an account or using SmartLearn Academy, you acknowledge that you have read, understood, and agree to be bound by these Terms and our Privacy Policy. If you do not agree to these Terms, please do not use our platform.</p>

            <h2>Description of Service</h2>
            <p>SmartLearn Academy provides an online educational platform offering:</p>
            <ul>
                <li>Interactive learning modules and quizzes</li>
                <li>Progress tracking and analytics</li>
                <li>Educational content for primary and secondary students</li>
                <li>Teacher and administrator management tools</li>
            </ul>

            <h2>User Accounts</h2>

            <h3>Account Creation</h3>
            <p>To use certain features of our platform, you must create an account. You agree to:</p>
            <ul>
                <li>Provide accurate and complete information</li>
                <li>Maintain the confidentiality of your account credentials</li>
                <li>Be responsible for all activities under your account</li>
                <li>Notify us immediately of any unauthorized use</li>
            </ul>

            <h3>Eligibility</h3>
            <p>Our platform serves educational institutions and their students. By using our service, you represent that you are:</p>
            <ul>
                <li>A student enrolled in an educational institution</li>
                <li>An educator or administrator authorized to use the platform</li>
                <li>At least 13 years old (or the minimum age in your jurisdiction)</li>
            </ul>

            <h2>Acceptable Use Policy</h2>
            <p>You agree to use our platform only for lawful educational purposes. Prohibited activities include:</p>
            <ul>
                <li>Sharing account credentials with others</li>
                <li>Attempting to gain unauthorized access</li>
                <li>Uploading malicious content or code</li>
                <li>Harassing other users or staff</li>
                <li>Violating intellectual property rights</li>
                <li>Using the platform for commercial purposes without permission</li>
            </ul>

            <h2>Content and Intellectual Property</h2>

            <h3>Our Content</h3>
            <p>All content on SmartLearn Academy, including text, graphics, logos, and software, is owned by us or our licensors and is protected by copyright and other intellectual property laws.</p>

            <h3>User Content</h3>
            <p>By uploading content to our platform, you grant us a license to use, display, and distribute that content in connection with our services. You retain ownership of your content but agree that it does not violate any third-party rights.</p>

            <h2>Privacy and Data Protection</h2>
            <p>Your privacy is important to us. Please review our Privacy Policy, which explains how we collect, use, and protect your information. By using our platform, you consent to our data practices as described in the Privacy Policy.</p>

            <h2>Payment and Billing</h2>
            <p>Some features of our platform may require payment. All fees are clearly disclosed before purchase. Payments are processed securely, and you agree to pay all charges associated with your account.</p>

            <h2>Termination</h2>
            <p>We reserve the right to suspend or terminate your account at any time for violations of these Terms. You may also terminate your account at any time. Upon termination, your right to use the platform ceases immediately.</p>

            <h2>Disclaimers and Limitations</h2>

            <div class="important-notice">
                <h3>⚠️ Important Disclaimers</h3>
                <p>Our platform is provided "as is" without warranties of any kind. We do not guarantee that the platform will be error-free or uninterrupted. Educational content is for informational purposes and should not replace professional teaching or assessment.</p>
            </div>

            <p>We are not liable for any indirect, incidental, or consequential damages arising from your use of the platform. Our total liability shall not exceed the amount paid by you for the service.</p>

            <h2>Indemnification</h2>
            <p>You agree to indemnify and hold us harmless from any claims, damages, or expenses arising from your use of the platform or violation of these Terms.</p>

            <h2>Governing Law</h2>
            <p>These Terms are governed by the laws of Zambia. Any disputes shall be resolved in the courts of Lusaka, Zambia.</p>

            <h2>Changes to Terms</h2>
            <p>We may update these Terms from time to time. We will notify users of material changes via email or platform notifications. Continued use of the platform after changes constitutes acceptance of the new Terms.</p>

            <h2>Contact Information</h2>
            <p>If you have questions about these Terms, please contact us at legal@smartlearn.academy.</p>

            <div class="highlight-box">
                <h3>📞 Need Help?</h3>
                <p>If you have questions about these Terms of Service or need clarification on any point, please don't hesitate to contact our support team. We're here to help you understand and comply with our guidelines.</p>
            </div>
        </div>

        <div class="contact-section">
            <h3>Questions About Our Terms?</h3>
            <p>Our legal team is available to answer any questions you may have about these Terms of Service. We want to ensure you have a clear understanding of your rights and responsibilities when using SmartLearn Academy.</p>
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