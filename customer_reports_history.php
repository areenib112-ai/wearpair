<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");


// העמוד מיועד רק ללקוח
if (!isset($_SESSION["userid"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] != 0) {
    header("Location: home.php");
    exit();
}

$con = OpenCon();

$userid = (int)$_SESSION["userid"];
$bookingid = 0;


// קבלת מספר התור אם נכנסו דרך כפתור הדוח של פגישה מסוימת
if (isset($_GET["bookingid"])) {
    $bookingid = (int)$_GET["bookingid"];
}


// אם נבחרה פגישה מסוימת מציגים רק את הדוח שלה
if ($bookingid > 0) {

    $reportsResult = mysqli_query($con,
        "SELECT stylingreports.*,
                bookings.bookingdate,
                bookings.bookingtime,
                users.fname,
                users.lname
         FROM stylingreports
         INNER JOIN bookings
         ON stylingreports.bookingid=bookings.bookingid
         INNER JOIN users
         ON stylingreports.stylistid=users.userid
         WHERE stylingreports.userid='$userid'
         AND stylingreports.bookingid='$bookingid'"
    );

} else {

    // כניסה רגילה לעמוד ההיסטוריה - מציגים את כל הדוחות
    $reportsResult = mysqli_query($con,
        "SELECT stylingreports.*,
                bookings.bookingdate,
                bookings.bookingtime,
                users.fname,
                users.lname
         FROM stylingreports
         INNER JOIN bookings
         ON stylingreports.bookingid=bookings.bookingid
         INNER JOIN users
         ON stylingreports.stylistid=users.userid
         WHERE stylingreports.userid='$userid'
         ORDER BY stylingreports.createdat DESC"
    );
}

require("nav.php");

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>הדוחות שלי - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.page {
    width: 90%;
    max-width: 900px;
    margin: 30px auto;
}

h1 {
    color: white;
    text-align: center;
}

.report {
    margin-bottom: 20px;
    padding: 18px;
    background: #111;
    border: 1px solid #444;
}

.report h2 {
    color: white;
}

.info {
    margin: 8px 0;
}

.info strong {
    color: white;
}

.empty {
    padding: 25px;
    background: #111;
    border: 1px solid #444;
    text-align: center;
}

</style>

</head>

<body>

<div class="page">

<?php
if ($bookingid > 0) {
?>
<h1>דוח סטיילינג</h1>
<?php
} else {
?>
<h1>דוחות הסטיילינג שלי</h1>
<?php
}
?>


<?php
if (  !$reportsResult || mysqli_num_rows($reportsResult) == 0) {
?>

<div class="empty">
<?php
if ($bookingid > 0) {
    echo "עדיין לא נשלח דוח עבור פגישה זו.";
} else {
    echo "עדיין לא התקבלו דוחות סטיילינג.";
}
?>
</div>

<?php

} else {

    while ($report = mysqli_fetch_array($reportsResult)) {

?>

<div class="report">

<h2>
<?php echo $report["reporttitle"]; ?>
</h2>


<div class="info">

<strong>סטייליסט:</strong>

<?php
echo $report["fname"] . " ";
echo $report["lname"];
?>

</div>


<div class="info">

<strong>תאריך הפגישה:</strong>

<?php echo $report["bookingdate"]; ?>

</div>


<div class="info">

<strong>שעת הפגישה:</strong>

<?php echo $report["bookingtime"]; ?>

</div>


<div class="info">

<strong>צבעים מומלצים:</strong>

<?php echo $report["colors"]; ?>

</div>


<div class="info">

<strong>מוצרים מומלצים:</strong>

<?php echo $report["recommendedproduct"]; ?>

</div>


<div class="info">

<strong>הערות והמלצות:</strong>

<?php echo $report["notes"]; ?>

</div>


<div class="info">

<strong>תאריך שליחת הדוח:</strong>

<?php echo $report["createdat"]; ?>

</div>


</div>

<?php

    }
}

?>

</div>

</body>
</html>