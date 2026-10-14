<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// התנתקות מהמערכת
if (isset($_POST["logoutBtn"])) {
    session_unset();
    session_destroy();
    header("Location: home.php");
    exit();
}

$role = -1;

// קבלת התפקיד של המשתמש המחובר
if (isset($_SESSION["role"])) {
    $role = $_SESSION["role"];
}

?>

<style>

nav {
    width: 100%;
    padding: 10px 20px;
    position: sticky;
    top: 0;
    z-index: 100;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-sizing: border-box;
    background: rgba(0,0,0,0.08);
    direction: rtl;
}

.nav-left,.nav-right,.nav-center,.nav-links {
    display: flex;
    align-items: center;
}

.nav-left {
    gap: 18px;
}

.nav-links {
    flex-wrap: wrap;
    gap: 20px;
}

.nav-center {
    gap: 10px;
    color: silver;
    font-size: 17px;
    font-weight: bold;
}

.logo-box img {
    width: auto;
    height: 58px;
    border-radius: 4px;
}

.nav-links a {
    padding: 8px 12px;
    border-radius: 20px;
    color: silver;
    font-size: 16px;
    font-weight: bold;
    text-decoration: none;
}

.nav-links a:hover,.nav-btn:hover {
    color: white;
    background: rgba(255,255,255,0.06);
}

.nav-btn {
    padding: 8px 12px;
    margin-right: 10px;
    border: none;
    border-radius: 20px;
    background: transparent;
    color: silver;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}

.cart-img {
    width: 70px;
    height: 70px;
    margin-right: 10px;
    cursor: pointer;
}

@media screen and (max-width:900px) {
    nav {
        flex-direction: column;
        gap: 10px;
    }
    .nav-left {
        flex-direction: column;
        gap: 10px;
    }
    .nav-links {
        justify-content: center;
        gap: 10px;
    }
    .nav-right {
        justify-content: center;
    }
}

</style>

<nav>

<div class="nav-left">

<div class="logo-box">
<img src="logo.png" alt="לוגו WearPair">
</div>

<div class="nav-links">

<?php

// קישורים למשתמש שלא התחבר
if (!isset($_SESSION["userid"])) {
    echo '<a href="home.php">דף הבית</a>';
    echo '<a href="login.php">התחברות</a>';
    echo '<a href="signup.php">הרשמה</a>';
}

// קישורים למשתמש מחובר
if (isset($_SESSION["userid"])) {

    // קישורים של לקוח
    if ($role == 0) {
        echo '<a href="home.php">דף הבית</a>';
        echo '<a href="Profile.php">פרופיל</a>';
        echo '<a href="women.php">נשים</a>';
        echo '<a href="men.php">גברים</a>';
        echo '<a href="bookingstylist.php">קביעת תור עם סטייליסט</a>';
        echo '<a href="customer_reports_history.php">דוחות הסטיילינג שלי</a>';
        echo '<a href="favorites.php">מועדפים</a>';
        echo '<a href="myorders.php">ההזמנות שלי</a>';
        echo '<a href="contact.php">צור קשר</a>';
    }

    // קישורים של מנהל
    if ($role == 1) {
        echo '<a href="manageusers.php">ניהול משתמשים</a>';
        echo '<a href="manageproducts.php">ניהול מוצרים</a>';
        echo '<a href="managebookings.php">ניהול פגישות</a>';
        echo '<a href="managemessages.php">ניהול הודעות</a>';
        echo '<a href="managestatistics.php">סטטיסטיקות</a>';
        echo '<a href="manageorders.php">ניהול הזמנות</a>';
    }

    // קישורים של סטייליסט
    if ($role == 2) {
        echo '<a href="home.php">דף הבית</a>';
        echo '<a href="women.php">נשים</a>';
        echo '<a href="men.php">גברים</a>';
        echo '<a href="stylist_bookings.php">טבלת תורים</a>';
        echo '<a href="stylist_reports_history.php">הדוחות שלי</a>';
        echo '<a href="contact.php">צור קשר</a>';
    }
}

?>

</div>
</div>

<div class="nav-right">

<?php
if (isset($_SESSION["userid"])) {
?>

<div class="nav-center">

<?php

// עגלת קניות מוצגת רק ללקוח
if ($role == 0) {
?>

<a href="cart.php">
<img src="cartpic.png" class="cart-img" alt="עגלת קניות">
</a>

<?php
}
?>

<span>
שלום,
<?php
if (isset($_SESSION["fname"])) {
    echo $_SESSION["fname"];
}
?>
</span>
<form method="post" style="display:inline;margin:0;">
<button type="submit" class="nav-btn" name="logoutBtn">התנתקות</button>
</form>
</div>
<?php
}
?>
</div>
</nav>