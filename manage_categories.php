<?php
/**
 * UniServe Enterprise Admin — Taxonomy & Category Curation
 */
$pageTitle = "Category & Taxonomy Management";
$pageSubtitle = "Curate marketplace classifications and merchant business categories";
$activeNav = "categories";

require_once __DIR__ . '/includes/admin_header.php';

$message = "";
$messageType = "info";

// Handle Add / Edit / Delete Actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "create") {
        $catName = trim($_POST["category_name"] ?? "");
        $catDesc = trim($_POST["description"] ?? "");

        if ($catName === "") {
            $message = "Category name is required.";
            $messageType = "error";
        } else {
            $check = $conn->prepare("SELECT category_id FROM categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
            $check->bind_param("s", $catName);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = "A category with this name already exists.";
                $messageType = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $catName, $catDesc);
                if ($stmt->execute()) {
                    $message = "Category '{$catName}' created successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to create category: " . $stmt->error;
                    $messageType = "error";
                }
                $stmt->close();
            }
            $check->close();
        }
    } elseif ($action === "update") {
        $catId = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);
        $catName = trim($_POST["category_name"] ?? "");
        $catDesc = trim($_POST["description"] ?? "");

        if (!$catId || $catName === "") {
            $message = "Valid ID and Category name are required.";
            $messageType = "error";
        } else {
            $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
            $stmt->bind_param("ssi", $catName, $catDesc, $catId);
            if ($stmt->execute()) {
                $message = "Category updated successfully.";
                $messageType = "success";
            } else {
                $message = "Failed to update category: " . $stmt->error;
                $messageType = "error";
            }
            $stmt->close();
        }
    } elseif ($action === "delete") {
        $catId = filter_input(INPUT_POST, "category_id", FILTER_VALIDATE_INT);
        if ($catId) {
            $bCheck = $conn->prepare("SELECT COUNT(*) AS c FROM businesses WHERE category_id = ?");
            $bCheck->bind_param("i", $catId);
            $bCheck->execute();
            $bCount = (int)($bCheck->get_result()->fetch_assoc()['c'] ?? 0);
            $bCheck->close();

            if ($bCount > 0) {
                $message = "Cannot delete: {$bCount} business(es) are currently assigned to this category.";
                $messageType = "error";
            } else {
                $del = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
                $del->bind_param("i", $catId);
                if ($del->execute()) {
                    $message = "Category removed successfully.";
                    $messageType = "success";
                } else {
                    $message = "Failed to delete category: " . $del->error;
                    $messageType = "error";
                }
                $del->close();
            }
        }
    }
}

$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        c.category_id,
        c.category_name,
        c.description,
        c.created_at,
        COUNT(b.business_id) AS business_count
    FROM categories c
    LEFT JOIN businesses b ON c.category_id = b.category_id
";

$params = [];
$types = "";

if ($search !== "") {
    $sql .= " WHERE c.category_name LIKE ? OR c.description LIKE ? ";
    $kw = "%" . $search . "%";
    $params = [$kw, $kw];
    $types = "ss";
}

$sql .= " GROUP BY c.category_id, c.category_name, c.description, c.created_at ORDER BY c.category_id ASC";

$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!-- FLASH ALERT -->
<?php if ($message): ?>
    <div class="admin-alert alert-<?= $messageType ?>">
        <span><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- TOOLBAR -->
<div class="admin-toolbar">
    <form method="GET" action="manage_categories.php" class="toolbar-search-form">
        <div class="admin-input-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" class="admin-input admin-input-with-icon" 
                   placeholder="Search taxonomy categories..." 
                   value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-primary" style="padding: 10px 16px;">Search</button>
        <?php if ($search !== ''): ?>
            <a href="manage_categories.php" class="btn-secondary" style="padding: 10px 14px;" title="Reset search">✕</a>
        <?php endif; ?>
    </form>

    <button type="button" class="btn-primary" onclick="openAddCategoryModal()" style="padding: 10px 18px;">
        <span>+ Add Category</span>
    </button>
</div>

