<?php
// =====================================================
// DATABASE CONNECTION
// =====================================================

$host = "localhost";
$dbname = "al_hamd_engeeniers";
$username = "root";
$dbpassword = "";

$conn = new mysqli($host, $username, $dbpassword, $dbname);

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");


// =====================================================
// CREATE ORDERS TABLE IF IT DOES NOT EXIST
// =====================================================

$ordersTable = "
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_name VARCHAR(150) NOT NULL,
    buyer_phone VARCHAR(50) NOT NULL,
    buyer_email VARCHAR(150) NOT NULL,
    buyer_address TEXT NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
";

$conn->query($ordersTable);


// =====================================================
// CREATE ORDER ITEMS TABLE
// =====================================================

$orderItemsTable = "
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    subtotal DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB
";

$conn->query($orderItemsTable);


// =====================================================
// PLACE ORDER
// =====================================================

$orderMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["place_order"])) {

    $buyerName = trim($_POST["buyerName"] ?? "");
    $buyerPhone = trim($_POST["buyerPhone"] ?? "");
    $buyerEmail = trim($_POST["buyerEmail"] ?? "");
    $buyerAddress = trim($_POST["buyerAddress"] ?? "");
    $cartData = $_POST["cartData"] ?? "";

    if (
        $buyerName === "" ||
        $buyerPhone === "" ||
        $buyerEmail === "" ||
        $buyerAddress === "" ||
        $cartData === ""
    ) {
        $orderMessage = "Please complete all delivery information.";
    } elseif (!filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
        $orderMessage = "Please enter a valid email address.";
    } else {

        $cart = json_decode($cartData, true);

        if (!is_array($cart) || count($cart) === 0) {

            $orderMessage = "Your cart is empty.";

        } else {

            $totalAmount = 0;
            $validItems = [];

            // ---------------------------------------------
            // Get real prices from database
            // ---------------------------------------------

            foreach ($cart as $item) {

                $productId = intval($item["id"] ?? 0);
                $quantity = intval($item["quantity"] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    continue;
                }

                $stmt = $conn->prepare(
                    "SELECT id, product_name, price, stock
                     FROM products
                     WHERE id = ?"
                );

                $stmt->bind_param("i", $productId);
                $stmt->execute();

                $result = $stmt->get_result();

                if ($product = $result->fetch_assoc()) {

                    if ($product["stock"] < $quantity) {
                        $orderMessage =
                            "Not enough stock available for " .
                            $product["product_name"] . ".";
                        break;
                    }

                    $price = floatval($product["price"]);
                    $subtotal = $price * $quantity;

                    $totalAmount += $subtotal;

                    $validItems[] = [
                        "id" => intval($product["id"]),
                        "name" => $product["product_name"],
                        "quantity" => $quantity,
                        "price" => $price,
                        "subtotal" => $subtotal
                    ];
                }

                $stmt->close();
            }


            // ---------------------------------------------
            // Save order
            // ---------------------------------------------

            if ($orderMessage === "" && count($validItems) > 0) {

                $conn->begin_transaction();

                try {

                    $stmt = $conn->prepare(
                        "INSERT INTO orders
                        (buyer_name, buyer_phone, buyer_email,
                         buyer_address, total_amount)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    $stmt->bind_param(
                        "ssssd",
                        $buyerName,
                        $buyerPhone,
                        $buyerEmail,
                        $buyerAddress,
                        $totalAmount
                    );

                    $stmt->execute();

                    $orderId = $conn->insert_id;

                    $stmt->close();


                    // -----------------------------------------
                    // Insert order items
                    // -----------------------------------------

                    foreach ($validItems as $item) {

                        $stmt = $conn->prepare(
                            "INSERT INTO order_items
                            (order_id, product_id, product_name,
                             quantity, price, subtotal)
                            VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        $stmt->bind_param(
                            "iisidd",
                            $orderId,
                            $item["id"],
                            $item["name"],
                            $item["quantity"],
                            $item["price"],
                            $item["subtotal"]
                        );

                        $stmt->execute();

                        $stmt->close();


                        // -----------------------------------------
                        // Reduce product stock
                        // -----------------------------------------

                        $stmt = $conn->prepare(
                            "UPDATE products
                             SET stock = stock - ?
                             WHERE id = ?"
                        );

                        $stmt->bind_param(
                            "ii",
                            $item["quantity"],
                            $item["id"]
                        );

                        $stmt->execute();

                        $stmt->close();
                    }


                    $conn->commit();

                    $orderMessage =
                        "Order placed successfully! Your Order ID is #" .
                        $orderId;

                } catch (Exception $e) {

                    $conn->rollback();

                    $orderMessage =
                        "Order could not be placed. Please try again.";
                }
            }
        }
    }
}


// =====================================================
// GET PRODUCTS FROM DATABASE
// =====================================================

$products = [];

$sql = "SELECT * FROM products ORDER BY id DESC";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}


// =====================================================
// GET CATEGORIES FROM DATABASE
// =====================================================

$categories = [];

$categoryResult = $conn->query(
    "SELECT DISTINCT category
     FROM products
     WHERE category IS NOT NULL
     AND category != ''
     ORDER BY category ASC"
);

if ($categoryResult) {

    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row["category"];
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Buy Products | Al Hamd Engeeniers</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: #f5f6f8;
    color: #222;
}

.container {
    width: 90%;
    max-width: 1250px;
    margin: auto;
}


/* =========================
   HEADER
========================= */

header {
    background: #111;
    padding: 17px 0;
    box-shadow: 0 3px 12px rgba(0,0,0,.15);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.logo {
    text-decoration: none;
    color: white;
}

.logo h2 {
    font-size: 25px;
    letter-spacing: 1px;
}

.logo span {
    color: #f5c400;
    font-size: 13px;
    letter-spacing: 2px;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 15px;
}

.back-btn {
    text-decoration: none;
    background: #f5c400;
    color: #111;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: bold;
}

.back-btn:hover {
    background: white;
}

.cart-button {
    border: none;
    background: white;
    color: #111;
    padding: 10px 16px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
    position: relative;
}

.cart-count {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #f5c400;
    color: #111;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
}


/* =========================
   PAGE TITLE
========================= */

.page-title {
    text-align: center;
    padding: 50px 20px 30px;
}

.page-title small {
    color: #d1a900;
    font-weight: bold;
    letter-spacing: 2px;
}

.page-title h1 {
    font-size: 38px;
    margin: 10px 0;
}

.page-title p {
    color: #777;
}


/* =========================
   SEARCH
========================= */

.shop-tools {
    background: white;
    padding: 20px;
    border-radius: 10px;
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(0,0,0,.06);
}

.search-box {
    flex: 1;
}

.search-box input,
.category-filter select {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    outline: none;
}

.category-filter {
    width: 220px;
}


/* =========================
   PRODUCTS
========================= */

.products-section {
    padding-bottom: 70px;
}

.product-grid {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 25px;
}

.product-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 5px 25px rgba(0,0,0,.07);
    transition: .3s;
}

.product-card:hover {
    transform: translateY(-5px);
}

.product-image {
    width: 100%;
    height: 220px;
    background: #eee;
    overflow: hidden;
    position: relative;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.category-tag {
    position: absolute;
    top: 12px;
    left: 12px;
    background: #f5c400;
    color: #111;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    z-index: 2;
}

.product-content {
    padding: 20px;
}

.product-content h3 {
    font-size: 21px;
    margin-bottom: 8px;
}

.product-description {
    color: #777;
    line-height: 1.5;
    font-size: 14px;
    min-height: 45px;
}

.product-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 18px;
}

.price {
    font-size: 21px;
    font-weight: bold;
}

.stock {
    font-size: 12px;
    color: #4d8b35;
    margin-top: 4px;
}

.out-stock {
    color: #d33;
}

.buy-btn {
    border: none;
    background: #111;
    color: white;
    padding: 11px 16px;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}

.buy-btn:hover {
    background: #f5c400;
    color: #111;
}

.buy-btn:disabled {
    background: #aaa;
    cursor: not-allowed;
}


/* =========================
   CART
========================= */

.cart-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,.55);
    z-index: 2000;
}

