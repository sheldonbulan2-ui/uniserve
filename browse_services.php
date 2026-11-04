
<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "customer"
) {
    header("Location: login.php");
    exit();
}

function escape_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$customer_name = $_SESSION["full_name"] ?? "Customer";
$search = trim($_GET["search"] ?? "");
$category_id = filter_var(
    $_GET["category"] ?? "",
    FILTER_VALIDATE_INT
);

if (!$category_id || $category_id < 1) {
    $category_id = 0;
}

/* Load available categories */
$categories_sql = "
    SELECT DISTINCT c.category_id, c.category_name
    FROM categories c
    INNER JOIN businesses b
        ON b.category_id = c.category_id
    INNER JOIN services s
        ON s.business_id = b.business_id
    WHERE s.availability = 'available'
      AND b.status = 'approved'
    ORDER BY c.category_name ASC
";

$categories_result = $conn->query($categories_sql);

if (!$categories_result) {
    http_response_code(500);
    exit("Unable to load service categories.");
}

/* Build service search */
$sql = "
    SELECT
        s.service_id,
        s.service_name,
        s.description,
        s.price,
        b.business_name,
        b.address,
        b.contact_number,
        c.category_id,
        c.category_name
    FROM services s
    INNER JOIN businesses b
        ON s.business_id = b.business_id
    LEFT JOIN categories c
        ON b.category_id = c.category_id
    WHERE s.availability = 'available'
      AND b.status = 'approved'
";

if ($search !== "") {
    $safe_search = $conn->real_escape_string($search);

    $sql .= "
        AND (
            s.service_name LIKE '%{$safe_search}%'
            OR s.description LIKE '%{$safe_search}%'
            OR b.business_name LIKE '%{$safe_search}%'
            OR b.address LIKE '%{$safe_search}%'
            OR c.category_name LIKE '%{$safe_search}%'
        )
    ";
}

if ($category_id > 0) {
    $sql .= " AND c.category_id = " . (int) $category_id;
}

$sql .= " ORDER BY b.business_name ASC, s.service_name ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    exit("Unable to load available services. Please check the database configuration.");
}

