<?php
require_once 'config.php';

// Redirect to dashboard if logged in
if (Auth::isLoggedIn()) {
    header('Location: modules/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
        }
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Navbar */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            padding: 15px 0;
        }
        .navbar-custom .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: #667eea !important;
        }
        .navbar-custom .navbar-brand i {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .navbar-custom .nav-link {
            font-weight: 500;
            color: #555 !important;
            padding: 8px 20px;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .navbar-custom .nav-link:hover {
            background: var(--primary-gradient);
            color: white !important;
            transform: translateY(-2px);
        }
        .navbar-custom .nav-link.btn-primary-custom {
            background: var(--primary-gradient);
            color: white !important;
        }
        .navbar-custom .nav-link.btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Hero Section */
        .hero-section {
            padding: 100px 0 80px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(102, 126, 234, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(118, 75, 162, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 20px;
        }
        .hero-title .highlight {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-subtitle {
            font-size: 1.2rem;
            color: #666;
            max-width: 500px;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .hero-image {
            position: relative;
            z-index: 1;
        }
        .hero-image img {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            max-width: 100%;
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,0.9);
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            color: #667eea;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .hero-badge i {
            margin-right: 8px;
        }
        .btn-hero-primary {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 5px 25px rgba(102, 126, 234, 0.4);
        }
        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 35px rgba(102, 126, 234, 0.5);
            color: white;
        }
        .btn-hero-secondary {
            background: transparent;
            color: #555;
            border: 2px solid #ddd;
            padding: 14px 40px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-hero-secondary:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-3px);
        }
        
        /* Stats Section */
        .stats-section {
            padding: 60px 0;
            background: white;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label {
            color: #888;
            font-weight: 500;
            margin-top: 5px;
        }
        
        /* Features Section */
        .features-section {
            padding: 80px 0;
            background: #f8f9fa;
        }
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
        }
        .section-subtitle {
            text-align: center;
            color: #888;
            margin-bottom: 50px;
            font-size: 1.1rem;
        }
        .feature-card {
            background: white;
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: all 0.3s;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .feature-card:hover::before {
            opacity: 1;
        }
        .feature-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
            color: white;
        }
        .feature-icon.blue { background: var(--primary-gradient); }
        .feature-icon.green { background: var(--success-gradient); }
        .feature-icon.pink { background: var(--secondary-gradient); }
        .feature-icon.orange { background: var(--warning-gradient); }
        .feature-card h5 {
            font-weight: 700;
            margin-bottom: 15px;
        }
        .feature-card p {
            color: #888;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        /* Testimonials */
        .testimonials-section {
            padding: 80px 0;
            background: white;
        }
        .testimonial-card {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s;
        }
        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        }
        .testimonial-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            color: white;
        }
        .testimonial-text {
            font-style: italic;
            color: #555;
            line-height: 1.8;
        }
        .testimonial-name {
            font-weight: 600;
            margin-top: 15px;
            color: #333;
        }
        .testimonial-role {
            color: #888;
            font-size: 14px;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            background: var(--primary-gradient);
            color: white;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 500px;
            height: 500px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .cta-section h2 {
            font-weight: 700;
            font-size: 2.5rem;
        }
        .cta-section p {
            opacity: 0.9;
            max-width: 600px;
            margin: 20px auto 30px;
            font-size: 1.1rem;
        }
        .btn-cta {
            background: white;
            color: #667eea;
            padding: 14px 50px;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }
        .btn-cta:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            color: #667eea;
        }
        
        /* Footer */
        .footer {
            background: #1a1a2e;
            color: rgba(255,255,255,0.7);
            padding: 60px 0 30px;
        }
        .footer h5 {
            color: white;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            display: block;
            margin-bottom: 10px;
        }
        .footer a:hover {
            color: white;
            transform: translateX(5px);
        }
        .footer .social-links a {
            display: inline-block;
            margin-right: 15px;
            font-size: 20px;
        }
        .footer-divider {
            border-color: rgba(255,255,255,0.1);
            margin: 30px 0;
        }
        .footer-bottom {
            text-align: center;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .hero-section {
                padding: 60px 0;
            }
            .hero-image {
                margin-top: 40px;
            }
            .stat-number {
                font-size: 2rem;
            }
            .section-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonials">Testimonials</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>modules/auth/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn-primary-custom" href="<?php echo SITE_URL; ?>modules/auth/register.php">
                            <i class="fas fa-user-plus"></i> Get Started
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section" style="margin-top: 80px;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-badge">
                        <i class="fas fa-rocket"></i> Next-Gen Learning Platform
                    </div>
                    <h1 class="hero-title">
                        Transform Your<br>
                        <span class="highlight">Learning Experience</span>
                    </h1>
                    <p class="hero-subtitle">
                        Upload your syllabus, generate AI-powered quizzes, and access personalized learning resources all in one place.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo SITE_URL; ?>modules/auth/register.php" class="btn btn-hero-primary">
                            <i class="fas fa-rocket"></i> Get Started Free
                        </a>
                        <a href="#features" class="btn btn-hero-secondary">
                            <i class="fas fa-play-circle"></i> Learn More
                        </a>
                    </div>
                    <div class="mt-4">
                        <span class="text-muted me-3"><i class="fas fa-check-circle text-success"></i> No credit card required</span>
                        <span class="text-muted"><i class="fas fa-check-circle text-success"></i> Free forever</span>
                    </div>
                </div>
                <div class="col-lg-6 hero-image">
                    <img src="https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=600&h=400&fit=crop&crop=center" alt="Learning" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6 stat-item">
                    <div class="stat-number">10K+</div>
                    <div class="stat-label">Active Students</div>
                </div>
                <div class="col-md-3 col-6 stat-item">
                    <div class="stat-number">500+</div>
                    <div class="stat-label">Courses</div>
                </div>
                <div class="col-md-3 col-6 stat-item">
                    <div class="stat-number">200+</div>
                    <div class="stat-label">Expert Teachers</div>
                </div>
                <div class="col-md-3 col-6 stat-item">
                    <div class="stat-number">95%</div>
                    <div class="stat-label">Satisfaction Rate</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <h2 class="section-title">Everything You Need to Succeed</h2>
            <p class="section-subtitle">Powerful features designed to enhance your learning journey</p>
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon blue">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <h5>Syllabus Upload</h5>
                        <p>Upload your course syllabus in PDF format and let our AI extract key concepts</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon green">
                            <i class="fas fa-brain"></i>
                        </div>
                        <h5>AI Quiz Generator</h5>
                        <p>Automatically generate MCQ and short answer questions from your syllabus</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon pink">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h5>Smart Resources</h5>
                        <p>Get personalized video and reading recommendations based on your syllabus</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon orange">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h5>Certificate & Progress</h5>
                        <p>Track your progress and earn certificates upon course completion</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section" id="testimonials">
        <div class="container">
            <h2 class="section-title">What Our Users Say</h2>
            <p class="section-subtitle">Real experiences from students and teachers</p>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <p class="testimonial-text">
                            "This platform completely transformed how I study. The AI-generated quizzes are incredibly helpful!"
                        </p>
                        <div class="testimonial-name">Sarah Johnson</div>
                        <div class="testimonial-role">Student, CS101</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <p class="testimonial-text">
                            "As a teacher, I love how easy it is to share resources and track student progress in real-time."
                        </p>
                        <div class="testimonial-name">Prof. Mike Brown</div>
                        <div class="testimonial-role">Mathematics Department</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="testimonial-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <p class="testimonial-text">
                            "The resource suggestions feature saved me hours of research. Everything I need is in one place!"
                        </p>
                        <div class="testimonial-name">Emily Davis</div>
                        <div class="testimonial-role">Student, Physics 101</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2>Ready to Start Learning?</h2>
            <p>Join thousands of students and teachers already using our platform to enhance their learning experience.</p>
            <a href="<?php echo SITE_URL; ?>modules/auth/register.php" class="btn btn-cta">
                <i class="fas fa-rocket"></i> Get Started Now
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?></h5>
                    <p style="color: rgba(255,255,255,0.6);">Empowering learners with AI-powered education tools and personalized learning experiences.</p>
                    <div class="social-links mt-3">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <h5>Quick Links</h5>
                    <a href="#features">Features</a>
                    <a href="#testimonials">Testimonials</a>
                    <a href="<?php echo SITE_URL; ?>modules/auth/login.php">Login</a>
                    <a href="<?php echo SITE_URL; ?>modules/auth/register.php">Register</a>
                </div>
                <div class="col-md-3">
                    <h5>For Students</h5>
                    <a href="#">Upload Syllabus</a>
                    <a href="#">Take Quiz</a>
                    <a href="#">View Resources</a>
                    <a href="#">Get Certificate</a>
                </div>
                <div class="col-md-3">
                    <h5>For Teachers</h5>
                    <a href="#">Create Resources</a>
                    <a href="#">Manage Subjects</a>
                    <a href="#">Track Progress</a>
                    <a href="#">View Analytics</a>
                </div>
            </div>
            <hr class="footer-divider">
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>