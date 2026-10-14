<?php

require("nav.php");
require("dbconnection.php");
require("functions.php");

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

$msg = "";
$filterStylist = "";
$filterStatus = "";

date_default_timezone_set("Asia/Jerusalem");
$today = date("Y-m-d");

// פגישה שנשארה booked אחרי שהתאריך שלה עבר הופכת ל-done
mysqli_query($conn,"
    UPDATE bookings
    SET status='done'
    WHERE status='booked'
    AND bookingdate<'$today'
");

// קבלת ערכי הסינון שנבחרו
if (isset($_GET["filterStylist"])) {
    $filterStylist = $_GET["filterStylist"];
}

if (isset($_GET["filterStatus"])) {
    $filterStatus = $_GET["filterStatus"];
}

// ניקוי הסינון
if (isset($_GET["clearBookingFilter"])) {
    $filterStylist = "";
    $filterStatus = "";
}

$allowedStatuses = array( "booked", "done", "cancelled");

// בדיקה שהסטטוס שנבחר תקין
$statusFound = false;

for ($i = 0; $i < count($allowedStatuses); $i++) {
    if ($filterStatus == $allowedStatuses[$i]) {
        $statusFound = true;
    }
}

if ($filterStatus != "" && $statusFound == false) {
    $filterStatus = "";
}

// אישור בקשת יום חופש של סטייליסט
if (isset($_POST["approveDayOff"])) {

    $slotid = (int)$_POST["slotid"];

    $requestResult = mysqli_query(
        $conn,
        "SELECT stylistavailability.*,
                users.fname,
                users.lname,
                users.email
         FROM stylistavailability
         INNER JOIN users
         ON stylistavailability.stylistid=users.userid
         WHERE stylistavailability.slotid='$slotid'
         AND stylistavailability.availabletime='DAY_OFF_REQUEST'
         AND users.role=2"
    );

    if (!$requestResult || mysqli_num_rows($requestResult) == 0) {

        $msg = "בקשת יום החופש לא נמצאה.";

    } else {

        $requestRow = mysqli_fetch_array($requestResult);

        $stylistid = $requestRow["stylistid"];

        $dayOffDate = $requestRow["availabledate"];

        // שינוי בקשת יום החופש למאושרת
        $approveRequest = mysqli_query(
            $conn,
            "UPDATE stylistavailability
             SET availabletime='DAY_OFF',
                 isavailable=0
             WHERE slotid='$slotid'
             AND availabletime='DAY_OFF_REQUEST'"
        );

        if ($approveRequest) {

            // קבלת הפגישות הפעילות של הסטייליסט באותו יום
            $cancelledBookings = mysqli_query(
                $conn,
                "SELECT bookings.*,
                        users.fname,
                        users.email
                 FROM bookings
                 INNER JOIN users
                 ON bookings.userid=users.userid
                 WHERE bookings.stylistid='$stylistid'
                 AND bookings.bookingdate='$dayOffDate'
                 AND bookings.status='booked'"
            );

            $cancelledCount = 0;
            $emailFailed = false;

            // שליחת אימייל לכל לקוח שהפגישה שלו בוטלה
            while ($cancelledRow = mysqli_fetch_array($cancelledBookings)) {

                $message = "<h2>שלום " . $cancelledRow["fname"] .  "</h2>";
                $message = $message . "<p>הפגישה שלך עם הסטייליסט בוטלה בגלל אישור יום חופש של הסטייליסט.</p>";
                $message = $message ."<p><b>תאריך:</b> " . $cancelledRow["bookingdate"] . "</p>";
                $message = $message ."<p><b>שעה:</b> " . $cancelledRow["bookingtime"] . "</p>";
                $message =$message . "<p>ניתן לקבוע פגישה חדשה דרך WearPair.</p>";

                $mailSent = sendWearPairMail( $cancelledRow["email"], "WearPair - הפגישה בוטלה", $message);

                if (!$mailSent) {
                    $emailFailed = true;
                }

                $cancelledCount++;
            }

            // ביטול כל הפגישות הפעילות של הסטייליסט באותו יום
            mysqli_query(
                $conn,
                "UPDATE bookings
                 SET status='cancelled',
                     calllink=NULL
                 WHERE stylistid='$stylistid'
                 AND bookingdate='$dayOffDate'
                 AND status='booked'"
            );

            if ($emailFailed) {
                $msg = "יום החופש אושר ו-$cancelledCount פגישות בוטלו, אך אימייל אחד או יותר לא נשלח.";

            } else {
                $msg = "יום החופש אושר ו-$cancelledCount פגישות בוטלו.";
            }

        } else {
            $msg = "שגיאה באישור בקשת יום החופש.";
        }
    }
}

// דחיית בקשת יום חופש של סטייליסט
if (isset($_POST["rejectDayOff"])) {

    $slotid = (int)$_POST["slotid"];

    $requestResult = mysqli_query(
        $conn,
        "SELECT stylistavailability.*,
                users.fname,
                users.lname,
                users.email
         FROM stylistavailability
         INNER JOIN users
         ON stylistavailability.stylistid=users.userid
         WHERE stylistavailability.slotid='$slotid'
         AND stylistavailability.availabletime='DAY_OFF_REQUEST'
         AND users.role=2"
    );

    if (!$requestResult || mysqli_num_rows($requestResult) == 0) {
        $msg = "בקשת יום החופש לא נמצאה.";

    } else {
        $requestRow = mysqli_fetch_array($requestResult);

        // מחיקת בקשת יום החופש שנדחתה
        $rejectRequest = mysqli_query(
            $conn,
            "DELETE FROM stylistavailability
             WHERE slotid='$slotid'
             AND availabletime='DAY_OFF_REQUEST'"
        );

        if ($rejectRequest) {
            $message = "<h2>שלום " . $requestRow["fname"] ."</h2>";
            $message = $message . "<p>בקשת יום החופש שלך לא אושרה על ידי המנהל.</p>";
            $message = $message . "<p><b>התאריך המבוקש:</b> " . $requestRow["availabledate"] . "</p>";
            $message = $message . "<p>הפגישות שלך בתאריך זה נשארות פעילות.</p>";

            $mailSent = sendWearPairMail( $requestRow["email"], "WearPair - בקשת יום החופש נדחתה", $message );

            if ($mailSent) {
                $msg =  "בקשת יום החופש נדחתה ונשלח אימייל לסטייליסט.";

            } else {
                $msg ="בקשת יום החופש נדחתה, אך האימייל לא נשלח.";
            }

        } else {
            $msg = "שגיאה בדחיית בקשת יום החופש.";
        }
    }
}

// קבלת בקשות ימי החופש שמחכות לאישור
$dayOffRequests = mysqli_query(
    $conn,
    "SELECT stylistavailability.*,
            users.fname,
            users.lname,
            users.email
     FROM stylistavailability
     INNER JOIN users
     ON stylistavailability.stylistid=users.userid
     WHERE stylistavailability.availabletime='DAY_OFF_REQUEST'
     AND users.role=2
     ORDER BY stylistavailability.availabledate ASC,
              stylistavailability.slotid ASC"
);

// קבלת רשימת הסטייליסטים לצורך הסינון
$stylistsResult = mysqli_query(
    $conn,
    "SELECT userid,
            fname,
            lname
     FROM users
     WHERE role=2
     ORDER BY fname,lname"
);

?>

<!DOCTYPE html>

<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ניהול פגישות - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    direction: rtl;
}