$total_services = $result->num_rows;
$initial = strtoupper(substr(trim($customer_name), 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Browse Services | UniServe</title>

    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/browse_services.css">
</head>

<body class="browse-services-page">

<div class="layout">

       <aside class="sidebar admin-sidebar">

        <div class="brand">
            <div class="brand-icon">U</div>

            <div>
                <h1>UniServe</h1>
                <p>Business &amp; Service Management</p>
            </div>
        </div>

        <h3>Customer Menu</h3>

        <nav aria-label="Customer navigation">
            <a href="customer_dashboard.php">
                <span class="nav-icon">▦</span>
                Dashboard
            </a>

            <a class="active" href="browse_services.php">
                <span class="nav-icon">⌕</span>
                Browse Services
            </a>

            <a href="my_requests.php">
                <span class="nav-icon">☷</span>
                My Requests
            </a>
        </nav>

        <div class="sidebar-bottom">
            <h3>Account</h3>

            <nav aria-label="Account navigation">
                <a href="profile.php">
                    <span class="nav-icon">♙</span>
                    My Profile
                </a>

                <a class="logout-link" href="logout.php">
                    <span class="nav-icon">↪</span>
                    Logout
                </a>
            </nav>
        </div>

    </aside>

    <div class="main-area">

        <header class="topbar">
            <div class="topbar-heading">
                <h2>Browse Services</h2>
                <p>Find the right service for your needs.</p>
            </div>

            <div class="user-info">
                <div class="avatar">
                    <?= escape_html($initial) ?>
                </div>

                <div>
                    <strong><?= escape_html($customer_name) ?></strong>
                    <p>Customer Account</p>
                </div>
            </div>
        </header>

        <main class="content">

            <div class="browse-breadcrumb">
                <a href="customer_dashboard.php">Dashboard</a>
                <span>/</span>
                <strong>Browse Services</strong>
            </div>

            <section class="browse-hero">
                <div class="browse-hero-copy">
                    <span class="browse-eyebrow">CUSTOMER PORTAL</span>
                    <h1>Discover Services</h1>
                    <p>
                        Explore services offered by approved businesses
                        and find the right one for your needs.
                    </p>
                </div>

                <a href="my_requests.php" class="browse-hero-button">
                    View My Requests
                </a>
            </section>


            <section class="browse-directory">

                <div class="browse-section-header">
                    <div>
                        <h2>Services Directory</h2>
                        <p>Search and explore available business services.</p>
                    </div>

                    <span class="browse-count">
                        <?= number_format($total_services) ?>
                        <?= $total_services === 1 ? "service" : "services" ?>
                    </span>
                </div>

                <form method="GET"
                      action="browse_services.php"
                      class="browse-search-form">

                    <div class="browse-search-field">
                        <label for="service-search">Search services</label>
                        <input
                            type="search"
                            id="service-search"
                            name="search"
                            value="<?= escape_html($search) ?>"
                            placeholder="Search by service, business, description..."
                        >
                    </div>

                    <div class="browse-category-field">
                        <label for="service-category">Category</label>
                        <select id="service-category" name="category">
                            <option value="">All Categories</option>

                            <?php while ($category = $categories_result->fetch_assoc()): ?>
                                <option
                                    value="<?= (int) $category["category_id"] ?>"
                                    <?= $category_id === (int) $category["category_id"] ? "selected" : "" ?>
                                >
                                    <?= escape_html($category["category_name"]) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <button type="submit" class="browse-search-button">
                        Search
                    </button>

                    <a href="browse_services.php" class="browse-reset-button">
                        Reset
                    </a>

                </form>

                <div class="browse-results-heading">
                    <h3>Services for You</h3>
                    <p>
                        <?= number_format($total_services) ?>
                        matching <?= $total_services === 1 ? "result" : "results" ?>
                    </p>
                </div>

                <?php if ($total_services > 0): ?>

                    <div class="browse-service-grid">

                        <?php while ($service = $result->fetch_assoc()): ?>

                            <article class="browse-service-card">

                                <div class="service-card-top">
                                    <span class="service-category">
                                        <?= escape_html($service["category_name"] ?: "Other Services") ?>
                                    </span>

                                    <span class="service-availability">
                                        <span class="availability-dot"></span>
                                        Available
                                    </span>
                                </div>

                                <h3 class="service-name">
                                    <?= escape_html($service["service_name"]) ?>
                                </h3>

                                <p class="service-description">
                                    <?php
                                    $description = trim((string) $service["description"]);
                                    echo $description !== ""
                                        ? nl2br(escape_html($description))
                                        : "No description provided.";
                                    ?>
                                </p>

                                <div class="service-business-details">

                                    <div class="service-detail">
                                        <span class="service-detail-label">BUSINESS</span>
                                        <strong>
                                            <?= escape_html($service["business_name"]) ?>
                                        </strong>
                                    </div>

                                    <div class="service-detail">
                                        <span class="service-detail-label">ADDRESS</span>
                                        <span>
                                            <?= escape_html($service["address"] ?: "Not provided") ?>
                                        </span>
                                    </div>

                                    <div class="service-detail">
                                        <span class="service-detail-label">CONTACT</span>
                                        <span>
                                            <?= escape_html($service["contact_number"] ?: "Not provided") ?>
                                        </span>
                                    </div>

                                </div>

                                <div class="service-card-footer">
                                    <div class="service-price">
                                        <span>Service Price</span>
                                        <strong>
                                            ₱<?= number_format((float) $service["price"], 2) ?>
                                        </strong>
                                    </div>

                                    <a
                                        class="service-request-button"
                                        href="request_service.php?id=<?= (int) $service["service_id"] ?>"
                                    >
                                        Request Service
                                        <span aria-hidden="true">&rarr;</span>
                                    </a>
                                </div>

                            </article>

                        <?php endwhile; ?>

                    </div>

                <?php else: ?>

                    <div class="browse-empty-state">
                        <div class="browse-empty-icon">S</div>
                        <h3>No Services Found</h3>
                        <p>
                            No available services match your search.
                            Try another keyword or select a different category.
                        </p>
                        <a href="browse_services.php" class="browse-search-button">
                            View All Services
                        </a>
                    </div>

                <?php endif; ?>

            </section>

            <footer class="browse-footer">
                <p>
                    &copy; <?= date("Y") ?>
                    UniServe — Unified Business and Service Management Application
                </p>
            </footer>

        </main>
    </div>
</div>

</body>
</html>

<?php
$result->free();
$categories_result->free();
$conn->close();
?>
