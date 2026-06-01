<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Center - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-light page-help">
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-success" href="index.php">
                <i class="fas fa-leaf"></i> AGROSPHERE
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Home</a>
        </div>
    </nav>

    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="fw-bold">How can we help you?</h1>
            <p class="lead text-muted">Quick answers for farmers, buyers, transporters, and administrators using AgroSphere MarketLink.</p>
        </div>

        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-success fs-3 mb-3"><i class="fas fa-user-check"></i></div>
                        <h5 class="fw-bold">Account Help</h5>
                        <p class="text-muted mb-0">Registration, login, verification, passwords, and profile updates.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-success fs-3 mb-3"><i class="fas fa-shopping-basket"></i></div>
                        <h5 class="fw-bold">Orders & Payments</h5>
                        <p class="text-muted mb-0">Placing orders, confirming payments, delivery status, and invoices.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-success fs-3 mb-3"><i class="fas fa-headset"></i></div>
                        <h5 class="fw-bold">Support</h5>
                        <p class="text-muted mb-0">Contact the team, report problems, and get help with disputes.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="accordion shadow-sm" id="helpAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne">
                                <strong>What is AgroSphere MarketLink?</strong>
                            </button>
                        </h2>
                        <div id="collapseOne" class="accordion-collapse collapse show" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                AgroSphere is a digital marketplace that connects Rwandan farmers with buyers and transporters. Farmers can publish crop listings, buyers can place orders, transporters can manage deliveries, and admins help keep the platform verified and organized.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo">
                                <strong>How do I create and verify my account?</strong>
                            </button>
                        </h2>
                        <div id="collapseTwo" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Register with your name, email, phone number, location, and role. After registration, an admin may need to verify your account before you can publish crop listings, accept deliveries, or access some protected actions. Keep your contact information accurate so the team can confirm your details.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree">
                                <strong>I cannot log in. What should I check?</strong>
                            </button>
                        </h2>
                        <div id="collapseThree" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Confirm that your email and password are correct, then check whether your account is verified. If you forgot your password, use the password reset page. If the problem continues, contact support with your registered email and role.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour">
                                <strong>How do I list crops as a farmer?</strong>
                            </button>
                        </h2>
                        <div id="collapseFour" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Log in to the Farmer Dashboard and choose the option to add a crop. Enter the crop name, quantity, price, district, harvest date, and a clear image. Listings may require admin approval before buyers can act on them.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive">
                                <strong>How should farmers price and describe crops?</strong>
                            </button>
                        </h2>
                        <div id="collapseFive" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Use realistic market prices, accurate quantities, and a specific district. Mention quality details such as freshness, harvest date, packaging, or storage conditions. Clear information helps buyers trust the listing and reduces order disputes.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSix">
                                <strong>How do buyers place and track orders?</strong>
                            </button>
                        </h2>
                        <div id="collapseSix" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Go to the Marketplace, review crop details, enter the quantity you need, and place the order. Your Buyer Dashboard shows order status, payment status, farmer details, and delivery progress when a transporter is assigned.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSeven">
                                <strong>How do payments work?</strong>
                            </button>
                        </h2>
                        <div id="collapseSeven" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                After placing an order, you can continue to the payment page and follow the available payment instructions. Keep your transaction reference or confirmation message until the order is fully confirmed.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseEight">
                                <strong>How do transporters get delivery work?</strong>
                            </button>
                        </h2>
                        <div id="collapseEight" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Verified transporters can receive delivery assignments from admins. Once assigned, the job appears in the Transporter Dashboard. Accept the delivery, update the status as it moves, and keep both buyer and farmer informed through the platform.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseNine">
                                <strong>What should I do if an order has a problem?</strong>
                            </button>
                        </h2>
                        <div id="collapseNine" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Contact support as soon as possible. Include the order number, crop name, issue description, and any useful evidence such as payment confirmation or delivery notes. The admin team can review messages, payments, and delivery records.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTen">
                                <strong>How do I keep my account safe?</strong>
                            </button>
                        </h2>
                        <div id="collapseTen" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                Use a strong password, do not share your login details, and log out on shared devices. Be careful with payment requests outside the platform and report suspicious accounts or messages to support.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mt-5">
                    <div class="col-md-6">
                        <div class="p-4 bg-white rounded shadow-sm h-100 border-start border-4 border-success">
                            <h5 class="fw-bold"><i class="fas fa-clock me-2 text-success"></i>Best Time To Contact Support</h5>
                            <p class="text-muted mb-0">Send a clear message anytime through the contact page. For faster help, include your account email, role, order ID, and the exact action that caused the problem.</p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-4 bg-white rounded shadow-sm h-100 border-start border-4 border-success">
                            <h5 class="fw-bold"><i class="fas fa-shield-alt me-2 text-success"></i>Trust & Safety</h5>
                            <p class="text-muted mb-0">Admins review users, listings, payments, and support messages to reduce fraud and keep the marketplace useful for serious agriculture trade.</p>
                        </div>
                    </div>
                </div>

                <div class="mt-5 p-4 bg-white rounded shadow-sm border-start border-4 border-success text-center">
                    <h5>Still need help?</h5>
                    <p class="text-muted">Can't find the answer you're looking for? Please contact our support team.</p>
                    <a href="contact.php" class="btn btn-success px-4">Contact Support</a>
                    <a href="contact_admin.php" class="btn btn-outline-success px-4 ms-2">Message Admin</a>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p class="mb-0 small opacity-50">&copy; 2026 AgroSphere MarketLink. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
