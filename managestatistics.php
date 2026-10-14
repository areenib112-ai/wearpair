<?php

require("nav.php");
require("dbconnection.php");

if (!isset($_SESSION["userid"])) {
    echo "<p>יש להתחבר תחילה.</p>";
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] != 1) {
    echo "<p>רק מנהל יכול להיכנס לעמוד זה.</p>";
    exit();
}

$conn = OpenCon();

if (!$conn) {
    echo "<p>החיבור למסד הנתונים נכשל.</p>";
    exit();
}

date_default_timezone_set("Asia/Jerusalem");
$today = date("Y-m-d");

// פגישה שנקבעה והתאריך שלה כבר עבר משתנה אוטומטית לפגישה שהסתיימה
mysqli_query($conn,"
    UPDATE bookings
    SET status='done'
    WHERE status='booked'
    AND bookingdate<'$today'
");

// מחזירה את מספר השורות שהשאילתה מצאה
function getRowsCount($conn,$sql)
{
    $result = mysqli_query($conn,$sql);

    if (!$result) {
        return 0;
    }

    return mysqli_num_rows($result);
}

// מספר הלקוחות במערכת
$customers = getRowsCount($conn,"
    SELECT *
    FROM users
    WHERE role=0
");

// מספר הסטייליסטים במערכת
$stylists = getRowsCount($conn,"
    SELECT *
    FROM users
    WHERE role=2
");

// מספר המשתמשים החסומים
$blockedUsers = getRowsCount($conn,"
    SELECT *
    FROM users
    WHERE blocked!=0
");

// השנה הנוכחית מוצגת כברירת מחדל
$currentYear = date("Y");
$selectedYear = $currentYear;

if (isset($_GET["year"])) {
    $selectedYear = $_GET["year"];
}

// מספר כל ההזמנות בשנה שנבחרה
$yearOrders = getRowsCount($conn,"
    SELECT *
    FROM orders
    WHERE YEAR(orderdate)='$selectedYear'
");

// חישוב סכום המכירות בשנה שנבחרה ללא הזמנות שבוטלו
$yearRevenue = 0;

$revenueResult = mysqli_query($conn,"
    SELECT totalprice
    FROM orders
    WHERE YEAR(orderdate)='$selectedYear'
    AND deliverystatus!='cancelled'
");

if ($revenueResult) {
    while ($revenueRow = mysqli_fetch_array($revenueResult)) {
        $yearRevenue = $yearRevenue + $revenueRow["totalprice"];
    }
}

// מספר כל הפגישות עם סטייליסט
$totalBookings = getRowsCount($conn,"
    SELECT *
    FROM bookings
");

// מספר הפגישות שנקבעו
$bookedBookings = getRowsCount($conn,"
    SELECT *
    FROM bookings
    WHERE status='booked'
");

// מספר הפגישות שבוטלו
$cancelledBookings = getRowsCount($conn,"
    SELECT *
    FROM bookings
    WHERE status='cancelled'
");

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>סטטיסטיקות - WearPair</title>

<style>
body {
    margin:0;
    background:#151515;
    color:silver;
    font-family:Arial,sans-serif;
    direction:rtl;
}

.statistics-page {
    max-width:1100px;
    margin:25px auto;
    padding:10px;
}

.statistics-page h1 {
    color:white;
    text-align:center;
    margin-bottom:25px;
}

.statistics-section {
    margin-bottom:35px;
}

.statistics-section h2 {
    color:white;
    border-bottom:1px solid #555;
    padding-bottom:8px;
    text-align:center;
}

.statistics-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:15px;
}

.sales-grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:15px;
    max-width:750px;
    margin:auto;
}

.stat-card {
    background:#111;
    border:1px solid #444;
    border-radius:10px;
    padding:20px 12px;
    text-align:center;
}

.stat-card h3 {
    color:silver;
    font-size:15px;
    margin:0 0 12px;
}

.stat-card p {
    color:white;
    font-size:28px;
    font-weight:bold;
    margin:0;
}

.year-box {
    max-width:450px;
    margin:20px auto 30px;
    padding:18px;
    background:#111;
    border:1px solid #444;
    border-radius:10px;
    text-align:center;
}

.year-box label {
    color:white;
    font-weight:bold;
    margin-left:10px;
}

.year-box select {
    padding:9px;
    background:black;
    color:white;
    border:1px solid #555;
    border-radius:6px;
}

.year-box button {
    padding:9px 16px;
    margin-right:8px;
    background:white;
    color:black;
    border:0;
    border-radius:6px;
    font-weight:bold;
    cursor:pointer;
}

@media screen and (max-width:850px) {
    .statistics-grid {
        grid-template-columns:repeat(2,1fr);
    }
}

@media screen and (max-width:500px) {
    .statistics-grid,
    .sales-grid {
        grid-template-columns:1fr;
    }
}
</style>
</head>

<body>
<div class="statistics-page">

<h1>סטטיסטיקות המערכת</h1>

<div class="statistics-section">
<h2>מכירות לפי שנה</h2>

<div class="year-box">
<form method="get">

<label>שנה</label>

<select name="year">
<?php
for ($year = $currentYear; $year >= 2026; $year--) {
?>
<option value="<?php echo $year; ?>"
<?php
if ($selectedYear == $year) {
    echo "selected";
}
?>
>
<?php echo $year; ?>
</option>
<?php
}
?>
</select>

<button type="submit">הצג</button>

</form>
</div>

<h2>
נתוני מכירות לשנת
<?php echo $selectedYear; ?>
</h2>

<div class="sales-grid">

<div class="stat-card">
<h3>סה״כ הזמנות</h3>
<p><?php echo $yearOrders; ?></p>
</div>

<div class="stat-card">
<h3>סה״כ מכירות</h3>
<p><?php echo $yearRevenue; ?> ₪</p>
</div>

</div>
</div>

<div class="statistics-section">
<h2>משתמשים</h2>

<div class="statistics-grid">

<div class="stat-card">
<h3>לקוחות</h3>
<p><?php echo $customers; ?></p>
</div>

<div class="stat-card">
<h3>סטייליסטים</h3>
<p><?php echo $stylists; ?></p>
</div>

<div class="stat-card">
<h3>משתמשים חסומים</h3>
<p><?php echo $blockedUsers; ?></p>
</div>

</div>
</div>

<div class="statistics-section">
<h2>פגישות</h2>

<div class="statistics-grid">

<div class="stat-card">
<h3>סה״כ פגישות</h3>
<p><?php echo $totalBookings; ?></p>
</div>

<div class="stat-card">
<h3>פגישות שנקבעו</h3>
<p><?php echo $bookedBookings; ?></p>
</div>

<div class="stat-card">
<h3>פגישות שבוטלו</h3>
<p><?php echo $cancelledBookings; ?></p>
</div>

</div>
</div>

</div>
</body>
</html>