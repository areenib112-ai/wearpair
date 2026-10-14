<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");
require("functions.php");

if (!isset($_SESSION["userid"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] != 2) {
    header("Location: home.php");
    exit();
}

date_default_timezone_set("Asia/Jerusalem");

$con = OpenCon();
$stylistid = (int)$_SESSION["userid"];
$msg = "";
$today = date("Y-m-d");

// פגישה שנשארה booked אחרי שהתאריך שלה עבר הופכת ל-done
mysqli_query($con,"
    UPDATE bookings
    SET status='done'
    WHERE status='booked'
    AND bookingdate<'$today'
");

$stylistResult = mysqli_query($con,"
    SELECT fname,lname,email,meetinglink
    FROM users
    WHERE userid='$stylistid'
    AND role=2
");

$stylistRow = mysqli_fetch_array($stylistResult);
$stylistMeetingLink = "";

if ($stylistRow) {
    $stylistMeetingLink = $stylistRow["meetinglink"];
}

// בקשת יום חופש
if (isset($_POST["dayOffBtn"])) {
    $dayOffDate = $_POST["dayoffdate"];

    if ($dayOffDate == "") {
        $msg = "יש לבחור תאריך.";
    } elseif ($dayOffDate < $today) {
        $msg = "לא ניתן לבחור תאריך שכבר עבר.";
    } else {
        $dayOffCheck = mysqli_query($con,"
            SELECT slotid,availabletime
            FROM stylistavailability
            WHERE stylistid='$stylistid'
            AND availabledate='$dayOffDate'
            AND (availabletime='DAY_OFF_REQUEST' OR availabletime='DAY_OFF')
        ");

        if ($dayOffCheck && mysqli_num_rows($dayOffCheck) > 0) {
            $dayOffRow = mysqli_fetch_array($dayOffCheck);

            if ($dayOffRow["availabletime"] == "DAY_OFF") {
                $msg = "יום החופש בתאריך זה כבר אושר.";
            } else {
                $msg = "בקשת יום החופש ממתינה לאישור המנהל.";
            }
        } else {
            $addRequest = mysqli_query($con,"
                INSERT INTO stylistavailability
                (stylistid,availabledate,availabletime,isavailable)
                VALUES('$stylistid','$dayOffDate','DAY_OFF_REQUEST',0)
            ");

            if ($addRequest) {
                $msg = "בקשת יום החופש נשלחה למנהל.";
            } else {
                $msg = "אירעה שגיאה בשליחת הבקשה.";
            }
        }
    }
}

// ביטול תור
if (isset($_POST["cancelBtn"])) {
    $bookingid = (int)$_POST["bookingid"];
// קבלת הפגישה שנבחרה יחד עם שם ואימייל הלקוח, רק אם היא שייכת לסטייליסט המחובר ועדיין פעילה.
    $bookingResult = mysqli_query($con,"
        SELECT bookings.*,
        users.fname,users.email
        FROM bookings
        INNER JOIN users
        ON bookings.userid=users.userid
        WHERE bookings.bookingid='$bookingid'
        AND bookings.stylistid='$stylistid'
        AND bookings.status='booked'
    ");

    if (!$bookingResult || mysqli_num_rows($bookingResult) == 0) {
        $msg = "לא ניתן לבטל את התור.";
    } else {
        $bookingRow = mysqli_fetch_array($bookingResult);

        $cancel = mysqli_query($con,"
            UPDATE bookings
            SET status='cancelled'
            WHERE bookingid='$bookingid'
            AND stylistid='$stylistid'
            AND status='booked'
        ");

        if ($cancel) {
            $message = "<h2>שלום ".$bookingRow["fname"]."</h2>";
            $message .= "<p>פגישת הסטיילינג שלך בוטלה.</p>";
            $message .= "<p><b>תאריך:</b> ".$bookingRow["bookingdate"]."</p>";
            $message .= "<p><b>שעה:</b> ".$bookingRow["bookingtime"]."</p>";

            sendWearPairMail( $bookingRow["email"], "WearPair - ביטול פגישת סטיילינג", $message );
            $msg = "התור בוטל.";
        } else {
            $msg = "אירעה שגיאה בביטול התור.";
        }
    }
}

// סיום תור
if (isset($_POST["doneBtn"])) {
    $bookingid = (int)$_POST["bookingid"];

    $done = mysqli_query($con,"
        UPDATE bookings
        SET status='done'
        WHERE bookingid='$bookingid'
        AND stylistid='$stylistid'
        AND status='booked'
    ");

    if ($done) {
        $msg = "התור סומן כהסתיים.";
    } else {
        $msg = "לא ניתן לסיים את התור.";
    }
}

// קבלת כל הפגישות של הסטייליסט יחד עם פרטי הלקוחות, כדי להציג אותן בטבלה לפי תאריך ושעה.
$bookingsResult = mysqli_query($con,"
    SELECT bookings.*,users.fname,users.lname,users.email
    FROM bookings
    INNER JOIN users
    ON bookings.userid=users.userid
    WHERE bookings.stylistid='$stylistid'
    ORDER BY bookings.bookingdate ASC,bookings.bookingtime ASC
");

require("nav.php");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>פגישות הסטייליסט - WearPair</title>

<style>
body{
    margin:0;
    background:#151515;
    color:silver;
    font-family:Arial;
    text-align:center;
}
.page{
    max-width:1250px;
    margin:35px auto;
    padding:20px;
}
h1,h2{
    color:white;
}
.msg{
    color:#ffcc66;
    font-weight:bold;
}
.dayoff-box{
    max-width:500px;
    margin:20px auto;
    padding:18px;
    background:#111;
    border:1px solid #444;
    border-radius:10px;
}
.dayoff-box input{
    width:100%;
    padding:9px;
    box-sizing:border-box;
    background:black;
    color:white;
    border:1px solid #555;
    border-radius:6px;
    color-scheme:dark;
}
.dayoff-box button{
    width:100%;
    margin-top:10px;
}
.table-box{
    overflow-x:auto;
}
table{
    width:100%;
    margin-top:25px;
    border-collapse:collapse;
    background:#111;
}
th,td{
    padding:10px;
    border:1px solid #333;
    vertical-align:middle;
}
th{
    background:black;
    color:white;
}
button,.report-link{
    display:inline-block;
    padding:8px 11px;
    margin:3px;
    border:1px solid transparent;
    border-radius:6px;
    font-weight:bold;
    cursor:pointer;
    text-decoration:none;
}
.cancel{
    background:#650000;
    color:white;
}
.done-button{
    background:#d9e8ff;
    color:#001f4d;
}
.report-link,.dayoff{
    background:white;
    color:black;
}
.call-link{
    color:white;
}
.booked{
    color:#ffcc66;
}
.done{
    color:#b9d7ff;
}
.cancelled{
    color:#ff9999;
}
.action-form{
    display:inline-block;
    margin:0;
}
.empty{
    padding:30px;
    background:#111;
    border:1px solid #444;
    border-radius:10px;
}
@media screen and (max-width:750px){
    table{
        min-width:900px;
    }
}
</style>
</head>

<body>
<div class="page">

<h1>הפגישות שלי</h1>

<?php
if ($msg != "") {
    echo "<p class='msg'>$msg</p>";
}
?>

<div class="dayoff-box">
<h2>בקשת יום חופש</h2>

<form method="post">
<input type="date" name="dayoffdate" min="<?php echo $today; ?>" required>
<button class="dayoff" type="submit" name="dayOffBtn">שליחת בקשה</button>
</form>
</div>

<div class="table-box">

<?php
if (!$bookingsResult || mysqli_num_rows($bookingsResult) == 0) {
?>
<div class="empty">אין לך פגישות.</div>
<?php
} else {
?>

<table>
<tr>
<th>מספר</th>
<th>לקוח</th>
<th>אימייל</th>
<th>תאריך</th>
<th>שעה</th>
<th>סטטוס</th>
<th>שיחה</th>
<th>פעולות</th>
</tr>

<?php
// מעבר על כל הפגישות והתאמת הסטטוס לטקסט שיוצג בטבלה.
while ($row = mysqli_fetch_array($bookingsResult)) {
    $bookingid = $row["bookingid"];

    $statusText = "";

    if ($row["status"] == "booked") {
        $statusText = "נקבע";
    } elseif ($row["status"] == "done") {
        $statusText = "הסתיים";
    } elseif ($row["status"] == "cancelled") {
        $statusText = "בוטל";
    } else {
        $statusText = $row["status"];
    }
?>

<tr>
<td><?php echo $row["bookingid"]; ?></td>
<td><?php echo $row["fname"]." ".$row["lname"]; ?></td>
<td><?php echo $row["email"]; ?></td>
<td><?php echo $row["bookingdate"]; ?></td>
<td><?php echo $row["bookingtime"]; ?></td>
<td class="<?php echo $row["status"]; ?>"><?php echo $statusText; ?></td>

<td>
<?php
if ($row["status"] == "booked" && $stylistMeetingLink != "") {
?>
<a class="call-link" href="<?php echo $stylistMeetingLink; ?>" target="_blank">פתיחת שיחה</a>
<?php
} elseif ($row["status"] == "booked") {
    echo "אין קישור";
} else {
    echo "-";
}
?>
</td>
<td>

<?php
if ($row["status"] == "booked") {
?>

<a class="report-link"
   href="stylist_report.php?bookingid=<?php echo $bookingid; ?>">
דוח
</a>

<form method="post" class="action-form">
<input type="hidden"
       name="bookingid"
       value="<?php echo $bookingid; ?>">

<button class="done-button"
        type="submit"
        name="doneBtn">
סיום
</button>
</form>

<form method="post" class="action-form">
<input type="hidden"
       name="bookingid"
       value="<?php echo $bookingid; ?>">

<button class="cancel"
        type="submit"
        name="cancelBtn"
        onclick="return confirm('לבטל את התור?');">
ביטול
</button>
</form>

<?php
} elseif ($row["status"] == "done") {
?>

<a class="report-link"
   href="stylist_report.php?bookingid=<?php echo $bookingid; ?>">
דוח
</a>

<?php
} else {
    echo "אין פעולה";
}
?>

</td>
</tr>

<?php
}
?>

</table>

<?php
}
?>

</div>
</div>
</body>
</html>