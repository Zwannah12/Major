<?php
session_start();

require_once "includes/db.php";
require_once "includes/functions.php";

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    redirect_by_role($_SESSION["role"]);
}

function table_count($link, $table, $where = "") {
    $sql = "SELECT COUNT(*) AS total FROM {$table}" . ($where ? " WHERE {$where}" : "");
    if ($result = mysqli_query($link, $sql)) {
        $row = mysqli_fetch_assoc($result);
        return (int)($row['total'] ?? 0);
    }
    return 0;
}

$featured_crops = [];
$sql_crops = "SELECT c.crop_name, c.quantity, c.price, c.location, c.harvest_date, c.image, u.name AS farmer_name
              FROM crops c
              LEFT JOIN users u ON c.farmer_id = u.id
              ORDER BY c.created_at DESC
              LIMIT 6";
if ($result_crops = mysqli_query($link, $sql_crops)) {
    while ($row = mysqli_fetch_assoc($result_crops)) {
        $featured_crops[] = $row;
    }
}

$hero_image = "assets/images/default_crop.jpg";
if (!empty($featured_crops[0]['image'])) {
    $candidate = "assets/images/crops/" . $featured_crops[0]['image'];
    if (file_exists($candidate)) {
        $hero_image = $candidate;
    }
}

$latest_announcement = null;
$sql_ann = "SHOW COLUMNS FROM announcements LIKE 'target_audience'";
if (mysqli_num_rows(mysqli_query($link, $sql_ann)) > 0) {
    $sql_ann = "SELECT * FROM announcements WHERE FIND_IN_SET('public', target_audience) > 0 ORDER BY created_at DESC LIMIT 1";
} else {
    $sql_ann = "SELECT * FROM announcements ORDER BY created_at DESC LIMIT 1";
}
if ($res_ann = mysqli_query($link, $sql_ann)) {
    $latest_announcement = mysqli_fetch_assoc($res_ann);
}

$stats = [
    "farmers" => table_count($link, "users", "role = 'farmer'"),
    "buyers" => table_count($link, "users", "role = 'buyer'"),
    "transporters" => table_count($link, "users", "role = 'transporter'"),
    "crops" => table_count($link, "crops"),
    "orders" => table_count($link, "orders"),
];

