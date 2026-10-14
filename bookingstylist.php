<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");
require("functions.php");

date_default_timezone_set("Asia/Jerusalem");

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
$msg = "";
$selectedStylist = "";
$selectedDate = "";
$showTimes = false;

$today = date("Y-m-d");
$now = date("Y-m-d H:i");

// פגישה שנשארה booked אחרי שהתאריך שלה עבר הופכת ל-done
mysqli_query($con,"
    UPDATE bookings
    SET status='done'
    WHERE status='booked'
    AND bookingdate<'$today'
");

$slots = array(
    "10:00","10:45","11:30","12:15","13:00",
    "13:45","14:30","15:15","16:00"
);

// בודקת אם התאריך שנבחר מתאים לקביעת תור
function checkBookingDate($con,$stylistid,$userid,$date,$today)
{
    if ($date < $today) {
        return "לא ניתן לבחור תאריך שכבר עבר.";
    }

    // בדיקה אם התאריך הוא שבת
    $day = date("l", strtotime($date));

    if ($day == "Saturday") {
        return "הסטייליסטים אינם עובדים בשבת.";
    }

    $unavailable = mysqli_query($con,"
        SELECT slotid
        FROM stylistavailability
        WHERE availabledate='$date'
        AND isavailable=0
        AND (
            (stylistid='$stylistid' AND availabletime='DAY_OFF')
            OR
            (stylistid IS NULL AND availabletime='HOLIDAY')
        )
    ");

    if ($unavailable && mysqli_num_rows($unavailable) > 0) {
        return "התאריך שנבחר אינו זמין.";
    }

    $customerBooking = mysqli_query($con,"
        SELECT bookingid
        FROM bookings
        WHERE userid='$userid'
        AND bookingdate='$date'
        AND status!='cancelled'
    ");

    if ($customerBooking && mysqli_num_rows($customerBooking) > 0) {
        return "כבר יש לך תור בתאריך זה.";
    }

    return "";
}

if (isset($_POST["showTimes"])) {
    $selectedStylist = $_POST["stylistid"];
    $selectedDate = $_POST["bookingdate"];

    if ($selectedStylist == "" || $selectedDate == "") {
        $msg = "יש לבחור סטייליסט ותאריך.";
    } else {
        $msg = checkBookingDate(
            $con,$selectedStylist,$userid,$selectedDate,$today
        );

        if ($msg == "") {
            $showTimes = true;
        }
    }
}

// קביעת תור
if (isset($_POST["bookBtn"])) {
    $selectedStylist = $_POST["stylistid"];
    $selectedDate = $_POST["bookingdate"];
    $selectedTime = "";

    if (isset($_POST["bookingtime"])) {
        $selectedTime = $_POST["bookingtime"];
    }

    if ($selectedStylist == "" || $selectedDate == "" || $selectedTime == "") {
        $msg = "יש לבחור שעה זמינה.";
    } elseif (!in_array($selectedTime,$slots)) {
        $msg = "שעת התור אינה תקינה.";
    } elseif (($selectedDate." ".$selectedTime) <= $now) {
        $msg = "לא ניתן להזמין שעה שכבר עברה.";
    } else {
        $msg = checkBookingDate(
            $con,$selectedStylist,$userid,$selectedDate,$today
        );

        if ($msg == "") {
            $stylistCheck = mysqli_query($con,"
                SELECT userid,fname,lname,meetinglink
                FROM users
                WHERE userid='$selectedStylist'
                AND role=2
                AND blocked=0
            ");

            $slotCheck = mysqli_query($con,"
                SELECT bookingid
                FROM bookings
                WHERE stylistid='$selectedStylist'
                AND bookingdate='$selectedDate'
                AND bookingtime='$selectedTime'
                AND status!='cancelled'
            ");

            if (!$stylistCheck || mysqli_num_rows($stylistCheck) == 0) {
                $msg = "הסטייליסט לא נמצא.";
            } elseif ($slotCheck && mysqli_num_rows($slotCheck) > 0) {
                $msg = "השעה שנבחרה כבר תפוסה.";
                $showTimes = true;
            } else {
                $stylistRow = mysqli_fetch_array($stylistCheck);
                $meetinglink = $stylistRow["meetinglink"];

                $add = mysqli_query($con,"
                    INSERT INTO bookings
                    (userid,stylistid,bookingdate,bookingtime,status,calllink)
                    VALUES
                    ('$userid','$selectedStylist','$selectedDate',
                    '$selectedTime','booked','$meetinglink')
                ");

                if ($add) {
                    $customerResult = mysqli_query($con,"
                        SELECT fname,email
                        FROM users
                        WHERE userid='$userid'
                    ");

                    $customerRow = mysqli_fetch_array($customerResult);

                    if ($customerRow) {
                        $message = "<h2>שלום ".$customerRow["fname"]."</h2>";
                        $message .= "<p>פגישת הסטיילינג שלך נקבעה.</p>";
                        $message .= "<p><b>סטייליסט:</b> ".$stylistRow["fname"]." ".$stylistRow["lname"]."</p>";
                        $message .= "<p><b>תאריך:</b> ".$selectedDate."</p>";
                        $message .= "<p><b>שעה:</b> ".$selectedTime."</p>";

                        if ($meetinglink != "") {
                            $message .= "<p><b>קישור לפגישה:</b> <a href='$meetinglink'>$meetinglink</a></p>";
                        }

                        sendWearPairMail(
                            $customerRow["email"],
                            "WearPair - פגישת סטיילינג",
                            $message
                        );
                    }

                    $msg = "התור נקבע בהצלחה.";
                    $selectedStylist = "";
                    $selectedDate = "";
                } else {
                    $msg = "אירעה שגיאה ביצירת התור.";
                    $showTimes = true;
                }
            }
        }
    }
}


// שליחת המידות מהפרופיל ישירות לסטייליסט
if (isset($_POST["sendMeasurements"])) {
    $bookingid = (int)$_POST["bookingid"];

    // בדיקה שהתור שייך ללקוח ושהוא עדיין פעיל
    $bookingCheck = mysqli_query($con,"
        SELECT stylistid
        FROM bookings
        WHERE bookingid='$bookingid'
        AND userid='$userid'
        AND status='booked'
    ");

    if (!$bookingCheck || mysqli_num_rows($bookingCheck) == 0) {
        $msg = "לא ניתן לשלוח מידות לתור זה.";
    } else {
        $bookingCheckRow = mysqli_fetch_array($bookingCheck);
        $stylistid = $bookingCheckRow["stylistid"];

        // קבלת המידות השמורות בפרופיל של הלקוח
        $profileResult = mysqli_query($con,"
            SELECT gender,height_cm,shoulder_cm,chest_cm,waist_cm,
                   hips_cm,inseam_cm,shoe_size
            FROM users
            WHERE userid='$userid'
        ");

        $profile = mysqli_fetch_array($profileResult);

        if (
            !$profile ||
            $profile["gender"] == "" ||
            $profile["height_cm"] == "" ||
            $profile["shoulder_cm"] == "" ||
            $profile["chest_cm"] == "" ||
            $profile["waist_cm"] == "" ||
            $profile["hips_cm"] == ""|| $profile["inseam_cm"] == ""
        ) {
            $msg = "יש לעדכן תחילה את המידות בפרופיל.";
        } else {
            $gender = $profile["gender"];
            $height = $profile["height_cm"];
            $shoulder = $profile["shoulder_cm"];
            $chest = $profile["chest_cm"];
            $waist = $profile["waist_cm"];
            $hips = $profile["hips_cm"];
            $inseam = $profile["inseam_cm"];

            $shoeSql = "NULL";

            if ($profile["shoe_size"] != "") {
                $shoeSql = "'".$profile["shoe_size"]."'";
            }

            // בדיקה אם המידות כבר נשלחו לתור הזה
            $measurementCheck = mysqli_query($con,"
                SELECT bookingid
                FROM booking_measurements
                WHERE bookingid='$bookingid'
                AND userid='$userid'
            ");

            if ($measurementCheck && mysqli_num_rows($measurementCheck) > 0) {

                // עדכון המידות שנשלחו בעבר
                $saveMeasurements = mysqli_query($con,"
                    UPDATE booking_measurements
                    SET stylistid='$stylistid',
                        gender='$gender',
                        height_cm='$height',
                        shoulder_cm='$shoulder',
                        chest_cm='$chest',
                        waist_cm='$waist',
                        hips_cm='$hips',
                        inseam_cm=$inseam,
                        shoe_size=$shoeSql,
                        submittedat=NOW()
                    WHERE bookingid='$bookingid'
                    AND userid='$userid'
                ");

            } else {

                // שמירת המידות בפעם הראשונה
                $saveMeasurements = mysqli_query($con,"
                    INSERT INTO booking_measurements
                    (bookingid,userid,stylistid,gender,height_cm,shoulder_cm,
                     chest_cm,waist_cm,hips_cm,inseam_cm,shoe_size,submittedat)
                    VALUES
                    ('$bookingid','$userid','$stylistid','$gender','$height',
                     '$shoulder','$chest','$waist','$hips',$inseam,$shoeSql,NOW())
                ");
            }

            if ($saveMeasurements) {
                $msg = "המידות נשלחו לסטייליסט.";
            } else {
                $msg = "אירעה שגיאה בשליחת המידות.";
            }
        }
    }
}

// ביטול תור
if (isset($_POST["cancelMyBooking"])) {
    $bookingid = (int)$_POST["bookingid"];

    $bookingResult = mysqli_query($con,"
        SELECT bookings.*,users.fname,users.email
        FROM bookings
        INNER JOIN users
        ON bookings.stylistid=users.userid
        WHERE bookings.bookingid='$bookingid'
        AND bookings.userid='$userid'
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
            AND userid='$userid'
            AND status='booked'
        ");

        if ($cancel) {
            $message = "<h2>שלום ".$bookingRow["fname"]."</h2>";
            $message .= "<p>הלקוח ביטל את פגישת הסטיילינג.</p>";
            $message .= "<p><b>תאריך:</b> ".$bookingRow["bookingdate"]."</p>";
            $message .= "<p><b>שעה:</b> ".$bookingRow["bookingtime"]."</p>";

            sendWearPairMail(
                $bookingRow["email"],
                "WearPair - ביטול פגישת סטיילינג",
                $message
            );

            $msg = "התור בוטל.";
        } else {
            $msg = "לא ניתן לבטל את התור.";
        }
    }
}

$stylistsResult = mysqli_query($con,"
    SELECT userid,fname,lname
    FROM users
    WHERE role=2
    AND blocked=0
    ORDER BY fname
");

$booked = array();

if ($showTimes) {
        // קבלת השעות שכבר תפוסות אצל הסטייליסט בתאריך שנבחר
    $bookedResult = mysqli_query($con,"
        SELECT bookingtime
        FROM bookings
        WHERE stylistid='$selectedStylist'
        AND bookingdate='$selectedDate'
        AND status!='cancelled'
    ");

        // שמירת כל השעות התפוסות במערך
    while ($bookedResult && $bookedRow = mysqli_fetch_array($bookedResult)) {
        $booked[] = $bookedRow["bookingtime"];
    }
}

// קבלת כל התורים של הלקוח יחד עם פרטי הסטייליסט
$myBookingsResult = mysqli_query($con,"
    SELECT bookings.*,users.fname,users.lname,users.email,users.meetinglink
    FROM bookings
    INNER JOIN users
    ON bookings.stylistid=users.userid
    WHERE bookings.userid='$userid'
    ORDER BY bookings.bookingdate DESC,bookings.bookingtime DESC
");

require("nav.php");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>קביעת תור עם סטייליסט - WearPair</title>

<style>
body{
    margin:0;
    background:#151515;
    color:silver;
    font-family:Arial;
    text-align:center;
}
.page{
    max-width:1100px;
    margin:35px auto;
    padding:20px;
}
h1,h2{
    color:white;
}
.box{
    max-width:650px;
    margin:20px auto;
    padding:20px;
    background:#111;
    border:1px solid #444;
    border-radius:10px;
    text-align:right;
}
label{
    display:block;
    margin-top:12px;
    color:white;
}
select,input{
    width:100%;
    padding:9px;
    margin-top:5px;
    box-sizing:border-box;
    background:black;
    color:white;
    border:1px solid #555;
}
input[type="date"]{
    color-scheme:dark;
}
button,.action-link{
    display:inline-block;
    padding:10px 14px;
    border:1px solid transparent;
    border-radius:6px;
    font-weight:bold;
    text-decoration:none;
    cursor:pointer;
}
.main-button{
    width:100%;
    margin-top:18px;
    background:white;
    color:black;
}
.main-button:hover{
    background:#d4af37;
}
.message{
    color:#ffcc66;
    font-weight:bold;
}
.table-box{
    overflow-x:auto;
}
table{
    width:100%;
    min-width:900px;
    border-collapse:collapse;
    background:#111;
}
th,td{
    padding:10px;
    border:1px solid #444;
}
th{
    background:black;
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
.cancel-button{
    background:#700000;
    color:white;
}
.action-buttons{
    display:flex;
    justify-content:center;
    gap:6px;
}
.action-link,.action-form{
    width:95px;
    margin:0;
}
.action-link,.action-form button{
    width:95px;
    height:34px;
    padding:6px;
    box-sizing:border-box;
    border-radius:6px;
    font-size:11px;
    font-weight:bold;
    text-align:center;
}
.action-link{
    display:flex;
    align-items:center;
    justify-content:center;
    background:#d0d0d0;
    color:#111;
}
.measurements-button{
    background:#d0d0d0;
    color:#111;
}
.cancel-button{
    background:#700000;
    color:white;
}
.no-link{
    flex:1;
    padding:9px;
    color:#999;
    border:1px solid #444;
    border-radius:7px;
    font-size:12px;
}
.no-action{
    color:#777;
    font-size:13px;
}
@media screen and (max-width:650px){
    .action-buttons{
        flex-direction:column;
    }
}
</style>
</head>

<body>
<div class="page">

<h1>קביעת תור עם סטייליסט</h1>

<?php
if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}
?>

<div class="box">
<h2>בחירת סטייליסט ותאריך</h2>

<form method="post">
<label>סטייליסט</label>

<select name="stylistid" required>
<option value="">בחירת סטייליסט</option>

<?php
if ($stylistsResult) {

    // מעבר על כל הסטייליסטים והצגתם ברשימה
    while ($stylistRow = mysqli_fetch_array($stylistsResult)) {
        $selected = "";

        // שמירת הסטייליסט שנבחר לאחר שליחת הטופס
        if ($selectedStylist == $stylistRow["userid"]) {
            $selected = "selected";
        }

        echo "<option value='".$stylistRow["userid"]."' $selected>";
        echo $stylistRow["fname"]." ".$stylistRow["lname"];
        echo "</option>";
    }
}
?>

</select>

<label>תאריך</label>
<input type="date" name="bookingdate" min="<?php echo $today; ?>" value="<?php echo $selectedDate; ?>" required>

<button class="main-button" type="submit" name="showTimes">הצגת שעות</button>
</form>

<?php
if ($showTimes) {
?>

<h2>בחירת שעה</h2>

<form method="post">
<input type="hidden" name="stylistid" value="<?php echo $selectedStylist; ?>">
<input type="hidden" name="bookingdate" value="<?php echo $selectedDate; ?>">

<label>שעה</label>
<select name="bookingtime" required>
<option value="">בחירת שעה</option>

<?php
$available = 0;

// מעבר על כל שעות העבודה
foreach ($slots as $slot) {
    $slotDateTime = $selectedDate." ".$slot;

    // הצגת השעה רק אם היא פנויה ועדיין לא עברה
    if (!in_array($slot,$booked) && $slotDateTime > $now) {
        $available++;
        echo "<option value='$slot'>$slot</option>";
    }
}
?>

</select>

<?php
// הצגת כפתור קביעת התור רק אם קיימת שעה פנויה
if ($available > 0) {
?>
<button class="main-button" type="submit" name="bookBtn">קביעת תור</button>
<?php
} else {
    echo "<p class='message'>אין שעות פנויות בתאריך זה.</p>";
}
?>

</form>

<?php
}
?>

</div>

<h2>התורים שלי</h2>

<div class="table-box">

<?php
if (!$myBookingsResult || mysqli_num_rows($myBookingsResult) == 0) {
    echo "<p>עדיין אין לך תורים.</p>";
} else {
?>

<table>
<tr>
<th>סטייליסט</th>
<th>אימייל</th>
<th>תאריך</th>
<th>שעה</th>
<th>סטטוס</th>
<th>פעולה</th>
</tr>

<?php
while ($bookingRow = mysqli_fetch_array($myBookingsResult)) {
?>

<tr>
<td><?php echo $bookingRow["fname"]." ".$bookingRow["lname"]; ?></td>
<td><?php echo $bookingRow["email"]; ?></td>
<td><?php echo $bookingRow["bookingdate"]; ?></td>
<td><?php echo $bookingRow["bookingtime"]; ?></td>

<td class="<?php echo $bookingRow["status"]; ?>">
<?php
if ($bookingRow["status"] == "booked") {
    echo "נקבע";
} elseif ($bookingRow["status"] == "done") {
    echo "הסתיים";
} elseif ($bookingRow["status"] == "cancelled") {
    echo "בוטל";
} else {
    echo $bookingRow["status"];
}
?>
</td>

<td>
<?php
if ($bookingRow["status"] == "booked") {
?>
<div class="action-buttons">

<?php
if ($bookingRow["meetinglink"] != "") {
?>
<a class="action-link meeting-button" href="<?php echo $bookingRow["meetinglink"]; ?>" target="_blank">כניסה לפגישה</a>
<?php
} else {
?>
<span class="no-link">אין קישור לפגישה</span>
<?php
}
?>

<form method="post" class="action-form">
<input type="hidden" name="bookingid" value="<?php echo $bookingRow["bookingid"]; ?>">
<button class="measurements-button" type="submit" name="sendMeasurements">שליחת המידות לסטייליסט</button>
</form>

<a class="action-link report-button" href="customer_reports_history.php?bookingid=<?php echo $bookingRow["bookingid"]; ?>">דוח</a>

<form method="post" class="action-form">
<input type="hidden" name="bookingid" value="<?php echo $bookingRow["bookingid"]; ?>">
<button class="cancel-button" type="submit" name="cancelMyBooking" onclick="return confirm('לבטל את התור?')">ביטול</button>
</form>

</div>

<?php
} elseif ($bookingRow["status"] == "done") {
?>
<div class="action-buttons">
<a class="action-link report-button" href="customer_reports_history.php?bookingid=<?php echo $bookingRow["bookingid"]; ?>">דוח</a>
</div>
<?php
} else {
    echo "<span class='no-action'>אין פעולה</span>";
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