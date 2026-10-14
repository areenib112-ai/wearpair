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

$msg = "";
$userSearch = "";
$userRole = "";
$userStatus = "";

if (isset($_GET["userSearch"])) {
    $userSearch = $_GET["userSearch"];
}

if (isset($_GET["userRole"])) {
    $userRole = $_GET["userRole"];
}

if (isset($_GET["userStatus"])) {
    $userStatus = $_GET["userStatus"];
}

if (isset($_GET["clearUserFilter"])) {
    $userSearch = "";
    $userRole = "";
    $userStatus = "";
}

if ( $userRole != "" && $userRole != "0" && $userRole != "1" && $userRole != "2") {
    $userRole = "";
}

if ( $userStatus != "" && $userStatus != "0" && $userStatus != "1") {
    $userStatus = "";
}


// הוספת משתמש חדש למערכת
if (isset($_POST["addUser"])) {

    $first = $_POST["first"];
    $last = $_POST["last"];
    $email = $_POST["email"];
    $pass = $_POST["pass"];
    $phone = $_POST["phone"];
    $addr = $_POST["addr"];
    $birthdate = $_POST["birthdate"];
    $role = $_POST["role"];

    if ( $role != 0 &&$role != 1 && $role != 2) {
        $role = 0;
    }

    if ( $first == "" || $last == "" || $email == "" ||
        $pass == "" || $addr == "" || $birthdate == ""
    ) {

        $msg = "יש למלא את כל שדות החובה.";

    } else {

        $checkEmail = mysqli_query(
            $conn,
            "SELECT userid
             FROM users
             WHERE email='$email'"
        );

        if ( $checkEmail &&mysqli_num_rows($checkEmail) > 0 ) {
            $msg = "כתובת האימייל כבר קיימת.";

        } else {

            $ok = mysqli_query(
                $conn,
                "INSERT INTO users
                (
                    pass1, pass2, pass3,
                    fname, lname, phone,
                    email, address, birthdate,
                    blocked, role
                )
                VALUES
                (
                    '$pass', '', '',
                    '$first', '$last', '$phone',
                    '$email', '$addr', '$birthdate',
                    0, '$role'
                )"
            );

            if ($ok) {
                $msg = "המשתמש נוסף בהצלחה.";
            } else {
                $msg = "שגיאה בהוספת המשתמש.";
            }
        }
    }
}


// שמירת קישור לפגישה של סטייליסט
if (isset($_POST["saveMeetingLink"])) {

    $stylistId = $_POST["userid"];
    $meetingLink = $_POST["meetinglink"];
    $meetingLink = str_replace(    "\\",  "\\\\", $meetingLink );
    $meetingLink = str_replace( "'", "''", $meetingLink );

    $stylistCheck = mysqli_query(
        $conn,
        "SELECT userid
         FROM users
         WHERE userid='$stylistId'
         AND role=2"
    );

    if ( !$stylistCheck || mysqli_num_rows($stylistCheck) == 0 ) {
        $msg = "הסטייליסט לא נמצא.";

    } elseif ($meetingLink == "") {
        $msg = "יש להזין קישור לפגישה.";

    } else {
        $updateLink = mysqli_query(
            $conn,
            "UPDATE users
             SET meetinglink='$meetingLink'
             WHERE userid='$stylistId'
             AND role=2"
        );

        if ($updateLink) {
            $msg = "קישור הפגישה נשמר בהצלחה.";
        } else {
            $msg = "שגיאה בשמירת קישור הפגישה.";
        }
    }
}


// מחיקת קישור הפגישה של סטייליסט
if (isset($_POST["deleteMeetingLink"])) {

    $stylistId = $_POST["userid"];

    $deleteLink = mysqli_query(
        $conn,
        "UPDATE users
         SET meetinglink=NULL
         WHERE userid='$stylistId'
         AND role=2"
    );

    if ($deleteLink) {
        $msg = "קישור הפגישה נמחק.";
    } else {
        $msg = "שגיאה במחיקת קישור הפגישה.";
    }
}


// חסימת משתמש
if (isset($_POST["blockUser"])) {

    $id = $_POST["userid"];

    if ($id == $_SESSION["userid"]) {
        $msg = "לא ניתן לחסום את החשבון שלך.";

    } else {

        $ok = mysqli_query(
            $conn,
            "UPDATE users
             SET blocked=2
             WHERE userid='$id'"
        );

        if ($ok) {
            $msg = "המשתמש נחסם.";
        } else {
            $msg = "שגיאה בחסימת המשתמש.";
        }
    }
}


