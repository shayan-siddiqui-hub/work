<?php

// ===============================
// DATABASE CONNECTION
// ===============================

$host = "localhost";
$dbname = "al_hamd_engeeniers";
$username = "root";
$dbpassword = "";

// Create database connection
$conn = new mysqli(
    $host,
    $username,
    $dbpassword,
    $dbname
);

// Check database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


// ===============================
// PROCESS SIGN UP FORM
// ===============================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ===============================
    // GET FORM DATA
    // ===============================

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $terms = isset($_POST["terms"]);


    // ===============================
    // VALIDATION
    // ===============================

    // Check email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die("Please enter a valid email address.");
    }


    // Check password length
    if (strlen($password) < 8) {
        die("Password must be at least 8 characters long.");
    }


    // Check Terms & Conditions
    if (!$terms) {
        die("Please accept the Terms & Conditions and Privacy Policy.");
    }


    // ===============================
    // HASH PASSWORD
    // ===============================

    // Convert the plain-text password
    // into a secure password hash.
    $hashedPassword = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    // ===============================
    // CHECK WHETHER EMAIL ALREADY EXISTS
    // ===============================

    // ❌ ERROR IN YOUR ORIGINAL CODE:
    //
    // You used:
    // SELECT * FROM users
    // WHERE email = ? AND password = ?
    //
    // This is incorrect for signup.
    //
    // Also, you cannot compare a password_hash()
    // result directly with the database password,
    // because password_hash() creates a different
    // secure hash each time.
    //
    // ✅ We only check the email here.

    $checkStmt = $conn->prepare(
        "SELECT id FROM users WHERE email = ?"
    );

    // Check whether prepare() failed
    if (!$checkStmt) {
        die("Prepare failed: " . $conn->error);
    }

    $checkStmt->bind_param(
        "s",
        $email
    );

    $checkStmt->execute();

    $result = $checkStmt->get_result();


    // If email already exists
    if ($result->num_rows > 0) {

        $checkStmt->close();
        $conn->close();

        die("An account with this email already exists.");
    }

    $checkStmt->close();


    // ===============================
    // INSERT NEW USER
    // ===============================

    // ❌ ERROR IN YOUR ORIGINAL CODE:
    //
    // You used SELECT here.
    //
    // A signup form needs INSERT because
    // we are creating a new user.
    //
    // ✅ Correct SQL:

    $stmt = $conn->prepare(
        "INSERT INTO users (email, password)
         VALUES (?, ?)"
    );


    // Check whether prepare() failed
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }


    // Bind email and hashed password
    //
    // "ss" means:
    // s = email (string)
    // s = password (string)

    $stmt->bind_param(
        "ss",
        $email,
        $hashedPassword
    );


    // ===============================
    // EXECUTE INSERT
    // ===============================

    if ($stmt->execute()) {

        // ===============================
        // SUCCESS
        // ===============================

        $stmt->close();
        $conn->close();

        // Redirect after successful signup
        header("Location: buyer.php");
        exit();

    } else {

        // ===============================
        // ERROR
        // ===============================

        echo "Something went wrong. Please try again.";
    }


    // ===============================
    // CLOSE CONNECTION
    // ===============================

    // Only close if the code reached here
    // without already closing it.
    if ($stmt) {
        $stmt->close();
    }

    $conn->close();
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login | Al hamd Engeeniers</title>

    <style>

        /* =========================
           GLOBAL STYLES
        ========================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f8fb;
            color: #102d4d;
            min-height: 100vh;
        }

        a {
            text-decoration: none;
            color: inherit;
        }


        /* =========================
           HEADER
        ========================== */

        header {
            width: 100%;
            background: white;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: auto;
        }

        .navbar {
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }


        /* =========================
           LOGO
        ========================== */

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            background: #123f68;
            clip-path: polygon(50% 0%, 100% 100%, 0% 100%);
            position: relative;
        }

        .logo-icon::after {
            content: "";
            position: absolute;
            width: 12px;
            height: 30px;
            background: #fbc52d;
            transform: rotate(38deg);
            left: 17px;
            top: 10px;
        }

        .logo-text h2 {
            font-size: 22px;
            color: #103b62;
            line-height: 1;
        }

        .logo-text span {
            font-size: 13px;
            letter-spacing: 3px;
            color: #555;
        }


        /* =========================
           BACK HOME BUTTON
        ========================== */

        .home-btn {
            background: #102d4d;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            transition: 0.3s;
        }

        .home-btn:hover {
            background: #fbc52d;
            color: #102d4d;
            transform: translateY(-2px);
        }


        /* =========================
           SIGN UP SECTION
        ========================== */

        .signup-section {
            min-height: calc(100vh - 75px);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 50px 20px;
        }


        /* =========================
           SIGN UP CARD
        ========================== */

        .signup-card {
            width: 100%;
            max-width: 500px;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.10);
        }

        .signup-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .signup-header h1 {
            color: #102d4d;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .signup-header p {
            color: #68798a;
            font-size: 14px;
        }

        .yellow-line {
            width: 40px;
            height: 3px;
            background: #fbc52d;
            margin: 12px auto 0;
        }


        /* =========================
           FORM
        ========================== */

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 7px;
            color: #103b62;
            font-size: 14px;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #d5dce2;
            border-radius: 6px;
            font-size: 15px;
            outline: none;
            transition: 0.3s;
            color: #102d4d;
        }

        .form-group input:focus {
            border-color: #fbc52d;
            box-shadow: 0 0 0 3px rgba(251, 197, 45, 0.15);
        }


        /* =========================
           TERMS
        ========================== */

        .terms {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin: 20px 0;
            font-size: 13px;
            color: #657689;
        }

        .terms input {
            margin-top: 3px;
        }

        .terms a {
            color: #0961a3;
            font-weight: bold;
        }

        .terms a:hover {
            color: #f0b900;
        }


        /* =========================
           SIGN UP BUTTON
        ========================== */

        .signup-submit {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 6px;
            background: #fbc52d;
            color: #102d4d;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        .signup-submit:hover {
            background: #102d4d;
            color: white;
            transform: translateY(-2px);
        }


        /* =========================
           LOGIN LINK
        ========================== */

        .login-link {
            text-align: center;
            margin-top: 22px;
            color: #657689;
            font-size: 14px;
        }

        .login-link a {
            color: #0961a3;
            font-weight: bold;
        }

        .login-link a:hover {
            color: #f0b900;
        }


        /* =========================
           FOOTER
        ========================== */

        footer {
            background: #061e33;
            color: white;
            text-align: center;
            padding: 18px;
            font-size: 13px;
        }

        footer span {
            color: #fbc52d;
        }


        /* =========================
           RESPONSIVE DESIGN
        ========================== */

        @media (max-width: 600px) {

            .navbar {
                height: 70px;
            }

            .logo-text h2 {
                font-size: 18px;
            }

            .logo-text span {
                font-size: 10px;
            }

            .logo-icon {
                width: 38px;
                height: 38px;
            }

            .home-btn {
                padding: 8px 14px;
                font-size: 13px;
            }

            .signup-section {
                padding: 30px 15px;
            }

            .signup-card {
                padding: 30px 22px;
            }

            .signup-header h1 {
                font-size: 28px;
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

                <div class="logo-icon"></div>

                <div class="logo-text">
                    <h2>Al hamd</h2>
                    <span>Engeeniers</span>
                </div>

            </a>


            <a href="index.html" class="home-btn">
                ← Back to Home
            </a>

        </div>

    </header>



    <!-- =========================
         SIGN UP SECTION
    ========================== -->

    <section class="signup-section">

        <div class="signup-card">


            <div class="signup-header">

                <h1>Login to your Account</h1>

                <p>
                     Al hamd Engeeniers 
                </p>

                <div class="yellow-line"></div>

            </div>



            <!-- SIGN UP FORM -->

           <form action="login.php" method="POST">


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="example@email.com"
                        required
                    >

                </div>


                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Create a password"
                        required
                    >

                </div>


                <!-- TERMS -->

                <div class="terms">

                    <input
                        type="checkbox"
                        id="terms"
                        name="terms"
                        required
                    >

                    <label for="terms">
                        I agree to the
                        <a href="#">
                            Terms & Conditions
                        </a>
                        and
                        <a href="#">
                            Privacy Policy
                        </a>.
                    </label>

                </div>



                <!-- SUBMIT BUTTON -->

                <button
                    type="submit"
                    class="signup-submit"
                >
                    Login Now
                </button>


            </form>



            <!-- LOGIN -->

            <div class="login-link">

                Don't have an account?

                <a href="index.html">
                    HOME
                </a>

            </div>


        </div>

    </section>



    <!-- =========================
         FOOTER
    ========================== -->

    <footer>

        © 2026
        <span>Al hamd Engeeniers</span>.
        All rights reserved.

    </footer>


</body>

</html>

