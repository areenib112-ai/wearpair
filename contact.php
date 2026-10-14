<?php
require("nav.php");
require("dbconnection.php");

if (!isset($_SESSION["userid"])) {
    echo "<p class='message'>יש להתחבר תחילה.</p>";
    exit();
}

if (!isset($_SESSION["role"]) || ($_SESSION["role"] != 0 && $_SESSION["role"] != 2)) {
    echo "<p class='message'>יש להשתמש בלוח הניהול כדי לנהל הודעות.</p>";
    exit();
}

$con = OpenCon();
$userid = $_SESSION["userid"];
$name = $_SESSION["fname"];
$msg = "";

$messageFilter = "all";
// קבלת הסינון שנבחר מטופס הסינון.
if (isset($_GET["messageFilter"])) {
    $messageFilter = $_GET["messageFilter"];
}
// שמירת הסינון הנוכחי לאחר שליחת הודעה או תגובה.
if (isset($_POST["messageFilter"])) {
    $messageFilter = $_POST["messageFilter"];
}

if (isset($_GET["clearMessageFilter"])) {
    $messageFilter = "all";
}

if ($messageFilter != "all" && $messageFilter != "open" && $messageFilter != "closed") {
    $messageFilter = "all";
}

// שליחת הודעה חדשה למנהל
if (isset($_POST["newTicket"])) {
    $subject = $_POST["subject"];
    $text = $_POST["message"];

    if ($subject == "" || $text == "") {
        $msg = "יש למלא את כל השדות.";
    } else {
        // ניקוי הטקסט לפני שמירתו במסד הנתונים
        $subject = str_replace("\\","\\\\",$subject);
        $subject = str_replace("'","''",$subject);
        $text = str_replace("\\","\\\\",$text);
        $text = str_replace("'","''",$text);
        $name2 = str_replace("\\","\\\\",$name);
        $name2 = str_replace("'","''",$name2);

        // יצירת ההודעה עם שם המשתמש
        $conversation = "<div class=\"msg user\">". $name2.": ".$text."</div>";

        // שמירת ההודעה החדשה בטבלת ההודעות הפתוחות
        $ok = mysqli_query($con,"
            INSERT INTO contactopen
            (userid,subject,conversation,createdat,updatedat)
            VALUES
            ('$userid','$subject','$conversation',NOW(),NOW())
        ");

        // בדיקה אם ההודעה נשמרה בהצלחה
        if ($ok) {
            $msg = "ההודעה נשלחה למנהל.";
        } else {
            $msg = "אירעה שגיאה בשליחת ההודעה.";
        }
    }
}

// שליחת תגובה להודעה פתוחה
if (isset($_POST["replyTicket"])) {
    $ticketid = $_POST["ticketid"];
    $text = $_POST["replytext"];

    if ($text == "") {
        $msg = "יש לכתוב תגובה.";
    } else {
        // קבלת ההודעה הפתוחה שהמשתמש רוצה להגיב עליה
        $ticketResult = mysqli_query($con,"
            SELECT conversation
            FROM contactopen
            WHERE id='$ticketid'
            AND userid='$userid'
        ");

        $ticket = mysqli_fetch_array($ticketResult);

        if ($ticket) {
            // ניקוי הטקסט לפני שמירתו במסד הנתונים
            $text = str_replace("\\","\\\\",$text);
            $text = str_replace("'","''",$text);
            $name2 = str_replace("\\","\\\\",$name);
            $name2 = str_replace("'","''",$name2);

            // יצירת התגובה עם שם המשתמש
            $newMessage = "<div class=\"msg user\">". $name2.": ".$text."</div>";

            $conversation = $ticket["conversation"].$newMessage;
            $conversation = str_replace("\\","\\\\",$conversation);
            $conversation = str_replace("'","''",$conversation);

            // הוספת התגובה להודעה הקיימת
            $ok = mysqli_query($con,"
                UPDATE contactopen
                SET conversation='$conversation',updatedat=NOW()
                WHERE id='$ticketid'
                AND userid='$userid'
            ");

            if ($ok) {
                $msg = "התגובה נשלחה.";
            } else {
                $msg = "אירעה שגיאה בשליחת התגובה.";
            }
        } else {
            $msg = "ההודעה לא נמצאה.";
        }
    }
}

// קבלת כל ההודעות הפתוחות של המשתמש
$openResult = mysqli_query($con,"
    SELECT *
    FROM contactopen
    WHERE userid='$userid'
    ORDER BY updatedat DESC
");

// קבלת כל ההודעות הסגורות של המשתמש
$closedResult = mysqli_query($con,"
    SELECT *
    FROM contacthistory
    WHERE userid='$userid'
    ORDER BY closedat DESC
");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>יצירת קשר - WearPair</title>

<style>
body{
    margin:0;
    background:#151515;
    color:silver;
    font-family:Arial;
    text-align:center;
    direction:rtl;
}
.page{
    max-width:850px;
    margin:35px auto;
    padding:20px;
}
h1,h2,h3{
    color:white;
}
.box,.ticket,.message-filter{
    padding:20px;
    margin:20px auto;
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
select,textarea{
    width:100%;
    padding:10px;
    margin-top:6px;
    box-sizing:border-box;
    background:black;
    color:white;
    border:1px solid #555;
    border-radius:6px;
}
textarea{
    min-height:90px;
    resize:vertical;
}
button{
    padding:10px 15px;
    margin-top:12px;
    border:0;
    border-radius:6px;
    font-weight:bold;
    cursor:pointer;
}
.main-button{
    width:100%;
}
.message{
    color:#ffcc66;
    font-weight:bold;
}
.filter-buttons{
    display:flex;
    gap:10px;
    margin-top:12px;
}
.filter-buttons button{
    flex:1;
}
.conversation{
    padding:12px;
    background:#080808;
    border-radius:7px;
}
.msg{
    padding:9px;
    margin:8px 0;
    border-radius:7px;
}
.user{
    background:#333;
}
.admin{
    background:#173b25;
}
.ticket-head{
    text-align:right;
}

.ticket-head h3{
    margin:0 0 8px 0;
}
.status-open{
    color:#9cff9c;
    font-weight:bold;
}
.status-closed,.closed-text{
    color:#ff9999;
    font-weight:bold;
}
.date{
    color:#999;
    font-size:13px;
}
@media screen and (max-width:600px){
    .filter-buttons{
        flex-direction:column;
    }
}
</style>
</head>

<body>
<div class="page">

<h1>יצירת קשר</h1>
<p>שליחת הודעה, משוב או דיווח על בעיה למנהל WearPair.</p>

<?php
if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}
?>

<div class="box">
<h2>הודעה חדשה</h2>

<form method="post">
<input type="hidden" name="messageFilter" value="<?php echo $messageFilter; ?>">

<label>נושא</label>
<select name="subject" required>
<option value="">בחירת נושא</option>
<option value="Contact Us">יצירת קשר</option>
<option value="Feedback">משוב</option>
<option value="Technical Problem">בעיה טכנית</option>
<option value="Account Problem">בעיה בחשבון</option>
<option value="Other">אחר</option>
</select>

<label>הודעה</label>
<textarea name="message" required></textarea>

<button class="main-button" type="submit" name="newTicket">שליחת הודעה</button>
</form>
</div>

<h2>ההודעות שלי</h2>

<div class="message-filter">
<form method="get">

<label>סטטוס</label>
<select name="messageFilter">
<option value="all" <?php if ($messageFilter == "all") echo "selected"; ?>>כל ההודעות</option>
<option value="open" <?php if ($messageFilter == "open") echo "selected"; ?>>הודעות פתוחות</option>
<option value="closed" <?php if ($messageFilter == "closed") echo "selected"; ?>>הודעות סגורות</option>
</select>

<div class="filter-buttons">
<button type="submit" name="filterMessages">סינון</button>
<button type="submit" name="clearMessageFilter">ניקוי</button>
</div>

</form>
</div>

<?php
$foundMessages = false;

// הצגת ההודעות הפתוחות
if ($messageFilter == "all" || $messageFilter == "open") {
    while ($row = mysqli_fetch_array($openResult)) {
        $foundMessages = true;
?>

<div class="ticket">

<div class="ticket-head">
<h3>
פנייה מספר <?php echo $row["id"]; ?> -

<?php
if ($row["subject"] == "Contact Us") {
    echo "יצירת קשר";
} elseif ($row["subject"] == "Feedback") {
    echo "משוב";
} elseif ($row["subject"] == "Technical Problem") {
    echo "בעיה טכנית";
} elseif ($row["subject"] == "Account Problem") {
    echo "בעיה בחשבון";
} elseif ($row["subject"] == "Other") {
    echo "אחר";
} else {
    echo $row["subject"];
}
?>
</h3>

<span class="status-open">פתוחה</span>
</div>

<div class="conversation">
<?php echo $row["conversation"]; ?>
</div>

<p class="date">
עדכון אחרון: <?php echo $row["updatedat"]; ?>
</p>

<form method="post">
<input type="hidden" name="ticketid" value="<?php echo $row["id"]; ?>">
<input type="hidden" name="messageFilter" value="<?php echo $messageFilter; ?>">

<label>תגובה</label>
<textarea name="replytext" required></textarea>
<button type="submit" name="replyTicket">שליחת תגובה</button>
</form>

</div>

<?php
    }
}

// הצגת ההודעות הסגורות
if ($messageFilter == "all" || $messageFilter == "closed") {
    while ($row = mysqli_fetch_array($closedResult)) {
        $foundMessages = true;
?>

<div class="ticket">

<div class="ticket-head">
<h3>
פנייה מספר <?php echo $row["id"]; ?> -

<?php
if ($row["subject"] == "Contact Us") {
    echo "יצירת קשר";
} elseif ($row["subject"] == "Feedback") {
    echo "משוב";
} elseif ($row["subject"] == "Technical Problem") {
    echo "בעיה טכנית";
} elseif ($row["subject"] == "Account Problem") {
    echo "בעיה בחשבון";
} elseif ($row["subject"] == "Other") {
    echo "אחר";
} else {
    echo $row["subject"];
}
?>
</h3>

<span class="status-closed">סגורה</span>
</div>

<div class="conversation">
<?php echo $row["conversation"]; ?>
</div>

<p class="date">
נסגרה בתאריך: <?php echo $row["closedat"]; ?>
</p>

<p class="closed-text">
הפנייה סגורה ולא ניתן לשלוח תגובות נוספות.
</p>

</div>

<?php
    }
}

if (!$foundMessages) {
    echo "<p>אין הודעות התואמות לסינון שנבחר.</p>";
}
?>

</div>
</body>
</html>