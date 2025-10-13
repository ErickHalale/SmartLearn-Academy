<?php
include 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - SmartLearn</title>
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

        .contact-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .contact-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
            text-align: center;
        }

        .contact-icon {
            font-size: 36px;
            color: #3498db;
            margin-bottom: 15px;
        }

        .contact-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .contact-card p {
            color: #555;
            margin-bottom: 5px;
        }

        .contact-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .btn {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .office-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-top: 30px;
            text-align: center;
        }

        .office-info h3 {
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

            .contact-grid {
                grid-template-columns: 1fr;
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
            <h1>Contact Information</h1>
            <p>We'd love to hear from you! Whether you have questions about our platform, need technical support, or want to provide feedback, our team is here to help.</p>

            <div class="contact-grid">
                <div class="contact-card">
                    <div class="contact-icon">📧</div>
                    <h3>Email Support</h3>
                    <p>support@smartlearn.academy</p>
                    <p>Response time: 24-48 hours</p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">📱</div>
                    <h3>Phone Support</h3>
                    <p>+260-211-123456</p>
                    <p>Mon-Fri: 8:00 AM - 6:00 PM GMT</p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">📍</div>
                    <h3>Office Address</h3>
                    <p>123 Education Street</p>
                    <p>Lusaka, Zambia</p>
                </div>
                <div class="contact-card">
                    <div class="contact-icon">🕒</div>
                    <h3>Business Hours</h3>
                    <p>Monday - Friday</p>
                    <p>8:00 AM - 6:00 PM GMT</p>
                </div>
            </div>

            <div class="contact-form">
                <h2>Send us a Message</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" placeholder="Please describe your inquiry..." required></textarea>
                    </div>
                    <button type="submit" class="btn">Send Message</button>
                </form>
            </div>
        </div>

        <div class="office-info">
            <h3>Visit Our Office</h3>
            <p>Located in the heart of Lusaka's educational district, our office welcomes visitors by appointment. We offer guided tours of our facilities and demonstrations of our latest educational technologies.</p>
            <p><strong>Appointment Required:</strong> Please contact us via email or phone to schedule a visit.</p>
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