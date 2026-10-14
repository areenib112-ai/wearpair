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
$replyId = 0;
$replyType = "";
$messageFilter = "all";


// קבלת סוג ההודעות שהמנהל רוצה לראות
if (isset($_GET["messageFilter"])) {
    $messageFilter = $_GET["messageFilter"];
}

if (isset($_POST["messageFilter"])) {
    $messageFilter = $_POST["messageFilter"];
}


// בדיקה שהסינון שנבחר תקין
if ( $messageFilter != "all" && $messageFilter != "open" && $messageFilter != "closed") {
    $messageFilter = "all";
}

// בחירת הודעה לצפייה
if (isset($_POST["chooseReply"])) {

    $replyId = (int)$_POST["ticket_id"];
    $replyType = $_POST["ticket_type"];

    if ( $replyType != "open" &&  $replyType != "closed") {
        $replyId = 0;
        $replyType = "";
    }
}


// שליחת תגובה להודעה פתוחה
if (isset($_POST["sendReply"])) {

    $replyId = (int)$_POST["ticket_id"];
    $replyType = "open";
    $text = $_POST["reply_text"];

    if ($text == "") {

        $msg = "יש לכתוב תגובה.";

    } else {

        $ticketResult = mysqli_query(
            $conn,
            "SELECT *
             FROM contactopen
             WHERE id='$replyId'"
        );

        if (  $ticketResult && mysqli_num_rows($ticketResult) > 0  ) {

            $ticket = mysqli_fetch_array($ticketResult);

            $adminName = $_SESSION["fname"];


            // החלפת תווים מיוחדים לפני שמירת התגובה
            $adminName = str_replace("&", "&amp;", $adminName);
            $adminName = str_replace("<", "&lt;", $adminName);
            $adminName = str_replace(">", "&gt;", $adminName);

            $text = str_replace("&", "&amp;", $text);
            $text = str_replace("<", "&lt;", $text);
            $text = str_replace(">", "&gt;", $text);


            // יצירת התגובה החדשה והוספתה לשיחה
            $newMessage = "<div class='msg admin'> " .  $adminName . ": " . $text . "</div>";

            $newConversation =  $ticket["conversation"] .  $newMessage;


            // התאמת הטקסט לשמירה במסד הנתונים
            $sqlConversation = str_replace("\\", "\\\\", $newConversation);
            $sqlConversation = str_replace("'", "''", $sqlConversation);


            // שמירת השיחה המעודכנת
            $update = mysqli_query(
                $conn,
                "UPDATE contactopen
                 SET conversation='$sqlConversation',
                     updatedat=NOW()
                 WHERE id='$replyId'"
            );


            if ($update) {

                $userid = $ticket["userid"];

                $userResult = mysqli_query(
                    $conn,
                    "SELECT fname,email
                     FROM users
                     WHERE userid='$userid'"
                );


                if ($userResult && mysqli_num_rows($userResult) > 0 ) {

                    $user = mysqli_fetch_array($userResult);

                    $emailSubject = "WearPair - תגובה להודעה מספר " . $replyId;

                    $body =
                        "<h2>שלום " . $user["fname"] . "</h2>
                         <p>מנהל WearPair הגיב להודעה שלך.</p>
                         <h3>" . $ticket["subject"] . "</h3>
                         <div>" . $newConversation . "</div>
                         <p>ניתן להתחבר ל-WearPair כדי להמשיך את השיחה.</p>";


                    // שליחת אימייל למשתמש לאחר תגובת המנהל
                    $mailSent = sendWearPairMail(
                        $user["email"],
                        $emailSubject,
                        $body
                    );


                    if ($mailSent) {
                        $msg = "התגובה נשמרה והאימייל נשלח.";
                    } else {
                        $msg = "התגובה נשמרה, אך האימייל לא נשלח.";
                    }

                } else {

                    $msg = "התגובה נשמרה.";
                }

            } else {

                $msg = "שגיאה בשמירת התגובה.";
            }

        } else {

            $msg = "ההודעה לא נמצאה.";
            $replyId = 0;
            $replyType = "";
        }
    }
}