.cart-panel {
    position: absolute;
    right: 0;
    top: 0;
    width: 450px;
    max-width: 95%;
    height: 100%;
    background: white;
    padding: 25px;
    overflow-y: auto;
}

.cart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 18px;
    border-bottom: 1px solid #eee;
}

.close-cart {
    border: none;
    background: #eee;
    width: 35px;
    height: 35px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
}

.cart-items {
    padding: 20px 0;
}

.cart-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.cart-item-image {
    width: 70px;
    height: 70px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
}

.cart-item-info {
    flex: 1;
}

.cart-item h4 {
    margin-bottom: 5px;
}

.cart-item p {
    font-size: 13px;
    color: #777;
}

.quantity-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantity-controls button {
    width: 28px;
    height: 28px;
    border: none;
    background: #111;
    color: white;
    border-radius: 4px;
    cursor: pointer;
}

.remove-btn {
    border: none;
    background: none;
    color: #d33;
    cursor: pointer;
    font-size: 12px;
    margin-top: 5px;
}

.cart-total {
    border-top: 2px solid #111;
    padding-top: 18px;
    margin-top: 10px;
}

.total-row {
    display: flex;
    justify-content: space-between;
    font-size: 20px;
    font-weight: bold;
}

.checkout-btn {
    width: 100%;
    margin-top: 20px;
    padding: 15px;
    border: none;
    border-radius: 6px;
    background: #f5c400;
    color: #111;
    font-weight: bold;
    cursor: pointer;
}


