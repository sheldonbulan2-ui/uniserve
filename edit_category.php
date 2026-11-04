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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_id = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);
}

if (!$category_id || $category_id < 1) {
    header("Location: manage_categories.php");
    exit();
}

$message = "";
$category_name = "";
$description = "";

/* Load the existing category */
$stmt = $conn->prepare(
    "SELECT category_name, description
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

$category_name = $category["category_name"];
$description = $category["description"] ?? "";

/* Update the category */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $category_name = trim($_POST["category_name"] ?? "");
    $description = trim($_POST["description"] ?? "");

    if ($category_name === "") {
        $message = "Please enter a category name.";
    } elseif (strlen($category_name) > 100) {
        $message = "Category name must not exceed 100 characters.";
    } else {
        $check = $conn->prepare(
            "SELECT category_id
             FROM categories
             WHERE category_name = ?
               AND category_id <> ?
             LIMIT 1"
        );

        if (!$check) {
            die("Unable to validate the category.");
        }

        $check->bind_param("si", $category_name, $category_id);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Another category already uses that name.";
            $check->close();
        } else {
            $check->close();

            $update = $conn->prepare(
                "UPDATE categories
                 SET category_name = ?, description = ?
                 WHERE category_id = ?"
            );

            if (!$update) {
                die("Unable to prepare the category update.");
            }

            $description_value = ($description === "") ? null : $description;

            $update->bind_param(
                "ssi",
                $category_name,
                $description_value,
                $category_id
            );

            if ($update->execute()) {
                $update->close();
                $conn->close();

                header("Location: manage_categories.php");
                exit();
            }

            $message = "Unable to update the category.";
            $update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - UniServe</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header>
    <h1>UniServe</h1>
    <p>Edit Service Category</p>
</header>

<nav>
    <a href="admin_dashboard.php">Dashboard</a> |
    <a href="manage_categories.php">Manage Categories</a> |
    <a href="logout.php">Logout</a>
</nav>

<main>
    <h2>Edit Category</h2>

    <?php if ($message !== ""): ?>
        <p><?= escape_html($message) ?></p>
    <?php endif; ?>

    <form
        method="POST"
        action="edit_category.php?id=<?= (int) $category_id ?>"
    >
        <input
            type="hidden"
            name="category_id"
            value="<?= (int) $category_id ?>"
        >

        <p>
            <label for="category_name">Category Name:</label><br>
            <input
                type="text"
                id="category_name"
                name="category_name"
                maxlength="100"
                value="<?= escape_html($category_name) ?>"
                required
            >
        </p>

        <p>
            <label for="description">Description:</label><br>
            <textarea
                id="description"
                name="description"
                rows="4"
                cols="40"
            ><?= escape_html($description) ?></textarea>
        </p>

        <button type="submit">Save Changes</button>
    </form>

    <p><a href="manage_categories.php">Cancel and Go Back</a></p>
</main>

</body>
</html>

<?php
$conn->close();
?>