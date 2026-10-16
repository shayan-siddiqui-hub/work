
<?php
session_start();

/* DATABASE CONNECTION */
$host = "localhost";
$username = "root";
$password = "";
$database = "al_hamd_engeeniers";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$message = "";
$messageType = "success";

/* ADD NEW PRODUCT */
if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["add_product"])) {

    $name = trim($_POST["product_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = filter_var($_POST["price"] ?? "", FILTER_VALIDATE_FLOAT);
    $stock = filter_var(
        $_POST["stock"] ?? "",
        FILTER_VALIDATE_INT
    );
    $image = trim($_POST["image"] ?? "");

    if (
        $name === "" ||
        $category === "" ||
        $price === false || $price < 0 ||
        $stock === false || $stock < 0
    ) {
        $message = "Please enter valid product information.";
        $messageType = "error";
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO products
            (product_name, category, description, price, stock, image)
            VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "sssd is",
            $name,
            $category,
            $description,
            $price,
            $stock,
            $image
        );

        if ($stmt->execute()) {
            header("Location: seller.php?added=1");
            exit;
        } else {
            $message = "Could not add product. Please check your table columns.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

/* DELETE PRODUCT */
if (isset($_GET["delete"])) {
    $id = filter_var($_GET["delete"], FILTER_VALIDATE_INT);

    if ($id !== false && $id > 0) {
        $stmt = $conn->prepare(
            "DELETE FROM products WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: seller.php");
    exit;
}

if (isset($_GET["added"])) {
    $message = "Product added successfully!";
}

/* DASHBOARD STATISTICS */
$totalProducts = 0;
$totalStock = 0;
$totalOrders = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total, COALESCE(SUM(stock), 0) AS stock_total
     FROM products"
);

if ($result) {
    $stats = $result->fetch_assoc();
    $totalProducts = (int)$stats["total"];
    $totalStock = (int)$stats["stock_total"];
}

/* Orders summary, if the existing orders table is available */
$orderResult = $conn->query("SELECT COUNT(*) AS total FROM orders");

if ($orderResult) {
    $totalOrders = (int)$orderResult->fetch_assoc()["total"];
}

/* FETCH PRODUCTS */
$products = $conn->query(
    "SELECT id, product_name, category, description, price, stock, image
     FROM products ORDER BY id DESC"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Seller Dashboard | Al-Hamd Engeeniers</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --navy: #101d35;
    --yellow: #ffc928;
    --background: #f4f6fa;
    --text: #263247;
    --muted: #748096;
    --white: #ffffff;
}

body {
    font-family: Arial, sans-serif;
    background: var(--background);
    color: var(--text);
}

a {
    text-decoration: none;
    color: inherit;
}

button, input, select, textarea {
    font: inherit;
}

.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 245px;
    background: var(--navy);
    color: white;
    padding: 28px 18px;
    z-index: 5;
}

.brand {
    padding: 0 12px 30px;
    border-bottom: 1px solid #33415a;
}

.brand h2 {
    font-size: 23px;
}

.brand span {
    color: var(--yellow);
}

.brand p {
    color: #bdc6d5;
    font-size: 12px;
    margin-top: 7px;
}

.menu-title {
    color: #91a0b8;
    font-size: 11px;
    letter-spacing: 1.5px;
    margin: 27px 12px 12px;
}

.nav-link {
    display: block;
    color: #dce3ef;
    padding: 14px 13px;
    border-radius: 8px;
    margin-bottom: 7px;
    transition: .2s;
}

.nav-link:hover,
.nav-link.active {
    background: var(--yellow);
    color: var(--navy);
    font-weight: bold;
}

.sidebar-bottom {
    position: absolute;
    bottom: 25px;
    left: 28px;
    right: 20px;
    color: #bdc6d5;
    font-size: 12px;
    line-height: 1.8;
}

.main {
    margin-left: 245px;
    padding: 30px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 30px;
}

.topbar h1 {
    font-size: 27px;
    color: var(--navy);
}

.topbar p {
    color: var(--muted);
    margin-top: 7px;
    font-size: 14px;
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.store-btn {
    background: var(--navy);
    color: white;
    padding: 12px 16px;
    border-radius: 7px;
    font-size: 13px;
}

.avatar {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--yellow);
    color: var(--navy);
    font-weight: bold;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border: 1px solid #e8ebf1;
    border-radius: 12px;
    padding: 23px;
    box-shadow: 0 4px 15px #18264108;
}

.stat-icon {
    font-size: 25px;
    margin-bottom: 18px;
}

.stat-card p {
    color: var(--muted);
    font-size: 13px;
}

.stat-card h2 {
    font-size: 29px;
    margin-top: 8px;
    color: var(--navy);
}

.section {
    background: white;
    border: 1px solid #e8ebf1;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 25px;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 23px;
}

.section-heading h2 {
    font-size: 20px;
    color: var(--navy);
}

.section-heading p {
    font-size: 13px;
    color: var(--muted);
    margin-top: 7px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.field.full {
    grid-column: 1 / -1;
}

.field label {
    font-size: 13px;
    font-weight: bold;
}

.field input,
.field select,
.field textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #dce1ea;
    border-radius: 7px;
    outline: none;
    background: white;
    color: var(--text);
}

.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: #e5ae00;
}

.field textarea {
    min-height: 95px;
    resize: vertical;
}

.primary-btn {
    background: var(--yellow);
    color: var(--navy);
    border: none;
    border-radius: 7px;
    padding: 13px 20px;
    font-weight: bold;
    cursor: pointer;
}

.primary-btn:hover {
    background: #f1b900;
}

.alert {
    margin-bottom: 20px;
    padding: 13px 16px;
    border-radius: 7px;
    background: #e8f8ee;
    color: #17683a;
}

.alert.error {
    background: #ffeded;
    color: #a52222;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 690px;
}

th, td {
    text-align: left;
    padding: 15px 12px;
    border-bottom: 1px solid #edf0f5;
    font-size: 13px;
}

th {
    background: #f8f9fc;
    color: var(--muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 180px;
}

.product-info img {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 7px;
    background: #f1f3f7;
}

.product-info strong {
    color: var(--navy);
}

.category {
    background: #eef2f8;
    border-radius: 5px;
    padding: 6px 8px;
    font-size: 11px;
}

.stock {
    color: #198754;
    font-weight: bold;
}

.stock.low {
    color: #cf7620;
}

.delete-btn {
    color: #c33333;
    font-weight: bold;
}

.empty {
    text-align: center;
    padding: 30px;
    color: var(--muted);
}

.footer {
    text-align: center;
    color: var(--muted);
    font-size: 12px;
    padding: 12px;
}

@media (max-width: 1000px) {
    .sidebar {
        width: 205px;
    }

    .main {
        margin-left: 205px;
        padding: 20px;
    }

    .stats {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 650px) {
    .sidebar {
        position: static;
        width: 100%;
        padding: 18px;
    }

    .brand {
        padding-bottom: 15px;
    }

    .menu-title {
        margin-top: 15px;
    }

    .sidebar-bottom {
        display: none;
    }

    .main {
        margin-left: 0;
        padding: 16px;
    }

    .topbar {
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .topbar h1 {
        font-size: 23px;
    }

    .stats {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .stat-card {
        padding: 18px;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .field.full {
        grid-column: auto;
    }

    .section {
        padding: 17px;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <h2>Al-Hamd <span>Engeeniers</span></h2>
        <p>SELLER CONTROL PANEL</p>
    </div>

    <p class="menu-title">MAIN MENU</p>
    <a class="nav-link active" href="#dashboard">▦ &nbsp; Dashboard</a>
    <a class="nav-link" href="seller.php">＋ &nbsp; Add Product</a>
    <a class="nav-link" href="#my-products">▤ &nbsp; My Products</a>
    <a class="nav-link" href="#orders">▣ &nbsp; Orders Overview</a>

    <p class="menu-title">WEBSITE</p>
    <a class="nav-link" href="index.html">⌂ &nbsp; Visit Website</a>
    <a class="nav-link" href="buyer.php">🛍 &nbsp; Buyer Store</a>

    <div class="sidebar-bottom">
        <strong>Al-Hamd Engeeniers</strong><br>
        Surveying Today for a Better Tomorrow.
    </div>
</aside>

<main class="main" id="dashboard">

    <header class="topbar">
        <div>
            <h1>Seller Dashboard</h1>
            <p>Manage your surveying products and inventory.</p>
        </div>

        <div class="top-actions">
            <a href="buyer.php" class="store-btn">View Store ↗</a>
            <div class="avatar">S</div>
        </div>
    </header>

    <?php if ($message !== ""): ?>
        <div class="alert <?= $messageType === "error" ? "error" : "" ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="stats">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <p>Total Products</p>
            <h2><?= $totalProducts ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <p>Units in Stock</p>
            <h2><?= $totalStock ?></h2>
        </div>

        <div class="stat-card">
            <div class="stat-icon">🛒</div>
            <p>Total Orders</p>
            <h2><?= $totalOrders ?></h2>
        </div>
    </section>

    <!-- ADD PRODUCT FORM -->
    <section class="section" id="add-product">
        <div class="section-heading">
            <div>
                <h2>Add New Product</h2>
                <p>Enter your product information below.</p>
            </div>
        </div>

        <form method="POST" action="seller.php#add-product">
            <div class="form-grid">

                <div class="field">
                    <label for="product_name">Product Name *</label>
                    <input type="text" id="product_name"
                           name="product_name" required
                           maxlength="255"
                           placeholder="e.g. Digital Total Station">
                </div>

                <div class="field">
                    <label for="category">Category *</label>
                    <select id="category" name="category" required>
                        <option value="">Select category</option>
                        <option>Total Stations</option>
                        <option>GNSS Receivers</option>
                        <option>Auto Levels</option>
                        <option>Tripods & Accessories</option>
                        <option>Laser Levels</option>
                        <option>Other Survey Equipment</option>
                    </select>
                </div>

                <div class="field">
                    <label for="price">Price (Rs.) *</label>
                    <input type="number" id="price" name="price"
                           min="0" step="0.01" required
                           placeholder="Enter price">
                </div>

                <div class="field">
                    <label for="stock">Available Stock *</label>
                    <input type="number" id="stock" name="stock"
                           min="0" step="1" required
                           placeholder="Enter quantity">
                </div>

                <div class="field full">
                    <label for="image">Product Image URL or Path</label>
                    <input type="text" id="image" name="image"
                           maxlength="255"
                           placeholder="images/total-station.jpg">
                </div>

                <div class="field full">
                    <label for="description">Product Description</label>
                    <textarea id="description" name="description"
                              placeholder="Describe the product, its features and specifications..."></textarea>
                </div>

                <div class="field full">
                    <div>
                        <button type="submit" name="add_product"
                                class="primary-btn"  action = "seller.php">
                            + Add Product
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </section>

    <!-- PRODUCT INVENTORY -->
    <section class="section" id="my-products">
        <div class="section-heading">
            <div>
                <h2>My Products</h2>
                <p>Products saved in your existing database.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($products && $products->num_rows > 0): ?>
                    <?php while ($product = $products->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <?php
                                    $imagePath = trim($product["image"] ?? "");
                                    $validImage = preg_match(
                                        '/^(https?:\/\/|images\/)/i',
                                        $imagePath
                                    );
                                    ?>
                                    <?php if ($validImage && $imagePath !== ""): ?>
                                        <img
                                            src="<?= htmlspecialchars($imagePath) ?>"
                                            alt="Product"
                                            onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <img
                                            src="https://placehold.co/80x80?text=Product"
                                            alt="Product">
                                    <?php endif; ?>

                                    <strong>
                                        <?= htmlspecialchars($product["product_name"]) ?>
                                    </strong>
                                </div>
                            </td>

                            <td>
                                <span class="category">
                                    <?= htmlspecialchars($product["category"]) ?>
                                </span>
                            </td>

                            <td>
                                Rs. <?= number_format((float)$product["price"], 2) ?>
                            </td>

                            <td class="<?= (int)$product["stock"] < 5 ? 'stock low' : 'stock' ?>">
                                <?= (int)$product["stock"] ?>
                            </td>

                            <td>
                                <a class="delete-btn"
                                   href="seller.php?delete=<?= (int)$product["id"] ?>"
                                   onclick="return confirm('Delete this product? This action cannot be undone.')">
                                   Delete
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="empty">
                            No products yet. Add your first product above.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ORDERS OVERVIEW -->
    <section class="section" id="orders">
        <div class="section-heading">
            <div>
                <h2>Orders Overview</h2>
                <p>Check the orders placed by buyers.</p>
            </div>
            <a href="order-history.php" class="store-btn">
                Order History ↗
            </a>
        </div>

        <p style="color:#748096;font-size:14px;line-height:1.8;">
            Total orders in the database:
            <strong><?= $totalOrders ?></strong>
            <br>
            Use your order history page to view individual orders
            and their status.
        </p>
    </section>

    <footer class="footer">
        © 2026 Al-Hamd Engeeniers. All rights reserved.
    </footer>

</main>

</body>
</html>