// ביטול חסימת משתמש
if (isset($_POST["unblockUser"])) {

    $id = $_POST["userid"];

    $ok = mysqli_query(
        $conn,
        "UPDATE users
         SET blocked=0
         WHERE userid='$id'"
    );

    if ($ok) {
        $msg = "חסימת המשתמש בוטלה.";
    } else {
        $msg = "שגיאה בביטול חסימת המשתמש.";
    }
}

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ניהול משתמשים - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    direction: rtl;
}

.users-page {
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
}

.users-page h1,
.users-page h2 {
    color: white;
    text-align: center;
}

.users-page .box {
    max-width: 100%;
    margin: 20px auto;
    padding: 20px;
    background: #111;
    border: 1px solid #333;
    border-radius: 12px;
    box-sizing: border-box;
    text-align: right;
}

.users-layout > .box:first-child {
    max-width: 700px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.full-input {
    grid-column: 1 / -1;
}

.users-page label {
    display: block;
    margin-top: 8px;
    color: white;
}

.users-page input,
.users-page select {
    width: 100%;
    padding: 9px;
    margin-top: 6px;
    background: black;
    color: white;
    border: 1px solid #555;
    border-radius: 7px;
    box-sizing: border-box;
}

.users-page input[type="date"] {
    color-scheme: dark;
}

.users-page button {
    padding: 9px 14px;
    background: #eee;
    color: #111;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.main-btn {
    margin-top: 10px;
}

.msgText {
    color: #ffcc66;
    font-weight: bold;
    text-align: center;
}

.filter-buttons,
.meeting-link-buttons {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.filter-buttons button,
.meeting-link-buttons button {
    flex: 1;
}

.users-table-wrap {
    overflow-x: auto;
}

.users-page table {
    width: 100%;
    margin-top: 15px;
    border-collapse: collapse;
}

.users-page th,
.users-page td {
    padding: 9px;
    border: 1px solid #444;
    font-size: 13px;
    text-align: center;
}

.users-page th {
    background: black;
    color: white;
}

.user-actions button {
    display: block;
    width: 100%;
    margin: 4px 0;
}

.users-page .blockBtn,
.users-page .link-delete-btn {
    background: #4b0000;
    color: #ffcccc;
}

.users-page .unblockBtn {
    background: #d9ffd9;
    color: #003300;
}

.meeting-link-cell {
    min-width: 210px;
}

@media screen and (max-width: 800px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

    .full-input {
        grid-column: auto;
    }

    .filter-buttons,
    .meeting-link-buttons {
        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="users-page">

<h1>ניהול משתמשים</h1>

<?php
if ($msg != "") {
    echo "<p class='msgText'>$msg</p>";
}
?>


<div class="users-layout">


<div class="box">

<h2>הוספת משתמש</h2>

<form method="post">

<div class="form-grid">


<div>

<label>שם פרטי</label>

<input type="text"
       name="first"
       required>

</div>


<div>

<label>שם משפחה</label>

<input type="text"
       name="last"
       required>

</div>


<div>

<label>אימייל</label>

<input type="email"
       name="email"
       required>

</div>


<div>

<label>סיסמה</label>

<input type="password"
       name="pass"
       required>

</div>


<div>

<label>טלפון</label>

<input type="text"
       name="phone">

</div>


<div>

<label>כתובת</label>

<input type="text"
       name="addr"
       required>

</div>


<div class="full-input">

<label>תאריך לידה</label>

<input type="date"
       name="birthdate"
       required>

</div>


<div class="full-input">

<label>תפקיד</label>

<select name="role"
        required>

<option value="0">
לקוח
</option>

<option value="1">
מנהל
</option>

<option value="2">
סטייליסט
</option>

</select>

</div>

</div>


<button type="submit"
        name="addUser"
        class="main-btn">
הוספת משתמש
</button>

</form>

</div>


<div class="box">

<h2>רשימת משתמשים</h2>

<form method="get">

<div class="form-grid">


<div class="full-input">

<label>חיפוש</label>

<input type="text"
       name="userSearch"
       placeholder="שם או אימייל"
       value="<?php echo $userSearch; ?>">

</div>


<div>

<label>תפקיד</label>

<select name="userRole">

<option value="">
כל התפקידים
</option>


<option value="0"
<?php
if ($userRole == "0") {
    echo "selected";
}
?>>
לקוח
</option>


<option value="1"
<?php
if ($userRole == "1") {
    echo "selected";
}
?>>
מנהל
</option>


<option value="2"
<?php
if ($userRole == "2") {
    echo "selected";
}
?>>
סטייליסט
</option>

</select>

</div>


<div>

<label>סטטוס</label>

<select name="userStatus">

<option value="">
כל הסטטוסים
</option>


<option value="0"
<?php
if ($userStatus == "0") {
    echo "selected";
}
?>>
פעיל
</option>


<option value="1"
<?php
if ($userStatus == "1") {
    echo "selected";
}
?>>
חסום
</option>

</select>

</div>

</div>


<div class="filter-buttons">

<button type="submit"
        name="filterUsers"
        class="main-btn">
סינון
</button>

<button type="submit"
        name="clearUserFilter"
        class="main-btn">
ניקוי
</button>

</div>

</form>


<?php

$searchSql = str_replace( "\\", "\\\\", $userSearch);
$searchSql = str_replace(  "'", "''",$searchSql);

$sql = "SELECT userid,
               fname,
               lname,
               email,
               role,
               blocked,
               meetinglink
        FROM users
        WHERE 1=1";

if ($searchSql != "") {
// LIKE עם % מאפשר למצוא גם התאמה חלקית ולא רק ערך זהה בדיוק.
    $sql = $sql . " AND (
                fname LIKE '%$searchSql%'
                OR lname LIKE '%$searchSql%'
                OR email LIKE '%$searchSql%'
              )";
}

if ($userRole != "") {
    $sql = $sql . " AND role='$userRole'";
}

if ($userStatus == "0") {
    $sql = $sql . " AND blocked=0";
}

if ($userStatus == "1") {
    $sql = $sql . " AND blocked!=0";
}

$sql = $sql . " ORDER BY userid DESC";

$users = mysqli_query($conn, $sql);

?>


<div class="users-table-wrap">

<?php

if ( !$users || mysqli_num_rows($users) == 0) {
    echo "<p class='msgText'>לא נמצאו משתמשים.</p>";

} else {
?>

<table>

<tr>
<th>ID</th>
<th>שם</th>
<th>אימייל</th>
<th>תפקיד</th>
<th>סטטוס</th>
<th>קישור לפגישה</th>
<th>פעולה</th>
</tr>


<?php

while ($row = mysqli_fetch_array($users)) {

    $permission = "לקוח";

    if ($row["role"] == 1) {
        $permission = "מנהל";
    }

    if ($row["role"] == 2) {
        $permission = "סטייליסט";
    }


    $status = "פעיל";

    if ($row["blocked"] != 0) {
        $status = "חסום";
    }

?>


<tr>


<td>
<?php echo $row["userid"]; ?>
</td>


<td>

<?php
echo $row["fname"] . " ";
echo $row["lname"];
?>

</td>


<td>
<?php echo $row["email"]; ?>
</td>


<td>
<?php echo $permission; ?>
</td>


<td>
<?php echo $status; ?>
</td>


<td class="meeting-link-cell">

<?php

if ($row["role"] == 2) {

?>

<form method="post"
      class="meeting-link-form">

<input type="hidden"
       name="userid"
       value="<?php echo $row["userid"]; ?>">

<input type="url"
       name="meetinglink"
       placeholder="קישור Zoom או Google Meet"
       value="<?php echo $row["meetinglink"]; ?>"
       required>


<div class="meeting-link-buttons">

<button type="submit"
        name="saveMeetingLink">
שמירה
</button>


<?php
if (  isset($row["meetinglink"]) && $row["meetinglink"] != "") {
?>

<button type="submit"
        name="deleteMeetingLink"
        class="link-delete-btn"
        formnovalidate
        onclick="return confirm('למחוק את קישור הפגישה של הסטייליסט?')">
מחיקה
</button>

<?php
}
?>

</div>

</form>


<?php

} else {

    echo "—";
}

?>

</td>


<td class="user-actions">

<?php

if ($row["userid"] == $_SESSION["userid"]) {

    echo "המנהל הנוכחי";

} else {

?>

<form method="post">

<input type="hidden"
       name="userid"
       value="<?php echo $row["userid"]; ?>">


<?php

if ($row["blocked"] == 0) {

?>

<button class="blockBtn"
        type="submit"
        name="blockUser">
חסימה
</button>


<?php

} else {

?>

<button class="unblockBtn"
        type="submit"
        name="unblockUser">
ביטול חסימה
</button>

<?php
}
?>

</form>

<?php
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

</div>

</div>

</body>
</html>