/* =========================
   CHECKOUT
========================= */

.checkout-box {
    display: none;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #ddd;
}

.checkout-box h3 {
    margin-bottom: 15px;
}

.checkout-group {
    margin-bottom: 14px;
}

.checkout-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 6px;
}

.checkout-group input,
.checkout-group textarea {
    width: 100%;
    padding: 11px;
    border: 1px solid #ddd;
    border-radius: 5px;
    outline: none;
}

.checkout-group textarea {
    height: 80px;
    resize: vertical;
}

.place-order-btn {
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 6px;
    background: #111;
    color: white;
    font-weight: bold;
    cursor: pointer;
}

.place-order-btn:hover {
    background: #f5c400;
    color: #111;
}


/* =========================
   MESSAGE
========================= */

.message {
    width: 90%;
    max-width: 1250px;
    margin: 20px auto;
    padding: 15px;
    background: #fff3cd;
    border-left: 5px solid #f5c400;
    border-radius: 6px;
    font-weight: bold;
}


/* =========================
   EMPTY PRODUCTS
========================= */

.no-products {
    grid-column: 1 / -1;
    background: white;
    padding: 60px;
    text-align: center;
    border-radius: 10px;
    color: #777;
}


/* =========================
   FOOTER
========================= */

footer {
    background: #111;
    color: white;
    text-align: center;
    padding: 25px 15px;
}

footer span {
    color: #f5c400;
}


/* =========================
   RESPONSIVE
========================= */

@media(max-width:950px) {

    .product-grid {
        grid-template-columns: repeat(2,1fr);
    }

}

@media(max-width:650px) {

    .navbar {
        flex-direction: column;
    }

    .shop-tools {
        flex-direction: column;
    }

    .category-filter {
        width: 100%;
    }

    .product-grid {
        grid-template-columns: 1fr;
    }

    .page-title h1 {
        font-size: 29px;
    }

}

</style>

</head>

<body>


<!-- =========================
     HEADER
========================= -->

<header>

<div class="container navbar">

<a href="index.html" class="logo">

<h2>Al hamd</h2>

<span>ENG EENIERS</span>

</a>

<div class="nav-right">

<a href="index.html" class="back-btn">
← Home
</a>

<button class="cart-button" onclick="openCart()">

🛒 Cart

<span class="cart-count" id="cartCount">
0
</span>

</button>

</div>

</div>

</header>


<?php if ($orderMessage !== "") { ?>

<div class="message">

<?php echo htmlspecialchars($orderMessage); ?>

</div>

<?php } ?>


<!-- =========================
     PAGE TITLE
========================= -->

<section class="page-title">

<small>OUR STORE</small>

<h1>Surveying Products</h1>

<p>
Choose the equipment you need
for your next project.
</p>

</section>


<!-- =========================
     PRODUCTS
========================= -->

<section class="products-section">

<div class="container">


<div class="shop-tools">

<div class="search-box">

<input
type="text"
id="searchInput"
placeholder="Search products..."
onkeyup="searchProducts()"
>

</div>


<div class="category-filter">

<select
id="categoryFilter"
onchange="filterProducts()"
>

<option value="all">
All Categories
</option>

<?php foreach ($categories as $category) { ?>

<option value="<?php echo htmlspecialchars(strtolower($category)); ?>">

<?php echo htmlspecialchars($category); ?>

</option>

<?php } ?>

</select>

</div>

</div>


<!-- PRODUCT GRID -->

<div class="product-grid" id="productGrid">


<?php if (count($products) === 0) { ?>

<div class="no-products">

<h2>No products available</h2>

<p>
Products added by sellers will appear here.
</p>

</div>

<?php } ?>


