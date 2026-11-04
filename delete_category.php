<?php
session_start();
require_once "config.php";

if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: login.php");
    exit();
}

function escape_html($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

$category_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_id = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);

    if (!$category_id || $category_id < 1) {
        header("Location: manage_categories.php");
        exit();
    }

    $stmt = $conn->prepare(
        "DELETE FROM categories WHERE category_id = ?"
    );

    if (!$stmt) {
        die("Unable to prepare the delete request.");
    }

    $stmt->bind_param("i", $category_id);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();

        header("Location: manage_categories.php");
        exit();
    }

    $message = "Unable to delete this category. It may be linked to existing businesses or services.";
    $stmt->close();
} elseif (!$category_id || $category_id < 1) {
    header("Location: manage_categories.php");
    exit();
}

/* Find the category to show the confirmation page */
$stmt = $conn->prepare(
    "SELECT category_id, category_name
     FROM categories
     WHERE category_id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to load the category.");
}

$stmt->bind_param("i", $category_id);
$stmt->execute();

$result = $stmt->get_result();
$category = $result->fetch_assoc();
$stmt->close();

if (!$category) {
    $conn->close();
    exit("Category not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Category - UniServe</title>
</head>
<body>

<header>
    <h1>UniServe</h1>
    <p>Delete Service Category</p>
</header>

<nav>
    <a href="admin_dashboard.php">Dashboard</a> |
    <a href="manage_categories.php">Manage Categories</a> |
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h2>Confirm Category Deletion</h2>

    <?php if ($message !== ""): ?>
        <p><?= escape_html($message) ?></p>
    <?php endif; ?>

    <p>
        Are you sure you want to delete the category
        <strong><?= escape_html($category["category_name"]) ?></strong>?
    </p>

    <p>This action cannot be undone.</p>

    <form method="POST" action="delete_category.php">
        <input
            type="hidden"
            name="category_id"
            value="<?= (int) $category["category_id"] ?>"
        >

        <button type="submit">Yes, Delete Category</button>
    </form>

    <p>
        <a href="manage_categories.php">Cancel</a>
    </p>
</main>

</body>
</html>

<?php
$conn->close();
?>