.bookings-page {
    max-width: 1200px;
    margin: 25px auto;
    padding: 20px;
    color: silver;
}

.bookings-page h1 {
    color: white;
    text-align: center;
}

.bookings-page h2 {
    color: white;
    text-align: center;
}

.msgText {
    color: #ffcc66;
    font-weight: bold;
    text-align: center;
}

.day-off-box {
    margin: 25px auto;
    padding: 20px;
    background: #111111;
    border: 1px solid #444444;
    border-radius: 10px;
    box-sizing: border-box;
}

.booking-box {
    margin: 25px auto;
    padding: 20px;
    background: #111111;
    border: 1px solid #444444;
    border-radius: 10px;
    box-sizing: border-box;
}

.booking-filter {
    margin-bottom: 20px;
    padding: 18px;
    background: #181818;
    border: 1px solid #444444;
    border-radius: 10px;
}

.booking-filter-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.booking-filter label {
    display: block;
    margin-bottom: 6px;
    color: white;
}

.booking-filter select {
    width: 100%;
    padding: 9px;
    background: black;
    color: white;
    border: 1px solid #555555;
    border-radius: 7px;
    box-sizing: border-box;
}

.booking-filter-buttons {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.booking-filter-buttons button {
    flex: 1;
}

.bookings-page button {
    padding: 9px 14px;
    background: #eeeeee;
    color: #111111;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.bookings-page button:hover {
    background: white;
}

.reject-day-off {
    background: #4b0000 !important;
    color: #ffcccc !important;
}

.day-off-actions {
    white-space: nowrap;
}

.day-off-actions form {
    display: inline-block;
    margin: 2px;
}

.booking-table-wrap {
    width: 100%;
    overflow-x: auto;
    margin-top: 15px;
}

.booking-table {
    width: 100%;
    border-collapse: collapse;
}

.booking-table th {
    padding: 10px;
    background: black;
    color: white;
    border: 1px solid #444444;
    text-align: center;
    font-size: 13px;
}

.booking-table td {
    padding: 10px;
    border: 1px solid #444444;
    text-align: center;
    font-size: 13px;
}

.request-status {
    color: #ffcc66;
    font-weight: bold;
}

.status-booked {
    color: #ffcc66;
    font-weight: bold;
}

.status-done {
    color: #b9d7ff;
    font-weight: bold;
}

.status-cancelled {
    color: #ff9999;
    font-weight: bold;
}

.link-text {
    color: white;
    word-break: break-word;
}

@media screen and (max-width: 750px) {

    .booking-filter-grid {
        grid-template-columns: 1fr;
    }

    .booking-filter-buttons {
        flex-direction: column;
    }

    .day-off-actions {
        white-space: normal;
    }
}

</style>

</head>

<body>

<div class="bookings-page">

<h1>ניהול פגישות</h1>

<?php

if ($msg != "") {
    echo "<p class='msgText'>$msg</p>";
}

?>

<div class="day-off-box">
<h2>בקשות לימי חופש</h2>

<?php
if ( !$dayOffRequests ||  mysqli_num_rows($dayOffRequests) == 0) {
?>
<p>אין בקשות לימי חופש שממתינות לאישור.</p>
<?php
} else {
?>

<div class="booking-table-wrap">
<table class="booking-table">
<tr>
<th>סטייליסט</th>
<th>תאריך מבוקש</th>
<th>סטטוס</th>
<th>פעולות</th>
</tr>

<?php
while ($requestRow = mysqli_fetch_array($dayOffRequests)) {
?>

<tr>
<td>

<?php
echo $requestRow["stylistid"] . " - ";
echo $requestRow["fname"] . " ";
echo $requestRow["lname"];
?>

</td>

<td>
<?php echo $requestRow["availabledate"]; ?>
</td>

<td class="request-status">ממתין</td>

<td class="day-off-actions">
<form method="post">
<input
    type="hidden"
    name="slotid"
    value="<?php echo $requestRow["slotid"]; ?>"
>

<button
    type="submit"
    name="approveDayOff"
    onclick="return confirm('לאשר את יום החופש ולבטל את הפגישות בתאריך זה?')"
>
אישור
</button>

</form>

<form method="post">

<input
    type="hidden"
    name="slotid"
    value="<?php echo $requestRow["slotid"]; ?>"
>

<button
    type="submit"
    name="rejectDayOff"
    class="reject-day-off"
    onclick="return confirm('לדחות את בקשת יום החופש?')"
>
דחייה
</button>
</form>
</td>
</tr>
<?php } ?>
</table>
</div>
<?php
}
?>
</div>

<div class="booking-box">
<h2>פגישות</h2>
<div class="booking-filter">
<form method="get">
<div class="booking-filter-grid">
<div>

<label>סטייליסט</label>

<select name="filterStylist">

<option value="">
כל הסטייליסטים
</option>

<?php

while ($stylistRow = mysqli_fetch_array($stylistsResult)) {

    $selected = "";

    if ($filterStylist == $stylistRow["userid"]) {
        $selected = "selected";
    }

?>

<option
    value="<?php echo $stylistRow["userid"]; ?>"
    <?php echo $selected; ?>
>

<?php
echo $stylistRow["userid"] . " - ";
echo $stylistRow["fname"] . " ";
echo $stylistRow["lname"];
?>

</option>

<?php
}
?>

</select>

</div>

<div>

<label>סטטוס</label>

<select name="filterStatus">

<option value="">
כל הסטטוסים
</option>

<option
    value="booked"

<?php
if ($filterStatus == "booked") {
    echo "selected";
}
?>

>
נקבע
</option>

<option
    value="done"

<?php
if ($filterStatus == "done") {
    echo "selected";
}
?>

>
הסתיים
</option>

<option
    value="cancelled"

<?php
if ($filterStatus == "cancelled") {
    echo "selected";
}
?>

>
בוטל
</option>

</select>

</div>

</div>

<div class="booking-filter-buttons">

<button
    type="submit"
    name="filterBookings"
>
סינון
</button>

<button
    type="submit"
    name="clearBookingFilter"
>
ניקוי
</button>
</div>
</form>
</div>

<?php

// קבלת הפגישות לפי הסטייליסט והסטטוס שנבחרו
$sql =
    "SELECT *
     FROM bookings
     WHERE 1=1";

if ($filterStylist != "") {
    $sql =  $sql ." AND bookings.stylistid='$filterStylist'";
}

if ($filterStatus != "") {
    $sql = $sql . " AND bookings.status='$filterStatus'";
}

$sql = $sql .
    " ORDER BY bookings.bookingdate DESC,
               bookings.bookingtime DESC,
               bookings.bookingid DESC";

$result = mysqli_query( $conn,$sql );
?>

<div class="booking-table-wrap">

<table class="booking-table">

<tr>

<th>מספר פגישה</th>
<th>לקוח</th>
<th>סטייליסט</th>
<th>תאריך</th>
<th>שעה</th>
<th>סטטוס</th>
<th>קישור לשיחה</th>

</tr>

<?php

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_array($result)) {

        // קבלת פרטי הלקוח של הפגישה
        $bookingCustomerResult = mysqli_query(
            $conn,
            "SELECT fname,lname
             FROM users
             WHERE userid='" . $row["userid"] . "'"
        );

        $bookingCustomerRow = mysqli_fetch_array($bookingCustomerResult);

        // קבלת פרטי הסטייליסט של הפגישה
        $bookingStylistResult = mysqli_query(
            $conn,
            "SELECT fname,lname
             FROM users
             WHERE userid='" . $row["stylistid"] . "'"
        );

        $bookingStylistRow = mysqli_fetch_array($bookingStylistResult);

        $statusClass = "status-booked";

        if ($row["status"] == "done") {
            $statusClass = "status-done";
        }

        if ($row["status"] == "cancelled") {
            $statusClass ="status-cancelled";
        }

?>

<tr>
<td>
<?php echo $row["bookingid"]; ?>
</td>

<td>
<?php
echo $row["userid"] . " - ";
echo $bookingCustomerRow["fname"] . " ";
echo $bookingCustomerRow["lname"];
?>

</td>

<td>
<?php
echo $row["stylistid"] . " - ";
echo $bookingStylistRow["fname"] . " ";
echo $bookingStylistRow["lname"];
?>
</td>

<td>
<?php echo $row["bookingdate"]; ?>
</td>

<td>
<?php echo $row["bookingtime"]; ?>
</td>

<td class="<?php echo $statusClass; ?>">

<?php

if ($row["status"] == "booked") {

    echo "נקבע";

} elseif ($row["status"] == "done") {

    echo "הסתיים";

} elseif ($row["status"] == "cancelled") {

    echo "בוטל";
}

?>

</td>

<td>

<?php

if (isset($row["calllink"]) && $row["calllink"] != "") {

?>

<a
    class="link-text"
    href="<?php echo $row["calllink"]; ?>"
    target="_blank"
>
<?php echo $row["calllink"]; ?>
</a>

<?php

} else {

    echo "עדיין אין קישור";
}

?>

</td>

</tr>

<?php

    }

} else {

?>

<tr>
<td colspan="7">
לא נמצאו פגישות.
</td>
</tr>
<?php
}
?>
</table>
</div>
</div>
</div>
</body>
</html>