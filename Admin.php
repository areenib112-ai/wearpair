<?php

require("nav.php");

if (!isset($_SESSION["userid"])) {
    echo "<p>יש להתחבר תחילה.</p>";
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] != 1) {
    echo "<p>רק מנהל יכול להיכנס לעמוד זה.</p>";
    exit();
}

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>לוח ניהול - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    text-align: center;
    direction: rtl;
}

.admin-page {
    max-width: 1000px;
    margin: 50px auto;
    padding: 20px;
}

.admin-page h1 {
    color: white;
    font-family: Georgia, serif;
    font-size: 38px;
}

.admin-page p {
    color: #cccccc;
    font-size: 17px;
    margin-bottom: 35px;
}

.admin-menu {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 15px;
}

.admin-link {
    width: 180px;
    padding: 18px;
    background: #111111;
    color: white;
    border: 1px solid #444444;
    border-radius: 10px;
    text-decoration: none;
    font-weight: bold;
    font-size: 16px;
}

.admin-link:hover {
    background: #222222;
}

@media screen and (max-width: 600px) {

    .admin-link {
        width: 80%;
    }
}

</style>

</head>

<body>

<div class="admin-page">

<h1>לוח ניהול</h1>

<p>
בחר את האזור שברצונך לנהל במערכת WearPair.
</p>

<div class="admin-menu">

<a class="admin-link"
   href="manageusers.php">
משתמשים
</a>

<a class="admin-link"
   href="manageproducts.php">
מוצרים
</a>

<a class="admin-link"
   href="managebookings.php">
פגישות
</a>

<a class="admin-link"
   href="managemessages.php">
הודעות
</a>

<a class="admin-link"
   href="managestatistics.php">
סטטיסטיקות
</a>

<a class="admin-link"
   href="manageorders.php">
הזמנות
</a>

</div>

</div>

</body>

</html>