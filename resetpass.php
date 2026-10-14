<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");

$con = OpenCon();

$msg = "";
$success = false;


// שינוי הסיסמה בעזרת הסיסמה הזמנית
if (isset($_POST["resetBtn"])) {

    $email = trim($_POST["email"]);
    $tempPass = $_POST["tempPass"];
    $newPass = $_POST["newPass"];
    $confirmPass = $_POST["confirmPass"];


    if ( $email == "" || $tempPass == "" || $newPass == "" || $confirmPass == "" ) {
        $msg = "יש למלא את כל השדות.";

    } elseif ($newPass != $confirmPass) {
        $msg = "הסיסמאות החדשות אינן תואמות.";

    } elseif (strlen($newPass) < 4) {
        $msg = "הסיסמה החדשה חייבת להכיל לפחות 4 תווים.";

    } else {
        // חיפוש המשתמש לפי האימייל
        $result = mysqli_query($con,
            "SELECT *
             FROM users
             WHERE email='$email'
             LIMIT 1"
        );


        if (!$result) {
            $msg = "אירעה שגיאה במסד הנתונים.";

        } elseif (mysqli_num_rows($result) == 0) {
            $msg = "האימייל לא נמצא במערכת.";

        } else {
            $user = mysqli_fetch_array($result);


            // משתמש שנחסם על ידי המנהל לא יכול לשנות סיסמה
            if ($user["blocked"] == 2) {
                $msg =  "החשבון נחסם על ידי המנהל. אין אפשרות לשנות סיסמה.";

            // איפוס סיסמה אפשרי רק לאחר קבלת סיסמה זמנית
            } elseif ($user["blocked"] != 1) {
                $msg = "יש לבקש תחילה סיסמה זמנית דרך שכחת סיסמה.";

            } elseif ($user["pass1"] != $tempPass) {
                $msg = "הסיסמה הזמנית אינה נכונה.";

            // מניעת שימוש באחת משלוש הסיסמאות האחרונות
            } elseif ( $newPass == $user["pass1"] || $newPass == $user["pass2"] || $newPass == $user["pass3"]) {
                $msg = "לא ניתן להשתמש באחת משלוש הסיסמאות האחרונות.";

            } else {
                // שמירת הסיסמה החדשה ופתיחת החסימה הזמנית
                $update = mysqli_query($con,
                    "UPDATE users
                     SET pass1='$newPass',
                         blocked=0
                     WHERE userid='" . $user["userid"] . "'"
                );


                if ($update) {
                    $msg = "הסיסמה שונתה בהצלחה. כעת ניתן להתחבר.";
                    $success = true;

                } else {
                    $msg = "אירעה שגיאה בשינוי הסיסמה.";
                }
            }
        }
    }
}


require("nav.php");

?>


<!DOCTYPE html>

<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>איפוס סיסמה - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.reset-form {
    width: 90%;
    max-width: 430px;
    margin: 70px auto;
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

.back-link {
    display: block;
    margin-top: 15px;
    color: silver;
    text-decoration: none;
}

.back-link:hover {
    color: white;
}

</style>

</head>


<body>


<form method="post" class="reset-form">


<h2>איפוס סיסמה</h2>


<?php

if (!$success) {

?>


<p>
יש להזין את הסיסמה הזמנית שנשלחה לאימייל ולבחור סיסמה חדשה.
</p>


<input
    type="email"
    name="email"
    placeholder="אימייל"
    required
>


<input
    type="password"
    name="tempPass"
    placeholder="סיסמה זמנית"
    required
>


<input
    type="password"
    name="newPass"
    placeholder="סיסמה חדשה"
    required
>


<input
    type="password"
    name="confirmPass"
    placeholder="אימות סיסמה חדשה"
    required
>


<button
    type="submit"
    name="resetBtn"
>
שינוי סיסמה
</button>


<?php

}

?>


<?php

if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}

?>


<a href="login.php" class="back-link">
חזרה להתחברות
</a>


</form>


</body>

</html>
