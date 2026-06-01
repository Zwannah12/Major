<?php
session_start();
require_once "includes/db.php";
require_once "includes/functions.php";
require_once "includes/security.php";

ensure_public_messages_table($link);

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $error = "Security token validation failed. Please try again.";
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $subject = sanitize_input($_POST['subject'] ?? '');
        $message_text = sanitize_input($_POST['message'] ?? '');

        if ($name === '' || $email === '' || $subject === '' || $message_text === '') {
            $error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            $sql = "INSERT INTO public_messages (name, email, subject, message) VALUES (?, ?, ?, ?)";
            if ($stmt = mysqli_prepare($link, $sql)) {
                mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $subject, $message_text);
                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header("location: contact.php?status=success");
                    exit();
                }
                mysqli_stmt_close($stmt);
            }

            $error = "Something went wrong. Please try again later.";
        }
    }
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - AgroSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .public-hero {
            min-height: 42vh;
            display: flex;
            align-items: end;
            padding: clamp(3rem, 7vw, 5.5rem) 0 2rem;
            color: #fff;
            background:
                linear-gradient(120deg, rgba(6, 25, 13, 0.92), rgba(22, 104, 49, 0.84)),
                url('assets/images/default_crop.jpg') center/cover;
        }
        .surface-panel {
            border: 1px solid var(--surface-border);
            border-radius: 8px;
            background: var(--surface-bg);
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.08);
        }
        .contact-pill {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            padding: .8rem 1rem;
            border-radius: 8px;
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.18);
            width: 100%;
        }
        .contact-icon {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
            flex: 0 0 auto;
        }
        .section-title {
            letter-spacing: 0;
            font-size: clamp(2rem, 4vw, 3.25rem);
            line-height: .98;
        }
    </style>
</head>
<body class="page-contact">
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2 text-success" href="index.php">
                <span class="brand-mark"><i class="fas fa-leaf"></i></span>
                <span>AgroSphere</span>
            </a>
            <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-2"></i>Back Home</a>
        </div>
    </nav>

    <section class="public-hero">
        <div class="container">
            <div class="row align-items-end">
                <div class="col-lg-8">
                    <div class="section-kicker text-warning">Support</div>
                    <h1 class="section-title fw-bold mb-3">Talk to the team behind the marketplace.</h1>
                    <p class="lead mb-0" style="max-width: 720px;">Whether you need help with an account, a listing, an order, or delivery, we keep the conversation close to the platform.</p>
                </div>
            </div>
        </div>
    </section>

    <main class="section-shell">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="surface-panel p-4 h-100">
                        <div class="section-kicker mb-2">Contact details</div>
                        <h4 class="fw-bold mb-4">Direct channels</h4>

                        <div class="contact-pill mb-3">
                            <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="fw-bold">Office</div>
                                <div class="text-muted small">Musanze, Rwanda</div>
                            </div>
                        </div>
                        <div class="contact-pill mb-3">
                            <div class="contact-icon"><i class="fas fa-phone"></i></div>
                            <div>
                                <div class="fw-bold">Phone</div>
                                <div class="text-muted small">+250 794 357 209</div>
                            </div>
                        </div>
                        <div class="contact-pill mb-3">
                            <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                            <div>
                                <div class="fw-bold">Email</div>
                                <div class="text-muted small">info@agrosphere.com</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="small text-muted mb-2">Response window</div>
                        <div class="fw-bold mb-3">Monday to Friday, 8:00 - 17:00</div>
                        <div class="d-flex gap-2">
                            <a href="help.php" class="btn btn-outline-success flex-fill">Help Center</a>
                            <a href="marketplace.php" class="btn btn-success flex-fill">Marketplace</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="surface-panel p-4 p-md-5">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                            <div>
                                <div class="section-kicker">Message us</div>
                                <h3 class="fw-bold mb-1">Send a support request</h3>
                                <p class="text-muted mb-0">Use this form for questions about your account, listings, payments, or delivery coordination.</p>
                            </div>
                            <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
                                <div class="alert alert-success border-0 shadow-sm mb-0">
                                    <i class="fas fa-check-circle me-2"></i>Your message has been sent successfully.
                                </div>
                            <?php elseif (!empty($error)): ?>
                                <div class="alert alert-danger border-0 shadow-sm mb-0">
                                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form action="contact.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Your Name</label>
                                    <input type="text" name="name" class="form-control form-control-lg" placeholder="John Doe" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Email Address</label>
                                    <input type="email" name="email" class="form-control form-control-lg" placeholder="john@example.com" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Subject</label>
                                    <input type="text" name="subject" class="form-control form-control-lg" placeholder="What do you need help with?" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label fw-bold">Message</label>
                                    <textarea name="message" class="form-control" rows="7" placeholder="Tell us what is happening and we will help." required></textarea>
                                </div>
                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-success btn-lg px-5 fw-bold">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-dark text-white py-4 mt-0">
        <div class="container text-center">
            <p class="mb-0 small opacity-50">&copy; 2026 AgroSphere MarketLink. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