<?php foreach ($products as $product) { ?>


<?php

$id = intval($product["id"]);

$productName = $product["product_name"] ?? "Unnamed Product";

$category = $product["category"] ?? "Other";

$description = $product["description"] ?? "No description available.";

$price = floatval($product["price"] ?? 0);

$stock = intval($product["stock"] ?? 0);

$image = trim($product["image"] ?? "");


// Image path
if ($image !== "") {

    // If database contains uploads/filename
    if (strpos($image, "uploads/") === 0) {
        $imageUrl = $image;
    } else {
        $imageUrl = "uploads/" . basename($image);
    }

} else {

    // Default image
    $imageUrl = "messi.webp";
}


?>

<div
class="product-card"

data-name="<?php echo htmlspecialchars(strtolower($productName)); ?>"

data-category="<?php echo htmlspecialchars(strtolower($category)); ?>"
>


<div class="product-image">


<span class="category-tag">

<?php echo htmlspecialchars($category); ?>

</span>


<img

src="<?php echo htmlspecialchars($imageUrl); ?>"

alt="<?php echo htmlspecialchars($productName); ?>"

onerror="this.onerror=null; this.src='messi.webp';"

>


</div>


<div class="product-content">


<h3>

<?php echo htmlspecialchars($productName); ?>

</h3>


<p class="product-description">

<?php echo htmlspecialchars($description); ?>

</p>


<div class="product-bottom">


<div>

<div class="price">

Rs.
<?php echo number_format($price); ?>

</div>


<?php if ($stock > 0) { ?>

<div class="stock">

<?php echo $stock; ?> In Stock

</div>

<?php } else { ?>

<div class="stock out-stock">

Out of Stock

</div>

<?php } ?>

</div>


<?php if ($stock > 0) { ?>

<button
class="buy-btn"

onclick='addToCart(
<?php echo $id; ?>,
<?php echo json_encode($productName); ?>,
<?php echo $price; ?>,
<?php echo json_encode($imageUrl); ?>
)'
>

Add to Cart

</button>

<?php } else { ?>

<button
class="buy-btn"
disabled
>

Out of Stock

</button>

<?php } ?>


</div>

</div>

</div>

<?php } ?>


</div>

</div>

</section>


<!-- =========================
     CART
========================= -->

<div
class="cart-overlay"
id="cartOverlay"
>

<div class="cart-panel">


<div class="cart-header">

<h2>
Shopping Cart
</h2>

<button
class="close-cart"
onclick="closeCart()"
>

×
</button>

</div>


<div
class="cart-items"
id="cartItems"
>

<div class="empty-cart">

🛒

<p>
Your cart is empty.
</p>

</div>

</div>


<div
class="cart-total"
id="cartTotalSection"
style="display:none;"
>


<div class="total-row">

<span>
Total
</span>

<span id="cartTotal">
Rs. 0
</span>

</div>


<button
class="checkout-btn"
onclick="showCheckout()"
>

Proceed to Checkout

</button>


<!-- =========================
     CHECKOUT FORM
========================= -->

<div
class="checkout-box"
id="checkoutBox"
>

<h3>
Delivery Information
</h3>


<form method="POST" id="orderForm">


<div class="checkout-group">

<label>
Full Name
</label>

<input
type="text"
name="buyerName"
id="buyerName"
placeholder="Enter your name"
required
>

</div>


<div class="checkout-group">

<label>
Phone Number
</label>

<input
type="tel"
name="buyerPhone"
id="buyerPhone"
placeholder="+92 300 1234567"
required
>

</div>


<div class="checkout-group">

<label>
Email
</label>

<input
type="email"
name="buyerEmail"
id="buyerEmail"
placeholder="example@email.com"
required
>

</div>


<div class="checkout-group">

<label>
Delivery Address
</label>

<textarea
name="buyerAddress"
id="buyerAddress"
placeholder="Enter your complete address"
required
></textarea>

</div>


<input
type="hidden"
name="cartData"
id="cartData"
>


<input
type="hidden"
name="place_order"
value="1"
>


<button
type="submit"
class="place-order-btn"
onclick="return prepareOrder()"
>

Place Order

</button>

</form>

</div>

</div>

</div>

</div>


<!-- =========================
     FOOTER
========================= -->

<footer>

<p>

© 2026

<span>
Al hamd Engeeniers
</span>

. All rights reserved.

</p>

</footer>


<script>

// =====================================================
// CART
// =====================================================

let cart = [];


// Load saved cart
try {

    const savedCart = localStorage.getItem("alHamdCart");

    if (savedCart) {
        cart = JSON.parse(savedCart);
    }

} catch (error) {

    cart = [];

}


// =====================================================
// SAVE CART
// =====================================================

function saveCart() {

    localStorage.setItem(
        "alHamdCart",
        JSON.stringify(cart)
    );

}


// =====================================================
// ADD TO CART
// =====================================================

function addToCart(id, name, price, image) {

    const existingProduct = cart.find(
        item => item.id == id
    );


    if (existingProduct) {

        existingProduct.quantity++;

    } else {

        cart.push({

            id: id,

            name: name,

            price: Number(price),

            image: image,

            quantity: 1

        });

    }


    saveCart();

    updateCart();

    alert(name + " has been added to your cart.");

}


