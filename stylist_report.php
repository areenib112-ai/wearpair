<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");
require("functions.php");


// בדיקה שהמשתמש הוא סטייליסט
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

$bookingid = 0;
$msg = "";

$booking = false;
$measurements = false;
$report = false;


// קבלת מספר התור
if (isset($_GET["bookingid"])) {
    $bookingid = (int)$_GET["bookingid"];
}

if (isset($_POST["bookingid"])) {
    $bookingid = (int)$_POST["bookingid"];
}


// קבלת פרטי התור והלקוח
if ($bookingid > 0) {

    $result = mysqli_query(
        $con,
        "SELECT bookings.*,
                users.fname,
                users.lname,
                users.email,
                users.body_type
         FROM bookings
         INNER JOIN users
         ON bookings.userid=users.userid
         WHERE bookings.bookingid='$bookingid'
         AND bookings.stylistid='$stylistid'
         AND bookings.status!='cancelled'"
    );

    if (  $result && mysqli_num_rows($result) > 0 ) {
        $booking = mysqli_fetch_array($result);
    }
}


// קבלת המדידות וההעדפות של הלקוח
if ($booking) {

    $userid = $booking["userid"];

    $result = mysqli_query(
        $con,
        "SELECT *
         FROM booking_measurements
         WHERE bookingid='$bookingid'
         AND userid='$userid'
         AND stylistid='$stylistid'"
    );

    if ( $result && mysqli_num_rows($result) > 0 ) {
        $measurements = mysqli_fetch_array($result);
    }


    // קבלת הדוח של הפגישה אם כבר נשלח
    $result = mysqli_query(
        $con,
        "SELECT *
         FROM stylingreports
         WHERE bookingid='$bookingid'
         AND stylistid='$stylistid'"
    );

    if ( $result && mysqli_num_rows($result) > 0 ) {

        $report = mysqli_fetch_array($result);
    }
}


// שמירת ושליחת הדוח
if (  $booking &&!$report &&   isset($_POST["sendReport"])) {

    $reportTitle = $_POST["reporttitle"];
    $colors = $_POST["colors"];
    $notes = $_POST["notes"];

    $recommendedProducts = "";


    // חיבור המוצרים שנבחרו לטקסט אחד
    if (isset($_POST["recommendedproducts"])) {

        foreach ($_POST["recommendedproducts"] as $product) {

            if ($recommendedProducts != "") {
                $recommendedProducts .= ", ";
            }

            $recommendedProducts .= $product;
        }
    }


    // בדיקה שכל שדות הדוח מלאים
    if ( $reportTitle == "" || $colors == "" ||
        $notes == "" ||  $recommendedProducts == ""
    ) {
        $msg = "יש למלא את כל שדות הדוח ולבחור מוצר.";

    } else {

        $userid = $booking["userid"];


        // שמירת הדוח במסד הנתונים
        $save = mysqli_query(
            $con,
            "INSERT INTO stylingreports
            (
                bookingid,
                userid,
                stylistid,
                colors,
                notes,
                createdat,
                reporttitle,
                recommendedproduct
            )
            VALUES
            (
                '$bookingid',
                '$userid',
                '$stylistid',
                '$colors',
                '$notes',
                NOW(),
                '$reportTitle',
                '$recommendedProducts'
            )"
        );


        if ($save) {

            $message =  "<h2>שלום " . $booking["fname"] . "</h2>";
            $message .= "<p>הסטייליסט שלך שלח לך דוח אישי.</p>";
            $message .= "<p><b>כותרת:</b> " . $reportTitle . "</p>";
            $message .= "<p><b>צבעים מומלצים:</b> " .  $colors .  "</p>";
            $message .=  "<p><b>מוצרים מומלצים:</b> " . $recommendedProducts . "</p>";
            $message .=  "<p><b>הערות:</b> " .  $notes .  "</p>";

            sendWearPairMail(  $booking["email"],"WearPair - דוח סטיילינג",  $message );

            $msg = "הדוח נשמר ונשלח ללקוח.";

            // קבלת הדוח שנשמר כדי להציג את הדוח של הפגישה הזאת
            $result = mysqli_query(
                $con,
                "SELECT *
                 FROM stylingreports
                 WHERE bookingid='$bookingid'
                 AND stylistid='$stylistid'"
            );

            if (  $result &&  mysqli_num_rows($result) > 0) {

                $report = mysqli_fetch_array($result);
            }

        } else {

            $msg = "אירעה שגיאה בשמירת הדוח.";
        }
    }
}


// שם מבנה הגוף להצגה
$bodyTypeText = "לא צוין";

if ($booking) {

    if ($booking["body_type"] == "hourglass") {
        $bodyTypeText = "שעון חול";
    } elseif ($booking["body_type"] == "pear") {
        $bodyTypeText = "אגס";
    } elseif ($booking["body_type"] == "rectangle") {
        $bodyTypeText = "מלבן";
    } elseif ($booking["body_type"] == "inverted_triangle") {
        $bodyTypeText = "משולש הפוך";
    } elseif ($booking["body_type"] == "apple") {
        $bodyTypeText = "תפוח";
    } elseif ($booking["body_type"] == "triangle") {
        $bodyTypeText = "משולש";
    } elseif ($booking["body_type"] == "oval") {
        $bodyTypeText = "אליפסה";
    }
}


// קבלת המוצרים לבחירה בדוח
$productsResult = mysqli_query(
    $con,
    "SELECT productid,
            pname,
            gender,
            price
     FROM products
     WHERE amount>0
     ORDER BY gender,pname"
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

<title>יצירת דוח - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
}