// סגירת הודעה והעברתה להיסטוריה
if (isset($_POST["closeTicket"])) {

    $closeId = (int)$_POST["ticket_id"];

    $ticketResult = mysqli_query(
        $conn,
        "SELECT *
         FROM contactopen
         WHERE id='$closeId'"
    );


    if ( $ticketResult && mysqli_num_rows($ticketResult) > 0 ) {

        $ticket = mysqli_fetch_array($ticketResult);

        $id = $ticket["id"];
        $userid = $ticket["userid"];
        $subject = $ticket["subject"];
        $conversation = $ticket["conversation"];
        $createdat = $ticket["createdat"];
        $updatedat = $ticket["updatedat"];


        // התאמת הטקסט לשמירה בטבלת ההיסטוריה
        $subjectSql = str_replace("\\", "\\\\", $subject);
        $subjectSql = str_replace("'", "''", $subjectSql);

        $conversationSql =  str_replace("\\", "\\\\", $conversation);

        $conversationSql = str_replace("'", "''", $conversationSql);


        // העברת ההודעה לטבלת ההיסטוריה
        $insert = mysqli_query(
            $conn,
            "INSERT INTO contacthistory
            (
                id,
                userid,
                subject,
                conversation,
                createdat,
                updatedat,
                closedat
            )
            VALUES
            (
                '$id',
                '$userid',
                '$subjectSql',
                '$conversationSql',
                '$createdat',
                '$updatedat',
                NOW()
            )"
        );


        if ($insert) {

            // מחיקת ההודעה מטבלת ההודעות הפתוחות
            mysqli_query(
                $conn,
                "DELETE FROM contactopen
                 WHERE id='$closeId'"
            );


            $userResult = mysqli_query(
                $conn,
                "SELECT fname,email
                 FROM users
                 WHERE userid='$userid'"
            );


            if ( $userResult && mysqli_num_rows($userResult) > 0 ) {

                $user = mysqli_fetch_array($userResult);

                $emailSubject = "WearPair - הודעה מספר " . $closeId . " נסגרה";

                $body =
                    "<h2>שלום " . $user["fname"] . "</h2>
                     <p>ההודעה שלך ב-WearPair נסגרה על ידי המנהל.</p>
                     <h3>" . $subject . "</h3>
                     <div>" . $conversation . "</div>
                     <p>ניתן לפתוח הודעה חדשה דרך WearPair.</p>";


                // שליחת אימייל למשתמש לאחר סגירת ההודעה
                $mailSent = sendWearPairMail( $user["email"], $emailSubject, $body );

                if ($mailSent) {
                    $msg = "ההודעה נסגרה והאימייל נשלח.";
                } else {
                    $msg = "ההודעה נסגרה, אך האימייל לא נשלח.";
                }

            } else {

                $msg = "ההודעה נסגרה.";
            }

            $replyId = 0;
            $replyType = "";

        } else {

            $msg = "שגיאה בסגירת ההודעה.";
        }

    } else {

        $msg = "ההודעה לא נמצאה.";
    }
}


// קבלת ההודעות הפתוחות
$openMessages = null;

if ( $messageFilter == "all" || $messageFilter == "open") {

    $openMessages = mysqli_query(
        $conn,
        "SELECT contactopen.*,
                users.fname,
                users.lname,
                users.email
         FROM contactopen
         INNER JOIN users
         ON contactopen.userid=users.userid
         ORDER BY contactopen.updatedat DESC"
    );
}


// קבלת ההודעות הסגורות
$closedMessages = null;

if ( $messageFilter == "all" || $messageFilter == "closed") {

    $closedMessages = mysqli_query(
        $conn,
        "SELECT contacthistory.*,
                users.fname,
                users.lname,
                users.email
         FROM contacthistory
         INNER JOIN users
         ON contacthistory.userid=users.userid
         ORDER BY contacthistory.closedat DESC"
    );
}