// =====================================================
// UPDATE CART
// =====================================================

function updateCart() {

    const cartItems =
        document.getElementById("cartItems");

    const cartCount =
        document.getElementById("cartCount");

    const cartTotal =
        document.getElementById("cartTotal");

    const totalSection =
        document.getElementById("cartTotalSection");


    let totalItems = 0;

    let totalPrice = 0;


    cart.forEach(item => {

        totalItems += Number(item.quantity);

        totalPrice +=
            Number(item.price) *
            Number(item.quantity);

    });


    cartCount.textContent = totalItems;


    if (cart.length === 0) {

        cartItems.innerHTML = `

            <div class="empty-cart">

                🛒

                <p>
                    Your cart is empty.
                </p>

            </div>

        `;

        totalSection.style.display = "none";

        return;
    }


    totalSection.style.display = "block";


    cartItems.innerHTML = "";


    cart.forEach((item, index) => {

        const image =
            item.image || "messi.webp";


        cartItems.innerHTML += `

            <div class="cart-item">

                <img
                    class="cart-item-image"
                    src="${escapeHtml(image)}"
                    alt="${escapeHtml(item.name)}"
                    onerror="this.onerror=null; this.src='messi.webp';"
                >

                <div class="cart-item-info">

                    <h4>
                        ${escapeHtml(item.name)}
                    </h4>

                    <p>
                        Rs.
                        ${Number(item.price).toLocaleString()}
                    </p>

                    <button
                        class="remove-btn"
                        onclick="removeItem(${index})"
                    >
                        Remove
                    </button>

                </div>


                <div class="quantity-controls">

                    <button
                        onclick="changeQuantity(${index}, -1)"
                    >
                        -
                    </button>

                    <strong>
                        ${item.quantity}
                    </strong>

                    <button
                        onclick="changeQuantity(${index}, 1)"
                    >
                        +
                    </button>

                </div>

            </div>

        `;

    });


    cartTotal.textContent =
        "Rs. " +
        totalPrice.toLocaleString();

}


// =====================================================
// ESCAPE HTML
// =====================================================

function escapeHtml(value) {

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

}


// =====================================================
// CHANGE QUANTITY
// =====================================================

function changeQuantity(index, amount) {

    cart[index].quantity += amount;


    if (cart[index].quantity <= 0) {

        cart.splice(index, 1);

    }


    saveCart();

    updateCart();

}


// =====================================================
// REMOVE ITEM
// =====================================================

function removeItem(index) {

    cart.splice(index, 1);

    saveCart();

    updateCart();

}


// =====================================================
// OPEN CART
// =====================================================

function openCart() {

    document
        .getElementById("cartOverlay")
        .style.display = "block";

}


// =====================================================
// CLOSE CART
// =====================================================

function closeCart() {

    document
        .getElementById("cartOverlay")
        .style.display = "none";

}


// =====================================================
// CHECKOUT
// =====================================================

function showCheckout() {

    if (cart.length === 0) {

        alert("Your cart is empty.");

        return;
    }


    document
        .getElementById("checkoutBox")
        .style.display = "block";

}


// =====================================================
// PREPARE ORDER
// =====================================================

function prepareOrder() {

    if (cart.length === 0) {

        alert("Your cart is empty.");

        return false;
    }


    document.getElementById("cartData").value =
        JSON.stringify(cart);


    return true;

}


// =====================================================
// SEARCH PRODUCTS
// =====================================================

function searchProducts() {

    const search =
        document
            .getElementById("searchInput")
            .value
            .toLowerCase();


    const products =
        document.querySelectorAll(".product-card");


    products.forEach(product => {

        const name =
            product.dataset.name.toLowerCase();


        if (name.includes(search)) {

            product.style.display = "block";

        } else {

            product.style.display = "none";

        }

    });

}


// =====================================================
// CATEGORY FILTER
// =====================================================

function filterProducts() {

    const selectedCategory =
        document
            .getElementById("categoryFilter")
            .value
            .toLowerCase();


    const products =
        document.querySelectorAll(".product-card");


    products.forEach(product => {

        const category =
            product.dataset.category.toLowerCase();


        if (
            selectedCategory === "all" ||
            category === selectedCategory
        ) {

            product.style.display = "block";

        } else {

            product.style.display = "none";

        }

    });

}


// =====================================================
// INITIAL CART LOAD
// =====================================================

updateCart();

</script>

</body>
</html>

<?php

$conn->close();

?>