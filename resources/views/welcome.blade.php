<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chrispp API - Backend Data Services</title>
    <link rel="icon" type="image/png" href="https://chrispp.com/assets/logo1-7v6plO_9.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #6C63FF;
            --secondary-color: #FF6584;
            --dark-bg: #0F0F1E;
            --card-bg: #1A1A2E;
            --text-light: #E0E0E0;
            --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0F0F1E 0%, #1A1A2E 50%, #16213E 100%);
            color: var(--text-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.1;
            background-image: 
                radial-gradient(circle at 20% 50%, var(--primary-color) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, var(--secondary-color) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, #764ba2 0%, transparent 50%);
            animation: gradientShift 15s ease infinite;
        }
        
        @keyframes gradientShift {
            0%, 100% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
        }
        
        /* Navigation */
        .navbar {
            background: rgba(26, 26, 46, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
            padding: 1rem 0;
        }
        
        .navbar-brand img {
            height: 45px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover img {
            transform: scale(1.05);
        }
        
        /* Hero Section */
        .hero-section {
            padding: 100px 0;
            text-align: center;
            position: relative;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.8s ease;
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            color: var(--text-light);
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 0.8s ease 0.2s both;
        }
        
        .api-badge {
            display: inline-block;
            padding: 12px 30px;
            background: var(--accent-gradient);
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            color: white;
            box-shadow: 0 10px 30px rgba(108, 99, 255, 0.3);
            animation: pulse 2s infinite;
            margin-bottom: 3rem;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 10px 30px rgba(108, 99, 255, 0.3); }
            50% { transform: scale(1.05); box-shadow: 0 15px 40px rgba(108, 99, 255, 0.4); }
            100% { transform: scale(1); box-shadow: 0 10px 30px rgba(108, 99, 255, 0.3); }
        }
        
        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            padding: 50px 0;
        }
        
        .feature-card {
            background: rgba(26, 26, 46, 0.6);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(108, 99, 255, 0.2);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(108, 99, 255, 0.1) 0%, transparent 70%);
            transform: rotate(45deg);
            transition: all 0.5s ease;
            opacity: 0;
        }
        
        .feature-card:hover::before {
            opacity: 1;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-color);
            box-shadow: 0 20px 40px rgba(108, 99, 255, 0.2);
        }
        
        .feature-icon {
            font-size: 3rem;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .feature-title {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: white;
        }
        
        .feature-description {
            color: rgba(224, 224, 224, 0.8);
            line-height: 1.6;
        }
        
        /* API Status Section */
        .status-section {
            padding: 80px 0;
            background: rgba(26, 26, 46, 0.3);
            margin: 50px 0;
            border-radius: 30px;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 25px;
            background: rgba(40, 167, 69, 0.1);
            border: 2px solid #28a745;
            border-radius: 50px;
            color: #28a745;
            font-weight: 600;
            animation: fadeInUp 0.8s ease;
        }
        
        .status-dot {
            width: 12px;
            height: 12px;
            background: #28a745;
            border-radius: 50%;
            animation: blink 2s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        /* Code Block */
        .code-block {
            background: #0A0A0F;
            border: 1px solid rgba(108, 99, 255, 0.2);
            border-radius: 15px;
            padding: 30px;
            margin: 40px 0;
            position: relative;
            overflow: hidden;
        }
        
        .code-block::before {
            content: 'EXAMPLE REQUEST';
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 0.75rem;
            color: var(--primary-color);
            opacity: 0.5;
            letter-spacing: 2px;
        }
        
        .code-text {
            color: #61DAFB;
            font-family: 'Courier New', monospace;
            font-size: 1rem;
            line-height: 1.8;
        }
        
        .code-comment {
            color: #6A6A6A;
        }
        
        /* CTA Section */
        .cta-section {
            text-align: center;
            padding: 80px 0;
        }
        
        .cta-button {
            display: inline-block;
            padding: 18px 50px;
            background: var(--accent-gradient);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(108, 99, 255, 0.3);
        }
        
        .cta-button:hover {
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(108, 99, 255, 0.4);
        }
        
        /* Footer */
        footer {
            background: rgba(15, 15, 30, 0.8);
            padding: 40px 0;
            text-align: center;
            border-top: 1px solid rgba(108, 99, 255, 0.2);
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-animation"></div>
    
    <!-- Navigation -->
    <nav class="navbar navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="https://chrispp.com/assets/logotext-4e_8MxMD.png" alt="Chrispp Logo">
            </a>
            <div class="status-indicator">
                <span class="status-dot"></span>
                API Status: Operational
            </div>
        </div>
    </nav>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="api-badge">
                <i class="bi bi-code-slash"></i> API ONLY
            </div>
            <h1 class="hero-title">Chrispp Backend APIs</h1>
            <p class="hero-subtitle">Powerful Data Services & RESTful API Endpoints</p>
            
            <div class="row mt-5">
                <div class="col-lg-8 mx-auto">
                    <div class="alert alert-info" style="background: rgba(108, 99, 255, 0.1); border: 1px solid var(--primary-color); color: var(--text-light);">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        <strong>Notice:</strong> This domain serves exclusively as an API endpoint. No web interface is available. Please integrate using our RESTful API documentation.
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Grid -->
    <section class="container">
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-lightning-charge-fill"></i>
                </div>
                <h3 class="feature-title">High Performance</h3>
                <p class="feature-description">Lightning-fast response times with optimized backend infrastructure designed for scale</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3 class="feature-title">Secure & Reliable</h3>
                <p class="feature-description">Enterprise-grade security with SSL encryption and 99.9% uptime guarantee</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="bi bi-graph-up"></i>
                </div>
                <h3 class="feature-title">Scalable Architecture</h3>
                <p class="feature-description">Auto-scaling infrastructure that grows with your application needs</p>
            </div>
        </div>
    </section>
    
    <!-- API Status Section -->
    <section class="status-section">
        <div class="container text-center">
            <h2 class="mb-4" style="color: white;">API Endpoints Overview</h2>
            <div class="row mt-5">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="text-center">
                        <h3 style="color: var(--primary-color); font-size: 2.5rem; font-weight: bold;">250ms</h3>
                        <p>Avg Response Time</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="text-center">
                        <h3 style="color: var(--primary-color); font-size: 2.5rem; font-weight: bold;">99.9%</h3>
                        <p>Uptime SLA</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="text-center">
                        <h3 style="color: var(--primary-color); font-size: 2.5rem; font-weight: bold;">RESTful</h3>
                        <p>API Architecture</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="text-center">
                        <h3 style="color: var(--primary-color); font-size: 2.5rem; font-weight: bold;">JSON</h3>
                        <p>Response Format</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Code Example -->
    <section class="container">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <h2 class="text-center mb-4" style="color: white;">Sample API Request</h2>
                <div class="code-block">
                    <div class="code-text">
                        <span class="code-comment">// Example API endpoint request</span><br>
                        GET https://api.chrispp.com/v1/data<br>
                        <span style="color: #FF6584;">Authorization:</span> Bearer YOUR_API_KEY<br>
                        <span style="color: #FF6584;">Content-Type:</span> application/json<br><br>
                        
                        <span class="code-comment">// Response</span><br>
                        {<br>
                        &nbsp;&nbsp;"status": "success",<br>
                        &nbsp;&nbsp;"data": { ... },<br>
                        &nbsp;&nbsp;"timestamp": "2025-09-12T00:00:00Z"<br>
                        }
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2 class="mb-4" style="color: white;">Ready to Integrate?</h2>
            <p class="mb-4" style="font-size: 1.2rem; opacity: 0.9;">Access our comprehensive API documentation to get started</p>
            <a href="#" class="cta-button">
                <i class="bi bi-book me-2"></i>View API Documentation
            </a>
        </div>
    </section>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <img src="https://chrispp.com/assets/logo1-7v6plO_9.png" alt="Chrispp" style="height: 40px; margin-bottom: 20px;">
            <p class="mb-2">© 2025 Chrispp. All rights reserved.</p>
            <p style="opacity: 0.7; font-size: 0.9rem;">Backend Data API Services</p>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>