// קבלת ההודעה שנבחרה לצפייה
$replyTicket = null;

if ($replyId > 0) {

    if ($replyType == "open") {

        $replyResult = mysqli_query(
            $conn,
            "SELECT contactopen.*,
                    users.fname,
                    users.lname,
                    users.email
             FROM contactopen
             INNER JOIN users
             ON contactopen.userid=users.userid
             WHERE contactopen.id='$replyId'"
        );

    } elseif ($replyType == "closed") {

        $replyResult = mysqli_query(
            $conn,
            "SELECT contacthistory.*,
                    users.fname,
                    users.lname,
                    users.email
             FROM contacthistory
             INNER JOIN users
             ON contacthistory.userid=users.userid
             WHERE contacthistory.id='$replyId'"
        );
    }


    if ( isset($replyResult) && $replyResult && mysqli_num_rows($replyResult) > 0 ) {
        $replyTicket = mysqli_fetch_array($replyResult);
    }
}

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ניהול הודעות - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    direction: rtl;
}

.messages-page {
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
}

h1,
h2,
h3 {
    color: white;
    text-align: center;
}

.msgText {
    color: #ffcc66;
    font-weight: bold;
    text-align: center;
}

.messages-layout {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 15px;
}

.box {
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
    padding: 15px;
}

