
<?php
session_start();

// ================================
// DATABASE CONNECTION
// ================================
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
$messageType = "";

// ================================
// ADD PRODUCT TO DATABASE
// ================================
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $productName = trim($_POST["productName"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = filter_input(INPUT_POST, "price", FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, "stock", FILTER_VALIDATE_INT);
    $brand = trim($_POST["brand"] ?? "");
    $description = trim($_POST["description"] ?? "");

    // Validate required fields
    if (
        $productName === "" ||
        $category === "" ||
        $price === false || $price === null || $price < 0 ||
        $stock === false || $stock === null || $stock < 0 ||
        $description === ""
    ) {
        $message = "Please enter valid information in all required fields.";
        $messageType = "error";

    } else {

        // ================================
        // IMAGE UPLOAD
        // ================================
        $imagePath = "";

        if (
            isset($_FILES["productImage"]) &&
            $_FILES["productImage"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            if ($_FILES["productImage"]["error"] !== UPLOAD_ERR_OK) {
                $message = "Image upload failed. Please try again.";
                $messageType = "error";
            } elseif ($_FILES["productImage"]["size"] > 5 * 1024 * 1024) {
                $message = "Image must be smaller than 5 MB.";
                $messageType = "error";
            } else {
                $tmpFile = $_FILES["productImage"]["tmp_name"];

                // Check actual image type, not just the file extension
                $imageInfo = getimagesize($tmpFile);
                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                $mime = $imageInfo["mime"] ?? "";

                if (
                    $imageInfo === false ||
                    !isset($allowedTypes[$mime])
                ) {
                    $message = "Upload a valid JPG, PNG, or WEBP image.";
                    $messageType = "error";
                } else {
                    $uploadDir = __DIR__ . "/uploads/";

                    if (
                        !is_dir($uploadDir) &&
                        !mkdir($uploadDir, 0755, true) &&
                        !is_dir($uploadDir)
                    ) {
                        $message = "Could not create the uploads folder.";
                        $messageType = "error";
                    } else {
                        $fileName = bin2hex(random_bytes(16))
                                  . "." . $allowedTypes[$mime];

                        if (move_uploaded_file(
                            $tmpFile,
                            $uploadDir . $fileName
                        )) {
                            $imagePath = "uploads/" . $fileName;
                        } else {
                            $message = "Could not save the product image.";
                            $messageType = "error";
                        }
                    }
                }
            }
        }

        // ================================
        // INSERT INTO PRODUCTS TABLE
        // ================================
        if ($messageType !== "error") {

            $sql = "INSERT INTO products
                    (product_name, category, price, stock,
                     brand, description, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            if (!$stmt) {
                $message = "SQL error: " . $conn->error;
                $messageType = "error";
            } else {
                $stmt->bind_param(
                    "ssdisss",
                    $productName,
                    $category,
                    $price,
                    $stock,
                    $brand,
                    $description,
                    $imagePath
                );

                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->close();

                    // Prevent duplicate submissions on refresh
                    header("Location: seller.php?added=1");
                    exit;
                } else {
                    $message = "Could not add product: " . $stmt->error;
                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
}

if (isset($_GET["added"]) && $_GET["added"] === "1") {
    $message = "Product added successfully!";
    $messageType = "success";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Seller Dashboard | Al hamd Engeeniers</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #f4f6f8;
            color: #222;
        }

        /* =========================
           HEADER
        ========================== */

        header {
            background: #111;
            padding: 18px 0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.15);
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: auto;
        }

        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            font-size: 14px;
            letter-spacing: 2px;
        }

        .back-btn {
            text-decoration: none;
            background: #f5c400;
            color: #111;
            padding: 11px 20px;
            border-radius: 6px;
            font-weight: bold;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: white;
        }


        /* =========================
           PAGE TITLE
        ========================== */

        .page-title {
            text-align: center;
            padding: 45px 20px 30px;
        }

        .page-title small {
            color: #f0b900;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .page-title h1 {
            font-size: 36px;
            margin: 10px 0;
            color: #111;
        }

        .page-title p {
            color: #666;
            font-size: 16px;
        }


        /* =========================
           SELLER SECTION
        ========================== */

        .seller-section {
            padding-bottom: 60px;
        }

        .seller-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }


        /* =========================
           FORM CARD
        ========================== */

        .form-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        }

        .form-card h2 {
            margin-bottom: 8px;
            color: #111;
        }

        .form-card > p {
            color: #777;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            outline: none;
            font-size: 15px;
            transition: 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #f5c400;
            box-shadow: 0 0 0 2px rgba(245,196,0,0.12);
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }


        /* =========================
           IMAGE UPLOAD
        ========================== */

        .upload-box {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 25px;
            text-align: center;
            background: #fafafa;
            transition: 0.3s;
        }

        .upload-box:hover {
            border-color: #f5c400;
            background: #fffdf2;
        }

        .upload-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .upload-box p {
            color: #777;
            margin-bottom: 12px;
        }

        .upload-box input {
            border: none;
            width: 100%;
        }


        /* =========================
           ADD PRODUCT BUTTON
        ========================== */

        .add-product-btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 6px;
            background: #f5c400;
            color: #111;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }

        .add-product-btn:hover {
            background: #111;
            color: white;
        }


        /* =========================
           PRODUCT PREVIEW
        ========================== */

        .preview-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
        }

        .preview-card h2 {
            margin-bottom: 20px;
        }

        .product-preview {
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
            background: white;
        }

        .preview-image {
            height: 250px;
            background: #f1f1f1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 16px;
        }

        .preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .preview-content {
            padding: 22px;
        }

        .preview-category {
            display: inline-block;
            background: #fff3b0;
            color: #806700;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .preview-content h3 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .preview-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .preview-price {
            font-size: 23px;
            font-weight: bold;
            color: #111;
        }

        .preview-stock {
            margin-top: 8px;
            color: #555;
        }


        /* =========================
           SELLER INFORMATION
        ========================== */

        .seller-info {
            margin-top: 25px;
            padding: 20px;
            background: #111;
            color: white;
            border-radius: 8px;
        }

        .seller-info h3 {
            color: #f5c400;
            margin-bottom: 10px;
        }

        .seller-info p {
            color: #ccc;
            line-height: 1.6;
        }


        /* =========================
           FOOTER
        ========================== */

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
        ========================== */

        @media (max-width: 850px) {

            .seller-wrapper {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 550px) {

            .navbar {
                gap: 15px;
            }

            .logo h2 {
                font-size: 20px;
            }

            .back-btn {
                padding: 9px 12px;
                font-size: 13px;
            }

            .page-title h1 {
                font-size: 28px;
            }

            .two-columns {
                grid-template-columns: 1fr;
            }

            .form-card,
            .preview-card {
                padding: 20px;
            }

        }

    </style>
</head>


<body>


    <!-- =========================
         HEADER
    ========================== -->

    <header>

        <div class="container navbar">

            <a href="index.html" class="logo">

                <h2>Al hamd</h2>
                <span>ENG EENIERS</span>

            </a>

            <a href="index.html" class="back-btn">
                ← Back to Website
            </a>

        </div>

    </header>



    <!-- =========================
         PAGE TITLE
    ========================== -->

    <section class="page-title">

        <small>SELLER DASHBOARD</small>

        <h1>Add Your Product</h1>

        <p>
            Enter your product information and add it to your store.
        </p>

    </section>



    <!-- =========================
         SELLER SECTION
    ========================== -->

    <section class="seller-section">

        <div class="container seller-wrapper">


            <!-- =========================
                 PRODUCT FORM
            ========================== -->

            <div class="form-card">

                <h2>Product Information</h2>

                <p>
                    Fill in the details below to add a new product.
                </p>


              <form action="seller.php" method="POST"
      enctype="multipart/form-data">


                    <!-- PRODUCT NAME -->

                    <div class="form-group">

                        <label for="productName">
                            Product Name
                        </label>

                        <input
                            type="text"
                            id="productName"
                            name="productName"
                            placeholder="e.g. Digital Total Station"
                            required
                        >

                    </div>



                    <!-- CATEGORY -->

                    <div class="form-group">

                        <label for="category">
                            Product Category
                        </label>

                        <select
                            id="category"
                            name="category"
                            required
                        >

                            <option value="">
                                Select Category
                            </option>

                            <option value="total-station">
                                Total Stations
                            </option>

                            <option value="gnss">
                                Shayan
                            </option>

                            <option value="auto-level">
                                Auto Levels
                            </option>

                            <option value="tripods">
                                Tripods & Accessories
                            </option>

                            <option value="other">
                                Other Equipment
                            </option>

                        </select>

                    </div>



                    <!-- PRICE + STOCK -->

                    <div class="two-columns">

                        <div class="form-group">

                            <label for="price">
                                Price
                            </label>

                            <input
                                type="number"
                                id="price"
                                name="price"
                                placeholder="Enter price"
                                min="0"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="stock">
                                Stock Quantity
                            </label>

                            <input
                                type="number"
                                id="stock"
                                name="stock"
                                placeholder="e.g. 10"
                                min="0"
                                required
                            >

                        </div>

                    </div>



                    <!-- BRAND -->

                    <div class="form-group">

                        <label for="brand">
                            Brand
                        </label>

                        <input
                            type="text"
                            id="brand"
                            name="brand"
                            placeholder="e.g. Leica, Trimble, Topcon"
                        >

                    </div>



                    <!-- DESCRIPTION -->

                    <div class="form-group">

                        <label for="description">
                            Product Description
                        </label>

                        <textarea
                            id="description"
                            name="description"
                            placeholder="Describe your product..."
                            required
                        ></textarea>

                    </div>



                    <!-- PRODUCT IMAGE -->

                    <div class="form-group">

                        <label>
                            Product Image
                        </label>

                        <div class="upload-box">

                            <div class="upload-icon">
                                📷
                            </div>

                            <p>
                                Upload your product image
                            </p>

                            <input
                                type="file"
                                id="productImage"
                                name="productImage"
                                accept="image/*"
                            >

                        </div>

                    </div>



                    <!-- ADD BUTTON -->

                 <button type="submit" class="add-product-btn">
                          + Add Product
                 </button>

                </form>

            </div>



            <!-- =========================
                 PRODUCT PREVIEW
            ========================== -->

            <div class="preview-card">

                <h2>Product Preview</h2>


                <div class="product-preview">


                    <div class="preview-image">

                        <span id="imageText">
                            Product Image
                        </span>

                        <img
                            id="previewImg"
                            src=""
                            alt="Product Preview"
                        >

                    </div>


                    <div class="preview-content">

                        <span
                            class="preview-category"
                            id="previewCategory"
                        >
                            Category
                        </span>


                        <h3 id="previewName">
                            Your Product Name
                        </h3>


                        <p
                            class="preview-description"
                            id="previewDescription"
                        >
                            Your product description will appear here.
                        </p>


                        <div
                            class="preview-price"
                            id="previewPrice"
                        >
                            Rs. 0
                        </div>


                        <div
                            class="preview-stock"
                            id="previewStock"
                        >
                            Stock: 0
                        </div>

                    </div>

                </div>



                <!-- SELLER INFO -->

                <div class="seller-info">

                    <h3>
                        Seller Information
                    </h3>

                    <p>
                        Make sure your product information,
                        price, stock quantity and image are
                        accurate before adding the product.
                    </p>

                </div>

            </div>

        </div>

    </section>



    <!-- =========================
         FOOTER
    ========================== -->

    <footer>

        <p>
            © 2026
            <span>Al hamd Engeeniers</span>.
            All rights reserved.
        </p>

    </footer>



    <!-- =========================
         LIVE PREVIEW JAVASCRIPT
    ========================== -->

    <script>

        const productName =
            document.getElementById("productName");

        const category =
            document.getElementById("category");

        const price =
            document.getElementById("price");

        const stock =
            document.getElementById("stock");

        const description =
            document.getElementById("description");

        const productImage =
            document.getElementById("productImage");


        const previewName =
            document.getElementById("previewName");

        const previewCategory =
            document.getElementById("previewCategory");

        const previewPrice =
            document.getElementById("previewPrice");

        const previewStock =
            document.getElementById("previewStock");

        const previewDescription =
            document.getElementById("previewDescription");

        const previewImg =
            document.getElementById("previewImg");

        const imageText =
            document.getElementById("imageText");


        /* PRODUCT NAME */

        productName.addEventListener("input", function() {

            previewName.textContent =
                this.value || "Your Product Name";

        });


        /* CATEGORY */

        category.addEventListener("change", function() {

            previewCategory.textContent =
                this.options[this.selectedIndex].text ||
                "Category";

        });


        /* PRICE */

        price.addEventListener("input", function() {

            previewPrice.textContent =
                "Rs. " + (this.value || "0");

        });


        /* STOCK */

        stock.addEventListener("input", function() {

            previewStock.textContent =
                "Stock: " + (this.value || "0");

        });


        /* DESCRIPTION */

        description.addEventListener("input", function() {

            previewDescription.textContent =
                this.value ||
                "Your product description will appear here.";

        });


        /* IMAGE PREVIEW */

        productImage.addEventListener("change", function() {

            const file = this.files[0];

            if (file) {

                const reader =
                    new FileReader();

                reader.onload = function(event) {

                    previewImg.src =
                        event.target.result;

                    previewImg.style.display =
                        "block";

                    imageText.style.display =
                        "none";

                };

                reader.readAsDataURL(file);

            }

        });

    </script>


</body>
</html>