<!-- CATEGORIES TABLE -->
<div class="admin-panel">
    <div class="admin-panel-header">
        <div>
            <h3>Marketplace Category Taxonomy</h3>
            <p>Classifications used by customers to filter services and merchants on the Android APK.</p>
        </div>
    </div>

    <?php if (!$result || $result->num_rows === 0): ?>
        <div class="admin-empty-state">
            <div class="empty-icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
            </div>
            <h4>No categories found</h4>
            <p><?= $search ? 'No categories matched your search term.' : 'Click "+ Add Category" above to establish taxonomy.' ?></p>
        </div>
    <?php else: ?>
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Category Name</th>
                        <th>Description / Scope</th>
                        <th>Linked Stores</th>
                        <th>Created At</th>
                        <th style="text-align: right; width: 140px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($c = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code style="color: var(--accent); font-weight: 700;">#CAT-<?= (int)$c["category_id"] ?></code></td>
                            <td>
                                <strong style="color: #ffffff; font-size: 14px;">
                                    <?= htmlspecialchars($c["category_name"], ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </td>
                            <td>
                                <span style="font-size: 12.5px; color: var(--text-muted);">
                                    <?= htmlspecialchars($c["description"] ?? 'No description provided', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="admin-badge" style="background: rgba(59, 130, 246, 0.12); color: #93c5fd; border: 1px solid rgba(59, 130, 246, 0.3);">
                                    <?= (int)$c["business_count"] ?> Stores
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-muted);"><?= date("M d, Y", strtotime($c["created_at"])) ?></span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-btn-group">
                                    <button type="button" class="btn-icon-action" title="Edit Category"
                                            onclick="openEditCategoryModal(<?= (int)$c['category_id'] ?>, '<?= htmlspecialchars(addslashes($c['category_name']), ENT_QUOTES, 'UTF-8') ?>', '<?= htmlspecialchars(addslashes($c['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>')">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>

                                    <form method="POST" action="manage_categories.php" style="display: inline;" 
                                          onsubmit="return confirm('Delete category <?= htmlspecialchars(addslashes($c['category_name']), ENT_QUOTES, 'UTF-8') ?>?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="category_id" value="<?= (int)$c['category_id'] ?>">
                                        <button type="submit" class="btn-icon-action btn-icon-delete" title="Delete Category">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ADD CATEGORY MODAL -->
<div id="addCategoryModal" class="admin-modal-backdrop">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>Add New Category</h3>
            <button type="button" class="modal-close-btn" onclick="closeAddCategoryModal()">&times;</button>
        </div>
        <form method="POST" action="manage_categories.php">
            <input type="hidden" name="action" value="create">
            <div class="admin-modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="admin-form-group">
                    <label for="new_category_name">Category Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="new_category_name" name="category_name" class="admin-input" placeholder="e.g. Health & Wellness" required>
                </div>
                <div class="admin-form-group">
                    <label for="new_description">Description</label>
                    <textarea id="new_description" name="description" class="admin-textarea" rows="3" placeholder="Brief summary of businesses in this category..."></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddCategoryModal()">Cancel</button>
                <button type="submit" class="btn-primary">Create Category</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT CATEGORY MODAL -->
<div id="editCategoryModal" class="admin-modal-backdrop">
    <div class="admin-modal">
        <div class="admin-modal-header">
            <h3>Edit Category</h3>
            <button type="button" class="modal-close-btn" onclick="closeEditCategoryModal()">&times;</button>
        </div>
        <form method="POST" action="manage_categories.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit_category_id" name="category_id" value="">
            <div class="admin-modal-body" style="display: flex; flex-direction: column; gap: 16px;">
                <div class="admin-form-group">
                    <label for="edit_category_name">Category Name <span style="color: #ef4444;">*</span></label>
                    <input type="text" id="edit_category_name" name="category_name" class="admin-input" required>
                </div>
                <div class="admin-form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="admin-textarea" rows="3"></textarea>
                </div>
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditCategoryModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.add('active');
}
function closeAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.remove('active');
}
function openEditCategoryModal(id, name, desc) {
    document.getElementById('edit_category_id').value = id;
    document.getElementById('edit_category_name').value = name;
    document.getElementById('edit_description').value = desc;
    document.getElementById('editCategoryModal').classList.add('active');
}
function closeEditCategoryModal() {
    document.getElementById('editCategoryModal').classList.remove('active');
}
</script>

<?php 
if (isset($stmt)) { $stmt->close(); }
require_once __DIR__ . '/includes/admin_footer.php'; 
?>