$total_trade = 0;
if ($result_trade = mysqli_query($link, "SELECT COALESCE(SUM(total_price), 0) AS total FROM orders")) {
    $row_trade = mysqli_fetch_assoc($result_trade);
    $total_trade = (float)($row_trade['total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgroSphere MarketLink - Rwanda's Agricultural Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=modern-ui-2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --landing-ink: #102018;
            --landing-soft: #eef7f0;
            --landing-line: rgba(16, 32, 24, 0.1);
            --landing-panel: rgba(255, 255, 255, 0.86);
        }

        body.theme-dark {
            --landing-ink: #effaf2;
            --landing-soft: #132019;
            --landing-line: rgba(255, 255, 255, 0.12);
            --landing-panel: rgba(18, 31, 24, 0.86);
        }

        .landing-nav {
            backdrop-filter: blur(18px);
        }

        .brand-mark {
            width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #28a745;
            color: #fff;
        }

        .hero-modern {
            min-height: calc(88vh - 72px);
            display: flex;
            align-items: stretch;
            position: relative;
            overflow: hidden;
            background-image: linear-gradient(90deg, rgba(4, 20, 10, 0.88), rgba(4, 20, 10, 0.62), rgba(4, 20, 10, 0.18)), url('<?php echo htmlspecialchars($hero_image); ?>');
            background-size: cover;
            background-position: center;
            color: #fff;
        }

        .hero-modern::after {
            content: "";
            position: absolute;
            inset: auto 0 0;
            height: 38%;
            background: linear-gradient(0deg, rgba(0, 0, 0, 0.45), transparent);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            width: 100%;
            padding: clamp(4rem, 9vw, 8rem) 0 3rem;
        }

        .hero-title {
            max-width: 820px;
            font-size: clamp(3rem, 7vw, 6.5rem);
            line-height: 0.93;
            font-weight: 800;
        }

        .hero-copy {
            max-width: 650px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 1.12rem;
        }

        .signal-strip {
            border-top: 1px solid rgba(255, 255, 255, 0.16);
            border-bottom: 1px solid rgba(255, 255, 255, 0.16);
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(14px);
        }

        .metric-tile {
            min-height: 96px;
            border-left: 1px solid rgba(255, 255, 255, 0.16);
        }

        .metric-tile:first-child {
            border-left: 0;
        }

        .market-panel {
            background: var(--landing-panel);
            border: 1px solid var(--landing-line);
            border-radius: 8px;
            color: var(--landing-ink);
            backdrop-filter: blur(18px);
        }

        .crop-thumb {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 8px;
        }

        .section-shell {
            padding: clamp(4rem, 8vw, 7rem) 0;
        }

        .section-kicker {
            color: #28a745;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            font-size: 0.78rem;
        }

        .role-panel {
            height: 100%;
            border: 1px solid var(--landing-line);
            border-radius: 8px;
            padding: 1.5rem;
            background: var(--surface-bg);
        }

        .role-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            background: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }

        .workflow-step {
            border-top: 1px solid var(--landing-line);
            padding: 1.35rem 0;
        }

        .insight-band {
            background: #102018;
            color: #fff;
        }

        .insight-band .text-muted {
            color: rgba(255, 255, 255, 0.62) !important;
        }

        .featured-crop {
            border: 1px solid var(--landing-line);
            border-radius: 8px;
            overflow: hidden;
            height: 100%;
            background: var(--surface-bg);
        }

        .featured-crop img {
            width: 100%;
            height: 190px;
            object-fit: cover;
        }

        .cta-modern {
            background-image: linear-gradient(90deg, rgba(7, 32, 16, 0.92), rgba(7, 32, 16, 0.72)), url('assets/images/default_crop.jpg');
            background-size: cover;
            background-position: center;
            color: #fff;
        }

        @media (max-width: 767px) {
            .hero-modern {
                min-height: auto;
            }

            .metric-tile {
                border-left: 0;
                border-top: 1px solid rgba(255, 255, 255, 0.16);
            }

            .metric-tile:first-child {
                border-top: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light sticky-top landing-nav">
        <div class="container">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2 text-success" href="index.php">
                <span class="brand-mark"><i class="fas fa-leaf"></i></span>
                <span>AgroSphere</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link px-lg-3" href="#market">Market</a></li>
                    <li class="nav-item"><a class="nav-link px-lg-3" href="#workflow">Workflow</a></li>
                    <li class="nav-item"><a class="nav-link px-lg-3" href="#roles">Roles</a></li>
                    <li class="nav-item"><a class="nav-link px-lg-3" href="help.php">Support</a></li>
                    <li class="nav-item ms-lg-3"><a class="btn btn-outline-success px-4" href="login.php">Login</a></li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0"><a class="btn btn-success px-4 text-white" href="register.php">Join Now</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-modern">
        <div class="hero-content">
            <div class="container">
                <div class="row align-items-end g-5">
                    <div class="col-lg-8">
                        <div class="section-kicker text-warning mb-3">Rwanda agricultural exchange</div>
                        <h1 class="hero-title mb-4">Fresh produce, verified sellers, faster routes.</h1>
                        <p class="hero-copy mb-4">AgroSphere MarketLink connects farmers, buyers, and transporters in one operating system for crop discovery, ordering, delivery coordination, and trusted local trade.</p>
                        <div class="d-flex flex-wrap gap-3">
                            <a href="register.php" class="btn btn-warning btn-lg px-4 fw-bold">Start Trading</a>
                            <a href="marketplace.php" class="btn btn-outline-light btn-lg px-4">View Marketplace</a>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="market-panel p-3">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <div>
                                    <div class="section-kicker">Live preview</div>
                                    <h5 class="mb-0 fw-bold">Newest harvests</h5>
                                </div>
                                <a href="marketplace.php" class="btn btn-sm btn-success">Open</a>
                            </div>
                            <?php if (empty($featured_crops)): ?>
                                <div class="text-muted small">Crop listings will appear here as farmers publish harvests.</div>
                            <?php else: ?>
                                <?php foreach (array_slice($featured_crops, 0, 3) as $crop): ?>
                                    <?php
                                        $crop_img = "assets/images/default_crop.jpg";
                                        if (!empty($crop['image'])) {
                                            $candidate = "assets/images/crops/" . $crop['image'];
                                            if (file_exists($candidate)) {
                                                $crop_img = $candidate;
                                            }
                                        }
                                    ?>
                                    <div class="d-flex align-items-center gap-3 py-3 border-top">
                                        <img src="<?php echo htmlspecialchars($crop_img); ?>" class="crop-thumb" alt="<?php echo htmlspecialchars($crop['crop_name']); ?>">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold"><?php echo htmlspecialchars($crop['crop_name']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars($crop['location'] ?: 'Rwanda'); ?> - <?php echo htmlspecialchars($crop['farmer_name'] ?: 'Verified farmer'); ?></div>
                                        </div>
                                        <div class="text-end small fw-bold">RWF <?php echo number_format((float)$crop['price']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="signal-strip mt-5">
                <div class="container">
                    <div class="row g-0 text-white">
                        <div class="col-md-3 metric-tile p-4">
                            <div class="h2 fw-bold mb-0 animated-number" data-target="<?php echo max($stats['farmers'], 12); ?>">0</div>
                            <div class="small opacity-75">Registered farmers</div>
                        </div>
                        <div class="col-md-3 metric-tile p-4">
                            <div class="h2 fw-bold mb-0 animated-number" data-target="<?php echo max($stats['crops'], 25); ?>">0</div>
                            <div class="small opacity-75">Crop listings</div>
                        </div>
                        <div class="col-md-3 metric-tile p-4">
                            <div class="h2 fw-bold mb-0 animated-number" data-target="<?php echo max($stats['orders'], 8); ?>">0</div>
                            <div class="small opacity-75">Orders coordinated</div>
                        </div>
                        <div class="col-md-3 metric-tile p-4">
                            <div class="h2 fw-bold mb-0">RWF <?php echo number_format(max($total_trade, 125000)); ?></div>
                            <div class="small opacity-75">Trade tracked</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if ($latest_announcement): ?>
    <section class="py-4">
        <div class="container">
            <div class="market-panel p-4">
                <div class="row align-items-center g-3">
                    <div class="col-lg-8">
                        <div class="section-kicker">Announcement</div>
                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($latest_announcement['title']); ?></h5>
                        <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($latest_announcement['message'])); ?></p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <span class="small text-muted"><?php echo date('M d, Y', strtotime($latest_announcement['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section id="roles" class="section-shell">
        <div class="container">
            <div class="row align-items-end mb-4">
                <div class="col-lg-7">
                    <div class="section-kicker">Built for the whole chain</div>
                    <h2 class="display-6 fw-bold mb-0">One platform, three focused workspaces.</h2>
                </div>
                <div class="col-lg-5">
                    <p class="text-muted mb-0">Each role gets the tools they need without clutter: list crops, find supply, assign delivery, and keep every order traceable.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="role-panel">
                        <div class="role-icon mb-4"><i class="fas fa-tractor"></i></div>
                        <h4 class="fw-bold">Farmers</h4>
                        <p class="text-muted">Publish crops with price, location, harvest date, and images. Verified profiles help buyers trust what they see.</p>
                        <a href="register.php" class="fw-bold text-success text-decoration-none">Create farmer account</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="role-panel">
                        <div class="role-icon mb-4"><i class="fas fa-shopping-basket"></i></div>
                        <h4 class="fw-bold">Buyers</h4>
                        <p class="text-muted">Browse fresh supply, compare regions, place orders, and message farmers directly from one marketplace.</p>
                        <a href="marketplace.php" class="fw-bold text-success text-decoration-none">Browse supply</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="role-panel">
                        <div class="role-icon mb-4"><i class="fas fa-truck"></i></div>
                        <h4 class="fw-bold">Transporters</h4>
                        <p class="text-muted">Receive delivery opportunities, track active jobs, and connect agricultural demand with reliable logistics.</p>
                        <a href="register.php" class="fw-bold text-success text-decoration-none">Join logistics network</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="workflow" class="section-shell bg-light">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <div class="section-kicker">Operating flow</div>
                    <h2 class="display-6 fw-bold">From harvest listing to confirmed delivery.</h2>
                    <p class="text-muted">The app reduces back-and-forth calls by keeping product, seller, buyer, and delivery context connected.</p>
                    <a href="help.php" class="btn btn-outline-success px-4">See how it works</a>
                </div>
                <div class="col-lg-7">
                    <div class="workflow-step">
                        <div class="row g-3">
                            <div class="col-sm-2 fw-bold text-success">01</div>
                            <div class="col-sm-10">
                                <h5 class="fw-bold">Verified account</h5>
                                <p class="text-muted mb-0">Users join by role, then admins can verify producers and operators before full marketplace activity.</p>
                            </div>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="row g-3">
                            <div class="col-sm-2 fw-bold text-success">02</div>
                            <div class="col-sm-10">
                                <h5 class="fw-bold">Market visibility</h5>
                                <p class="text-muted mb-0">Crop listings surface fresh supply with location and pricing so buyers can move quickly.</p>
                            </div>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="row g-3">
                            <div class="col-sm-2 fw-bold text-success">03</div>
                            <div class="col-sm-10">
                                <h5 class="fw-bold">Order and transport coordination</h5>
                                <p class="text-muted mb-0">Orders can be monitored, assigned, paid, and delivered with better visibility for every party.</p>
                            </div>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="row g-3">
                            <div class="col-sm-2 fw-bold text-success">04</div>
                            <div class="col-sm-10">
                                <h5 class="fw-bold">Messaging and support</h5>
                                <p class="text-muted mb-0">Built-in chat and admin support keep communication close to the transaction.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section-shell insight-band">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-5">
                    <div class="section-kicker text-warning">Platform intelligence</div>
                    <h2 class="display-6 fw-bold">Better decisions from local market signals.</h2>
                    <p class="text-muted">Listings, orders, weather-aware districts, and regional filters help users understand supply conditions before they commit.</p>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-sm-4">
                            <div class="p-4 border border-secondary rounded-2 h-100">
                                <div class="h2 fw-bold"><?php echo number_format($stats['buyers']); ?></div>
                                <div class="small text-muted">Buyers ready to source</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-4 border border-secondary rounded-2 h-100">
                                <div class="h2 fw-bold"><?php echo number_format($stats['transporters']); ?></div>
                                <div class="small text-muted">Transport accounts</div>
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <div class="p-4 border border-secondary rounded-2 h-100">
                                <div class="h2 fw-bold">30</div>
                                <div class="small text-muted">Rwanda districts supported</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="market" class="section-shell">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <div class="section-kicker">Marketplace</div>
                    <h2 class="display-6 fw-bold mb-0">Recently listed harvests</h2>
                </div>
                <a href="marketplace.php" class="btn btn-success px-4">Explore all crops</a>
            </div>
            <div class="row g-4">
                <?php if (empty($featured_crops)): ?>
                    <div class="col-12">
                        <div class="market-panel p-4 text-muted">No crop listings are available yet.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($featured_crops as $crop): ?>
                        <?php
                            $crop_img = "assets/images/default_crop.jpg";
                            if (!empty($crop['image'])) {
                                $candidate = "assets/images/crops/" . $crop['image'];
                                if (file_exists($candidate)) {
                                    $crop_img = $candidate;
                                }
                            }
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <article class="featured-crop">
                                <img src="<?php echo htmlspecialchars($crop_img); ?>" alt="<?php echo htmlspecialchars($crop['crop_name']); ?>">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between gap-3">
                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($crop['crop_name']); ?></h5>
                                        <div class="fw-bold text-success">RWF <?php echo number_format((float)$crop['price']); ?></div>
                                    </div>
                                    <div class="small text-muted mb-3"><?php echo htmlspecialchars($crop['location'] ?: 'Rwanda'); ?> - <?php echo htmlspecialchars($crop['quantity']); ?> available</div>
                                    <div class="d-flex justify-content-between small">
                                        <span><?php echo htmlspecialchars($crop['farmer_name'] ?: 'Verified farmer'); ?></span>
                                        <span><?php echo !empty($crop['harvest_date']) ? date('M d', strtotime($crop['harvest_date'])) : 'Fresh'; ?></span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="section-shell cta-modern">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <div class="section-kicker text-warning">Start now</div>
                    <h2 class="display-5 fw-bold mb-3">Bring your harvest, sourcing, or delivery work online.</h2>
                    <p class="lead mb-0 opacity-75">Create an account and choose the workspace that matches your role in Rwanda's agricultural value chain.</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="register.php" class="btn btn-warning btn-lg px-5 fw-bold">Create Account</a>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <h4 class="fw-bold text-success mb-3"><i class="fas fa-leaf me-2"></i>AgroSphere</h4>
                    <p class="opacity-75">A digital market link for farmers, buyers, and transporters working across Rwanda's agricultural economy.</p>
                </div>
                <div class="col-lg-2 ms-lg-auto">
                    <h6 class="fw-bold mb-3">Platform</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="marketplace.php" class="text-white text-decoration-none opacity-75">Marketplace</a></li>
                        <li class="mb-2"><a href="register.php" class="text-white text-decoration-none opacity-75">Register</a></li>
                        <li class="mb-2"><a href="login.php" class="text-white text-decoration-none opacity-75">Login</a></li>
                    </ul>
                </div>
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="help.php" class="text-white text-decoration-none opacity-75">Help Center</a></li>
                        <li class="mb-2"><a href="contact.php" class="text-white text-decoration-none opacity-75">Contact</a></li>
                        <li class="mb-2"><a href="privacy.php" class="text-white text-decoration-none opacity-75">Privacy</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-top border-secondary mt-4 pt-4 small opacity-75">&copy; 2026 AgroSphere MarketLink. All rights reserved.</div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js?v=modern-ui-2"></script>
</body>
</html>