.page {
    width: 90%;
    max-width: 1100px;
    margin: 30px auto;
}


h1,
h2 {
    color: white;
    text-align: center;
}


h2 {
    margin-top: 35px;
}


table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}


th,
td {
    padding: 9px;
    border: 1px solid #444;
    text-align: center;
}


th {
    background: black;
    color: white;
}


label {
    display: block;
    margin-top: 15px;
    margin-bottom: 6px;
    color: white;
    font-weight: bold;
}


input[type="text"],
textarea {
    width: 100%;
    padding: 9px;
    box-sizing: border-box;
    background: black;
    color: white;
    border: 1px solid #555;
    border-radius: 6px;
}


textarea {
    min-height: 80px;
}


.product-list {
    max-height: 230px;
    overflow-y: auto;
    padding: 10px;
    background: black;
    border: 1px solid #555;
    border-radius: 6px;
}


.product-option {
    display: block;
    padding: 7px;
    margin: 0;
    color: white;
    font-weight: normal;
    text-align: right;
}


.product-option input {
    width: auto;
    margin-left: 7px;
}


.sendBtn {
    width: 100%;
    margin-top: 18px;
    padding: 10px;
    background: white;
    color: black;
    border: 0;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}


.msg {
    color: #ffcc66;
    text-align: center;
    font-weight: bold;
}


.error {
    margin-top: 30px;
    color: #ffcc66;
    text-align: center;
    font-weight: bold;
}


.report-view {
    margin-top: 20px;
    padding: 18px;
    background: #111;
    border: 1px solid #444;
}


.report-view strong {
    color: white;
}


</style>
</head>
<body>


<div class="page">


<h1>יצירת דוח סטיילינג</h1>


<?php

if ($msg != "") {
    echo "<p class='msg'>$msg</p>";
}

if (!$booking) {
    echo "<p class='error'>התור לא נמצא או שאינו שייך לך.</p>";
} else {

?>


<h2>פרטי הלקוח</h2>
<table>

<tr>
<th>שם</th>
<th>אימייל</th>
<th>תאריך</th>
<th>שעה</th>
</tr>

<tr>

<td>
<?php
echo $booking["fname"] . " ";
echo $booking["lname"];
?>
</td>

<td><?php echo $booking["email"]; ?></td>
<td><?php echo $booking["bookingdate"]; ?></td>
<td><?php echo $booking["bookingtime"]; ?></td>

</tr>

</table>


<?php

if ($measurements) {

?>

<h2>המידות שהלקוח שלח</h2>


<table>

<tr>
<th>מין</th>
<th>גובה</th>
<th>כתפיים</th>
<th>חזה</th>
<th>מותניים</th>
<th>ירכיים</th>
<th>אורך רגל</th>
<th>נעליים</th>
<th>מבנה גוף</th>
</tr>


<tr>

<td>
<?php
if ($measurements["gender"] == "women") {
    echo "אישה";
} else {
    echo "גבר";
}
?>
</td>

<td><?php echo $measurements["height_cm"]; ?></td>
<td><?php echo $measurements["shoulder_cm"]; ?></td>
<td><?php echo $measurements["chest_cm"]; ?></td>
<td><?php echo $measurements["waist_cm"]; ?></td>
<td><?php echo $measurements["hips_cm"]; ?></td>

<td>
<?php
if ($measurements["inseam_cm"] != "") {
    echo $measurements["inseam_cm"];
} else {
    echo "-";
}
?>
</td>

<td>
<?php
if ($measurements["shoe_size"] != "") {
    echo $measurements["shoe_size"];
} else {
    echo "-";
}
?>
</td>

<td><?php echo $bodyTypeText; ?></td>

</tr>

</table>


<?php

} else {

?>

<p class="error">
הלקוח לא שלח מידות לפגישה זו.
</p>

<?php

}


if ($report) {

?>

<h2>הדוח שנשלח</h2>

<div class="report-view">

<p>
<strong>כותרת:</strong>
<?php echo $report["reporttitle"]; ?>
</p>

<p>
<strong>צבעים מומלצים:</strong>
<?php echo $report["colors"]; ?>
</p>

<p>
<strong>מוצרים מומלצים:</strong>
<?php echo $report["recommendedproduct"]; ?>
</p>

<p>
<strong>הערות והמלצות:</strong>
<?php echo $report["notes"]; ?>
</p>

<p>
<strong>תאריך שליחת הדוח:</strong>
<?php echo $report["createdat"]; ?>
</p>

</div>

<?php

} else {

?>


<h2>כתיבת דוח</h2>


<form method="post">


<input
    type="hidden"
    name="bookingid"
    value="<?php echo $bookingid; ?>"
>


<label>כותרת הדוח</label>

<input
    type="text"
    name="reporttitle"
    required
>


<label>צבעים מומלצים</label>

<textarea
    name="colors"
    required
></textarea>


<label>מוצרים מומלצים מהחנות</label>


<div class="product-list">


<?php

while ( $productsResult && $product = mysqli_fetch_array($productsResult)) {

?>


<label class="product-option">

<input
    type="checkbox"
    name="recommendedproducts[]"
    value="<?php echo $product["pname"]; ?>"
>


<?php

echo $product["pname"];

if ($product["gender"] == "women") {

    echo " - נשים";

} else {

    echo " - גברים";
}

echo " - " . $product["price"] . " ₪";

?>

</label>
<?php
}
?>
</div>


<label>הערות והמלצות</label>

<textarea
    name="notes"
    required
></textarea>


<button
    class="sendBtn"
    type="submit"
    name="sendReport"
>

שליחת הדוח

</button>
</form>
<?php
}
}
?>
</div>
</body>
</html>
