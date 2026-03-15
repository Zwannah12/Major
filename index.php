<?php
// Initialize the session
session_start();

// Include functions for redirection
require_once "includes/functions.php";

// Redirect to dashboard if already logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    redirect_by_role($_SESSION["role"]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroSphere MarketLink - Connecting Rwanda's Agriculture</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Additional Landing Page Specific Styles */
        .hero-section {
            background: linear-gradient(rgba(40, 167, 69, 0.8), rgba(30, 126, 52, 0.9)), url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?ixlib=rb-1.2.1&auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            border-bottom-left-radius: 50% 20px;
            border-bottom-right-radius: 50% 20px;
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 2rem;
            transition: all 0.3s;
        }
        .feature-card:hover .feature-icon {
            background-color: #28a745;
            color: white;
            transform: rotateY(360deg);
        }
        .step-number {
            width: 40px;
            height: 40px;
            background-color: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .testimonial-card {
            border-left: 5px solid #28a745;
            background-color: white;
        }
        .cta-section {
            background-color: #2c3e50;
            color: white;
            padding: 80px 0;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-success" href="index.php">
                <i class="fas fa-leaf"></i> AGROSPHERE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link px-3" href="#how-it-works">How it Works</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="marketplace.php">Marketplace</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#impact">Our Impact</a></li>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-success px-4" href="login.php">Login</a></li>
                    <li class="nav-item ms-2"><a class="btn btn-success px-4 text-white" href="register.php">Join Now</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-center">
        <div class="container animated-fade-in">
            <h1 class="display-3 fw-bold mb-4">Empowering Rwanda's <br><span class="text-warning">Agricultural Ecosystem</span></h1>
            <p class="lead mb-5 mx-auto" style="max-width: 700px;">Directly connecting hard-working farmers with buyers and reliable transporters. Bridging the gap for a sustainable and prosperous future.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="register.php" class="btn btn-warning btn-lg px-5 fw-bold shadow">Get Started</a>
                <a href="marketplace.php" class="btn btn-outline-light btn-lg px-5">Browse Market</a>
            </div>
        </div>
    </header>

    <!-- Features Section -->
    <section class="py-5 mt-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Designed for Every Stakeholder</h2>
                <div class="bg-success mx-auto" style="width: 80px; height: 3px;"></div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 p-4 feature-card shadow-sm text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-tractor"></i></div>
                        <h4 class="fw-bold">For Farmers</h4>
                        <p class="text-muted">List your crops directly, eliminate middlemen, and get the fair price your hard work deserves.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 feature-card shadow-sm text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-shopping-basket"></i></div>
                        <h4 class="fw-bold">For Buyers</h4>
                        <p class="text-muted">Access fresh produce directly from the source. Compare prices and quality across various regions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 feature-card shadow-sm text-center">
                        <div class="feature-icon mx-auto"><i class="fas fa-truck"></i></div>
                        <h4 class="fw-bold">For Transporters</h4>
                        <p class="text-muted">Find delivery requests easily. Optimize your routes and increase your earnings with steady work.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it Works -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <img src="https://images.unsplash.com/photo-1523348837708-15d4a09cfac2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" class="img-fluid rounded-4 shadow" alt="Agriculture">
                </div>
                <div class="col-lg-6 ps-lg-5 mt-4 mt-lg-0">
                    <h2 class="fw-bold mb-4">Simple. Transparent. Efficient.</h2>
                    <div class="d-flex mb-4">
                        <div class="step-number">1</div>
                        <div class="ms-3">
                            <h5 class="fw-bold">Register & Profile</h5>
                            <p class="text-muted">Join as a farmer, buyer, or transporter and set up your verified profile in minutes.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-number">2</div>
                        <div class="ms-3">
                            <h5 class="fw-bold">List or Browse</h5>
                            <p class="text-muted">Farmers list their harvest; buyers browse the marketplace and place orders directly.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-number">3</div>
                        <div class="ms-3">
                            <h5 class="fw-bold">Reliable Delivery</h5>
                            <p class="text-muted">Transporters accept delivery requests, ensuring fresh produce reaches its destination safely.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Impact Stats -->
    <section id="impact" class="py-5 text-white" style="background-color: #1e7e34;">
        <div class="container text-center">
            <h2 class="fw-bold mb-5 text-white">Our Growing Community</h2>
            <div class="row g-4">
                <div class="col-6 col-md-3">
                    <div class="h1 fw-bold animated-number mb-0 text-white" data-target="1500">0</div>
                    <p class="opacity-75">Farmers Onboarded</p>
                </div>
                <div class="col-6 col-md-3">
                    <div class="h1 fw-bold animated-number mb-0 text-white" data-target="2500">0</div>
                    <p class="opacity-75">Crops Listed</p>
                </div>
                <div class="col-6 col-md-3">
                    <div class="h1 fw-bold animated-number mb-0 text-white" data-target="1000">0</div>
                    <p class="opacity-75">Orders Processed</p>
                </div>
                <div class="col-6 col-md-3">
                    <div class="h1 fw-bold animated-number mb-0 text-white" data-target="800">0</div>
                    <p class="opacity-75">Completed Deliveries</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Success Stories -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Voices from the Field</h2>
            </div>
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card p-4 testimonial-card shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-quote-left text-success mb-3 fa-2x opacity-25"></i>
                            <p class="fst-italic lead mb-4">"Thanks to AgroSphere, I've doubled my income and can now send my children to a better school. It's truly a game-changer for rural farmers like me."</p>
                            <div class="d-flex align-items-center">
                                <div class="fw-bold text-success">Marie Uwimana</div>
                                <div class="ms-2 text-muted small">— Tomato Farmer, Bugesera</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card p-4 testimonial-card shadow-sm">
                        <div class="card-body">
                            <i class="fas fa-quote-left text-success mb-3 fa-2x opacity-25"></i>
                            <p class="fst-italic lead mb-4">"I used to spend days looking for quality produce. Now, I can source everything I need from my phone, knowing exactly who grew it and when it was harvested."</p>
                            <div class="d-flex align-items-center">
                                <div class="fw-bold text-success">Jean-Paul Habimana</div>
                                <div class="ms-2 text-muted small">— Wholesale Buyer, Kigali</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="cta-section text-center">
        <div class="container">
            <h2 class="display-5 fw-bold mb-4">Ready to Transform Your Business?</h2>
            <p class="lead mb-5 opacity-75">Join thousands of Rwandans already using AgroSphere MarketLink to grow their agricultural ventures.</p>
            <a href="register.php" class="btn btn-success btn-lg px-5 shadow fw-bold">Create Free Account</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h4 class="fw-bold text-success mb-4"><i class="fas fa-leaf"></i> AGROSPHERE</h4>
                    <p class="opacity-50">Providing innovative digital solutions to empower smallholder farmers and strengthen food security in Rwanda.</p>
                    <div class="d-flex gap-3 mt-4">
                        <a href="#" class="text-white opacity-50 hover-opacity-100"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white opacity-50 hover-opacity-100"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white opacity-50 hover-opacity-100"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 ms-lg-auto">
                    <h6 class="fw-bold mb-4">Platform</h6>
                    <ul class="list-unstyled opacity-50">
                        <li><a href="marketplace.php" class="text-white text-decoration-none">Marketplace</a></li>
                        <li><a href="register.php" class="text-white text-decoration-none">Registration</a></li>
                        <li><a href="login.php" class="text-white text-decoration-none">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-4">Support</h6>
                    <ul class="list-unstyled opacity-50">
                        <li><a href="#" class="text-white text-decoration-none">Help Center</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Contact Us</a></li>
                        <li><a href="#" class="text-white text-decoration-none">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-3">
                    <h6 class="fw-bold mb-4">Newsletter</h6>
                    <p class="small opacity-50">Get the latest agricultural updates and market trends.</p>
                    <div class="input-group mb-3">
                        <input type="email" class="form-control bg-transparent border-secondary text-white" placeholder="Email">
                        <button class="btn btn-success" type="button">Join</button>
                    </div>
                </div>
            </div>
            <hr class="my-5 opacity-10">
            <div class="text-center small opacity-50">
                &copy; 2026 AgroSphere MarketLink. All rights reserved.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html>