.filter-box {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

select,
textarea {
    padding: 9px;
    background: black;
    color: white;
    border: 1px solid #555;
    border-radius: 6px;
}

select {
    flex: 1;
}

textarea {
    width: 100%;
    box-sizing: border-box;
}

button {
    padding: 9px 14px;
    border: 0;
    border-radius: 6px;
    font-weight: bold;
    cursor: pointer;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 8px;
    border: 1px solid #444;
    text-align: center;
    font-size: 13px;
}

th {
    background: black;
    color: white;
}

.open-status {
    color: #9cff9c;
    font-weight: bold;
}

.closed-status {
    color: #ff9999;
    font-weight: bold;
}

.close-btn {
    background: #4b0000;
    color: #ffcccc;
}

.chat {
    background: #090909;
    border: 1px solid #444;
    border-radius: 7px;
    padding: 12px;
    margin: 15px 0;
    max-height: 300px;
    overflow-y: auto;
}

.reply-buttons {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.reply-buttons button {
    flex: 1;
}

@media screen and (max-width: 850px) {

    .messages-layout {
        grid-template-columns: 1fr;
    }

    .filter-box {
        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="messages-page">

<h1>ניהול הודעות</h1>

<?php

if ($msg != "") {
    echo "<p class='msgText'>$msg</p>";
}

?>


<div class="messages-layout">


<div class="box">

<h2>הודעות</h2>


<form method="get">

<div class="filter-box">

<select name="messageFilter">

<option value="all"
<?php
if ($messageFilter == "all") {
    echo "selected";
}
?>>
כל ההודעות
</option>


<option value="open"
<?php
if ($messageFilter == "open") {
    echo "selected";
}
?>>
הודעות פתוחות
</option>


<option value="closed"
<?php
if ($messageFilter == "closed") {
    echo "selected";
}
?>>
הודעות סגורות
</option>

</select>


<button type="submit">
סינון
</button>

</div>

</form>


<table>

<tr>
<th>ID</th>
<th>משתמש</th>
<th>אימייל</th>
<th>נושא</th>
<th>סטטוס</th>
<th>תאריך</th>
<th>פעולה</th>
</tr>


<?php
$foundMessages = false;

// הצגת ההודעות הפתוחות
if ( $openMessages && mysqli_num_rows($openMessages) > 0) {

    while ( $row = mysqli_fetch_array($openMessages) ) {
        $foundMessages = true;

?>

<tr>

<td><?php echo $row["id"]; ?></td>

<td>
<?php
echo $row["fname"] . " ";
echo $row["lname"];
?>
</td>

<td><?php echo $row["email"]; ?></td>

<td><?php echo $row["subject"]; ?></td>

<td class="open-status">
פתוחה
</td>

<td>
<?php echo $row["updatedat"]; ?>
</td>

<td>

<form method="post">

<input type="hidden"
       name="ticket_id"
       value="<?php echo $row["id"]; ?>">

<input type="hidden"
       name="ticket_type"
       value="open">

<input type="hidden"
       name="messageFilter"
       value="<?php echo $messageFilter; ?>">

<button type="submit"
        name="chooseReply">
צפייה / תגובה
</button>

</form>

</td>

</tr>

<?php

    }
}


// הצגת ההודעות הסגורות
if ($closedMessages && mysqli_num_rows($closedMessages) > 0) {

    while ( $row = mysqli_fetch_array($closedMessages)) {
        $foundMessages = true;

?>

<tr>

<td><?php echo $row["id"]; ?></td>

<td>
<?php
echo $row["fname"] . " ";
echo $row["lname"];
?>
</td>

<td><?php echo $row["email"]; ?></td>

<td><?php echo $row["subject"]; ?></td>

<td class="closed-status">
סגורה
</td>

<td>
<?php echo $row["closedat"]; ?>
</td>

<td>

<form method="post">

<input type="hidden"
       name="ticket_id"
       value="<?php echo $row["id"]; ?>">

<input type="hidden"
       name="ticket_type"
       value="closed">

<input type="hidden"
       name="messageFilter"
       value="<?php echo $messageFilter; ?>">

<button type="submit"
        name="chooseReply">
צפייה
</button>

</form>

</td>

</tr>

<?php

    }
}

if ($foundMessages == false) {

?>

<tr>
<td colspan="7">
לא נמצאו הודעות.
</td>
</tr>

<?php
}

?>

</table>

</div>


<div class="box">

<h2>צפייה ותגובה</h2>


<?php

if ($replyId == 0) {

?>

<p>
בחר הודעה מהטבלה.
</p>


<?php

} elseif ($replyTicket == null) {

?>

<p>
ההודעה לא נמצאה.
</p>


<?php

} else {

?>


<h3>
הודעה מספר <?php echo $replyTicket["id"]; ?>
</h3>


<p>
<b>משתמש:</b>

<?php
echo $replyTicket["fname"] . " ";
echo $replyTicket["lname"];
?>
</p>


<p>
<b>אימייל:</b>
<?php echo $replyTicket["email"]; ?>
</p>


<p>
<b>נושא:</b>
<?php echo $replyTicket["subject"]; ?>
</p>


<p>
<b>סטטוס:</b>

<?php

if ($replyType == "open") {
    echo "פתוחה";
} else {
    echo "סגורה";
}

?>

</p>


<div class="chat">
<?php echo $replyTicket["conversation"]; ?>
</div>


<?php

if ($replyType == "open") {

?>


<form method="post">

<input type="hidden"
       name="ticket_id"
       value="<?php echo $replyTicket["id"]; ?>">

<input type="hidden"
       name="messageFilter"
       value="<?php echo $messageFilter; ?>">


<label>תגובה</label>

<textarea name="reply_text"
          rows="5"
          required></textarea>


<div class="reply-buttons">

<button type="submit"
        name="sendReply">
שליחת תגובה
</button>


<button type="submit"
        name="closeTicket"
        class="close-btn"
        formnovalidate
        onclick="return confirm('לסגור את ההודעה ולשלוח אימייל למשתמש?')">
סגירת הודעה
</button>

</div>

</form>


<?php

} else {

?>


<p class="closed-status">
ההודעה סגורה ולא ניתן לשלוח תגובה חדשה.
</p>


<?php
}

?>


<?php
}

?>

</div>

</div>

</div>

</body>
</html>
