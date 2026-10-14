<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");


// העמוד מיועד רק לסטייליסט
if (!isset($_SESSION["userid"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] != 2) {
    header("Location: home.php");
    exit();
}


$con = OpenCon();

$stylistid = (int)$_SESSION["userid"];


// קבלת כל הדוחות שנשלחו על ידי הסטייליסט
$reportsResult = mysqli_query(
    $con,
    "SELECT stylingreports.*,
            bookings.bookingdate,
            bookings.bookingtime,
            users.fname,
            users.lname,
            users.email
     FROM stylingreports
     INNER JOIN bookings
     ON stylingreports.bookingid=bookings.bookingid
     INNER JOIN users
     ON stylingreports.userid=users.userid
     WHERE stylingreports.stylistid='$stylistid'
     ORDER BY stylingreports.createdat DESC"
);


require("nav.php");

?>

<!DOCTYPE html>

<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1.0"
>

<title>היסטוריית דוחות - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    direction: rtl;
}


.page {
    max-width: 1200px;
    margin: 35px auto;
    padding: 20px;
}


h1 {
    color: white;
    text-align: center;
}


.subtitle {
    color: #bbb;
    text-align: center;
    margin-bottom: 30px;
}


.report-card {
    margin-bottom: 20px;
    padding: 20px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
}


.report-card h2 {
    margin-top: 0;
    color: white;
}


.customer-info {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-bottom: 20px;
}


.info-item {
    padding: 10px;
    background: black;
    border-radius: 6px;
}


.info-item strong {
    display: block;
    margin-bottom: 5px;
    color: white;
}


.report-section {
    margin-top: 12px;
    padding: 12px;
    background: black;
    border-radius: 6px;
}


.report-section strong {
    display: block;
    margin-bottom: 7px;
    color: white;
}


.empty {
    padding: 30px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
    text-align: center;
}


@media screen and (max-width:750px) {

    .customer-info {
        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<div class="page">


<h1>הדוחות שלי</h1>


<p class="subtitle">
היסטוריית דוחות הסטיילינג שנשלחו ללקוחות.
</p>


<?php

if ( !$reportsResult ||  mysqli_num_rows($reportsResult) == 0) {

?>

<div class="empty">
עדיין לא נשלחו דוחות.
</div>


<?php

} else {


    while ( $report = mysqli_fetch_array($reportsResult) ) {

?>


<div class="report-card">


<h2>
<?php echo $report["reporttitle"]; ?>
</h2>


<div class="customer-info">


<div class="info-item">

<strong>לקוח</strong>

<?php

echo $report["fname"] . " ";
echo $report["lname"];

?>

</div>


<div class="info-item">

<strong>אימייל</strong>

<?php echo $report["email"]; ?>

</div>


<div class="info-item">

<strong>מספר תור</strong>

<?php echo $report["bookingid"]; ?>

</div>


<div class="info-item">

<strong>תאריך הפגישה</strong>

<?php echo $report["bookingdate"]; ?>

</div>


<div class="info-item">

<strong>שעת הפגישה</strong>

<?php echo $report["bookingtime"]; ?>

</div>


<div class="info-item">

<strong>תאריך שליחת הדוח</strong>

<?php echo $report["createdat"]; ?>

</div>


</div>


<div class="report-section">

<strong>צבעים מומלצים</strong>

<?php echo $report["colors"]; ?>

</div>


<div class="report-section">

<strong>מוצרים מומלצים</strong>

<?php echo $report["recommendedproduct"]; ?>

</div>


<div class="report-section">

<strong>הערות והמלצות</strong>

<?php echo $report["notes"]; ?>

</div>


</div>


<?php

    }

}

?>

</div>
</body>
</html>