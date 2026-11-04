<?php
session_start();
require_once "config.php";

function escape_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Require customer login
if (
    !isset($_SESSION["user_id"], $_SESSION["role"]) ||
    $_SESSION["role"] !== "customer"
) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$full_name = $_SESSION["full_name"] ?? "Customer";
$initial = strtoupper(substr($full_name, 0, 1));

$service_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$error = "";

if (!$service_id || $service_id < 1) {
    header("Location: browse_services.php");
    exit();
}

// Load available service from an approved business
$sql = "SELECT
            s.service_id,
            s.service_name,
            s.description,
            s.price,
            b.business_name
        FROM services s
        INNER JOIN businesses b
            ON s.business_id = b.business_id
        WHERE s.service_id = ?
          AND s.availability = 'available'
          AND b.status = 'approved'
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $conn->close();
    http_response_code(500);
    exit("Unable to load the service. Please try again later.");
}

$stmt->bind_param("i", $service_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    http_response_code(500);
    exit("Unable to load the service. Please try again later.");
}

$service = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$service) {
    $conn->close();
    header("Location: browse_services.php");
    exit();
}

$preferred_date = "";
$quantity = "1";
$notes = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $preferred_date = trim($_POST["preferred_date"] ?? "");
    $quantity_input = trim($_POST["quantity"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    $date_object = DateTime::createFromFormat("!Y-m-d", $preferred_date);
    $date_errors = DateTime::getLastErrors();

    $valid_date = $date_object !== false
        && $date_object->format("Y-m-d") === $preferred_date
        && (
            $date_errors === false ||
            (
                $date_errors["warning_count"] === 0 &&
                $date_errors["error_count"] === 0
            )
        );

    if (!$valid_date || $preferred_date < date("Y-m-d")) {
        $error = "Please select a valid date that is today or in the future.";
    } elseif (
        !ctype_digit($quantity_input) ||
        (int) $quantity_input < 1 ||
        (int) $quantity_input > 100
    ) {
        $error = "Quantity must be a whole number between 1 and 100.";
    } elseif (strlen($notes) > 2000) {
        $error = "Notes must not exceed 2000 characters.";
    } else {
        $quantity = (int) $quantity_input;
        $unit_price = (float) $service["price"];
        $total_amount = round($unit_price * $quantity, 2);

        $request_status = "pending";
        $payment_status = "pending";
        $payment_method = "Not specified";

        $conn->begin_transaction();

        try {
            $insert_request_sql = "INSERT INTO service_requests
                (
                    customer_id,
                    service_id,
                    preferred_date,
                    quantity,
                    total_amount,
                    status,
                    notes
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $request_stmt = $conn->prepare($insert_request_sql);

            if (!$request_stmt) {
                throw new Exception("Unable to prepare service request.");
            }

            $request_stmt->bind_param(
                "iisidss",
                $user_id,
                $service_id,
                $preferred_date,
                $quantity,
                $total_amount,
                $request_status,
                $notes
            );

            if (!$request_stmt->execute()) {
                $request_stmt->close();
                throw new Exception("Unable to save service request.");
            }

            $request_id = (int) $conn->insert_id;
            $request_stmt->close();

            $insert_transaction_sql = "INSERT INTO transactions
                (
                    request_id,
                    amount,
                    payment_method,
                    payment_status
                )
                VALUES (?, ?, ?, ?)";

            $transaction_stmt = $conn->prepare($insert_transaction_sql);

            if (!$transaction_stmt) {
                throw new Exception("Unable to prepare transaction record.");
            }

            $transaction_stmt->bind_param(
                "idss",
                $request_id,
                $total_amount,
                $payment_method,
                $payment_status
            );

            if (!$transaction_stmt->execute()) {
                $transaction_stmt->close();
                throw new Exception("Unable to save transaction record.");
            }

            $transaction_stmt->close();

            $conn->commit();
            $conn->close();

            header("Location: my_requests.php?message=submitted");
            exit();

        } catch (Throwable $exception) {
            $conn->rollback();

            error_log(
                "UniServe request/transaction error: " .
                $exception->getMessage()
            );

            $error = "Unable to submit your request right now. Please try again.";
        }
    }
}

$conn->close();

$unit_price = (float) $service["price"];
$total_preview = $unit_price * max(1, min(100, (int) $quantity));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Service | UniServe</title>

    <link rel="stylesheet" href="assets/css/request_service.css">
</head>

<body class="request-service-page">

<div class="layout">

    <main class="main-content">

        <header class="topbar">
            <div>
                <h1>Request Service</h1>
                <p>Submit your preferred service details</p>
            </div>

            <div class="topbar-user">
                <div class="user-avatar"><?= escape_html($initial) ?></div>
                <span><?= escape_html($full_name) ?></span>
            </div>
        </header>

        <div class="request-content">

            <a href="browse_services.php" class="request-back-link">
                <span>←</span>
                <span>Back to Services</span>
            </a>

            <div class="request-heading">
                <p class="eyebrow">SERVICE BOOKING</p>
                <h2>Request a Service</h2>
                <p>
                    Review the service details and provide the information
                    needed to submit your request.
                </p>
            </div>

            <?php if ($error !== ""): ?>
                <div class="request-message error" role="alert">
                    <span class="message-symbol">!</span>
                    <span><?= escape_html($error) ?></span>
                </div>
            <?php endif; ?>

            <div class="request-grid">

                <section class="service-summary-card">
                    <div class="service-card-top">
                        <div class="service-symbol">◇</div>
                        <span class="availability-badge">
                            Available
                        </span>
                    </div>

                    <p class="service-overline">SELECTED SERVICE</p>

                    <h3><?= escape_html($service["service_name"]) ?></h3>

                    <div class="business-detail">
                        <span class="detail-label">Provided by</span>
                        <strong>
                            <?= escape_html($service["business_name"]) ?>
                        </strong>
                    </div>

                    <div class="service-divider"></div>

                    <div class="service-description">
                        <h4>About this service</h4>
                        <p><?= nl2br(escape_html(
                            $service["description"] ?: "No description provided."
                        )) ?></p>
                    </div>

                    <div class="service-price">
                        <span>Price per unit</span>
                        <strong>
                            ₱<?= number_format($unit_price, 2) ?>
                        </strong>
                    </div>

                    <div class="summary-note">
                        <span class="summary-note-icon">i</span>
                        <p>
                            Your request will be reviewed by the service
                            provider. Submission does not mean it has
                            already been approved.
                        </p>
                    </div>
                </section>

                <section class="request-form-card">

                    <div class="form-card-heading">
                        <div>
                            <h3>Request Details</h3>
                            <p>Complete the fields below.</p>
                        </div>

                        <span class="required-label">* Required</span>
                    </div>

                    <form
                        method="POST"
                        action="request_service.php?id=<?= (int) $service["service_id"] ?>"
                    >

                        <div class="request-field">
                            <label for="preferred_date">
                                Preferred Service Date
                                <span>*</span>
                            </label>

                            <input
                                type="date"
                                id="preferred_date"
                                name="preferred_date"
                                min="<?= date("Y-m-d") ?>"
                                value="<?= escape_html($preferred_date) ?>"
                                required
                            >

                            <small>
                                Choose today or a future date.
                            </small>
                        </div>

                        <div class="request-field">
                            <label for="quantity">
                                Quantity
                                <span>*</span>
                            </label>

                            <input
                                type="number"
                                id="quantity"
                                name="quantity"
                                min="1"
                                max="100"
                                step="1"
                                value="<?= escape_html($quantity) ?>"
                                required
                            >

                            <small>
                                Enter a quantity between 1 and 100.
                            </small>
                        </div>

                        <div class="request-field">
                            <label for="notes">
                                Additional Notes
                                <span class="optional-label">Optional</span>
                            </label>

                            <textarea
                                id="notes"
                                name="notes"
                                rows="5"
                                maxlength="2000"
                                placeholder="Share any special instructions or details..."
                            ><?= escape_html($notes) ?></textarea>

                            <div class="textarea-footer">
                                <small>
                                    Include any important service requirements.
                                </small>
                                <span id="notes-counter">0 / 2000</span>
                            </div>
                        </div>

                        <div class="request-total">
                            <div>
                                <span>Estimated Total</span>
                                <small>Price per unit × quantity</small>
                            </div>

                            <strong id="estimated-total">
                                ₱<?= number_format($total_preview, 2) ?>
                            </strong>
                        </div>

                        <p class="total-disclaimer">
                            This is an estimate based on the selected quantity.
                        </p>

                        <button class="request-submit" type="submit">
                            <span>Submit Request</span>
                            <span class="submit-arrow">→</span>
                        </button>

                        <a
                            class="request-cancel"
                            href="browse_services.php"
                        >
                            Cancel and Return to Services
                        </a>

                    </form>
                </section>

            </div>

        </div>

    </main>
</div>

<script>
(function () {
    const quantityInput = document.getElementById("quantity");
    const notesInput = document.getElementById("notes");
    const notesCounter = document.getElementById("notes-counter");
    const totalDisplay = document.getElementById("estimated-total");

    const unitPrice = <?= json_encode($unit_price) ?>;

    function updateTotal() {
        const rawValue = quantityInput.value;
        const quantity = /^\d+$/.test(rawValue)
            ? Math.max(0, Math.min(100, Number(rawValue)))
            : 0;

        const total = unitPrice * quantity;

        totalDisplay.textContent = total.toLocaleString("en-PH", {
            style: "currency",
            currency: "PHP",
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateNotesCounter() {
        notesCounter.textContent =
            notesInput.value.length.toLocaleString() + " / 2000";
    }

    quantityInput.addEventListener("input", updateTotal);
    notesInput.addEventListener("input", updateNotesCounter);

    updateTotal();
    updateNotesCounter();
})();
</script>

</body>
</html>
