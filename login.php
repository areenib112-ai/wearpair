<?php
require("dbconnection.php");
require("functions.php");
session_start();

$con = OpenCon();
$msg = "";

// התחברות למערכת
if (isset($_POST["lgSubmit"])) {

    $loginEmail = $_POST["email"];
    $loginPass = $_POST["password"];

    // חיפוש המשתמש לפי אימייל
    $usersResult = mysqli_query($con,"
        SELECT *
        FROM users
        WHERE email='$loginEmail'
        LIMIT 1
    ");

    if ($usersResult && mysqli_num_rows($usersResult) > 0) {

        $userRow = mysqli_fetch_array($usersResult);
        $triesKey = "tries_" . $userRow["userid"];

        // בדיקה אם המשתמש חסום
        if ((int)$userRow["blocked"] != 0) {

            mysqli_query($con,"
                UPDATE users
                SET lastlogindate=NOW(),
                    lastloginstatus='failed'
                WHERE userid='" . $userRow["userid"] . "'
            ");

            if ((int)$userRow["blocked"] == 1) {
                $msg = "החשבון נחסם זמנית. יש לאפס את הסיסמה.";
            } else {
                $msg = "החשבון נחסם על ידי המנהל. יש לפנות למנהל.";
            }

        } else {

            // בדיקת הסיסמה
            if ($userRow["pass1"] == $loginPass) {

                mysqli_query($con,"
                    UPDATE users
                    SET lastlogindate=NOW(),
                        lastloginstatus='success'
                    WHERE userid='" . $userRow["userid"] . "'
                ");

                $_SESSION["fname"] = $userRow["fname"];
                $_SESSION["userid"] = $userRow["userid"];
                $_SESSION["role"] = (int)$userRow["role"];

                $_SESSION[$triesKey] = 0;

                // מעבר לעמוד המתאים לפי סוג המשתמש
                if ($_SESSION["role"] == 1) {
                    header("Location: managestatistics.php");
                } else {
                    header("Location: home.php");
                }

                exit();

            } else {

                mysqli_query($con,"
                    UPDATE users
                    SET lastlogindate=NOW(),
                        lastloginstatus='failed'
                    WHERE userid='" . $userRow["userid"] . "'
                ");

                // ספירת ניסיונות התחברות שגויים
                if (isset($_SESSION[$triesKey])) {
                    $_SESSION[$triesKey]++;
                } else {
                    $_SESSION[$triesKey] = 1;
                }

                // חסימת המשתמש לאחר 3 ניסיונות שגויים
                if ($_SESSION[$triesKey] >= 3) {

                    $tempUserName = $userRow["fname"];
                    $tempEmail = $userRow["email"];
                    $tempPass = "";

                    $chars = "1234567890" . "abcdefghijklmnopqrstuvwxyz" . "ABCDEFGHIJKLMNOPQRSTUVWXYZ";

                    // יצירת סיסמה זמנית באורך 6 תווים
                    for ($i = 0; $i < 6; $i++) {

                        $idx = rand( 0, strlen($chars) - 1 );

                        $tempPass .= $chars[$idx];
                    }

                    // שמירת שלוש הסיסמאות האחרונות
                    mysqli_query($con,"
                        UPDATE users
                        SET pass3=pass2
                        WHERE userid='" . $userRow["userid"] . "'
                    ");

                    mysqli_query($con,"
                        UPDATE users
                        SET pass2=pass1
                        WHERE userid='" . $userRow["userid"] . "'
                    ");

                    mysqli_query($con,"
                        UPDATE users
                        SET pass1='$tempPass',
                            blocked=1
                        WHERE userid='" . $userRow["userid"] . "'
                    ");

                    $resetLink =
                        "http://localhost/wearpair/resetpass.php";

                    $message =
                        "<h2>שלום $tempUserName</h2>";

                    $message .=
                        "<p>החשבון שלך נחסם לאחר 3 ניסיונות התחברות שגויים.</p>";

                    $message .=
                        "<p><b>הסיסמה הזמנית שלך:</b> $tempPass</p>";

                    $message .=
                        "<p>יש להשתמש בקישור הבא כדי לבחור סיסמה חדשה:</p>";

                    $message .=
                        "<p><a href='$resetLink'>$resetLink</a></p>";

                    $sent = sendWearPairMail(
                        $tempEmail,
                        "WearPair - סיסמה זמנית",
                        $message
                    );

                    if ($sent) {
                        $msg = "החשבון נחסם לאחר 3 ניסיונות שגויים. סיסמה זמנית נשלחה לאימייל.";
                    } else {
                        $msg =  "לא ניתן לשלוח את האימייל. הסיסמה הזמנית היא: " .  $tempPass;
                    }

                } else {

                    $msg =   "סיסמה שגויה. ניסיון מספר " .  $_SESSION[$triesKey] . ".";
                }
            }
        }

    } else {
        $msg = "האימייל לא נמצא במערכת.";
    }
}

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width,initial-scale=1.0"
>

<title>התחברות - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.login-form {
    width: 90%;
    max-width: 400px;
    margin: 80px auto;
    padding: 25px;
    background: #111;
    border: 1px solid #444;
    text-align: center;
}

h2 {
    color: white;
}

input {
    width: 100%;
    padding: 10px;
    margin-bottom: 12px;
    box-sizing: border-box;
    background: black;
    color: white;
    border: 1px solid #555;
}

button {
    width: 100%;
    padding: 10px;
    background: white;
    color: black;
    border: none;
    font-weight: bold;
    cursor: pointer;
}

.message {
    color: #ffcc66;
    font-weight: bold;
}

.login-link {
    display: block;
    margin-top: 12px;
    color: silver;
    text-decoration: none;
}

.login-link:hover {
    color: white;
}

</style>

</head>

<body>

<?php require("nav.php"); ?>

<form method="post" class="login-form">

<h2>התחברות</h2>

<input
    type="email"
    name="email"
    placeholder="אימייל"
    required
>

<input
    type="password"
    name="password"
    placeholder="סיסמה"
    required
>

<button
    type="submit"
    name="lgSubmit"
>
התחברות
</button>

<?php

if ($msg != "") {
?>

<p class="message">
<?php echo $msg; ?>
</p>

<?php
}
?>

<a
    href="forgotpass.php"
    class="login-link"
>
שכחת סיסמה?
</a>

<a
    href="signup.php"
    class="login-link"
>
עדיין אין לך חשבון? הרשמה
</a>

</form>

</body>

</html>