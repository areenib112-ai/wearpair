<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");
require("functions.php");

$con = OpenCon();

$msg = "";


// יצירת סיסמה זמנית ושליחתה למשתמש
if (isset($_POST["sendBtn"])) {
    $email = trim($_POST["email"]);

    if ($email == "") {
        $msg = "יש להזין כתובת אימייל.";

    } else {

        // חיפוש המשתמש לפי האימייל
        $result = mysqli_query($con,
            "SELECT userid,fname,email,blocked
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

            // משתמש שנחסם על ידי המנהל לא יכול לאפס סיסמה
            if ($user["blocked"] == 2) {
                $msg =  "החשבון נחסם על ידי המנהל. יש לפנות למנהל.";

            } else {
                $userid = $user["userid"];
                $firstname = $user["fname"];
                $userEmail = $user["email"];

                $characters = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

                $tempPass = "";


                // יצירת סיסמה זמנית באורך 6 תווים
                for ($i = 0; $i < 6; $i++) {

                    $randomIndex = rand( 0,  strlen($characters) - 1  );

                    $tempPass = $tempPass . $characters[$randomIndex];
                }

                $resetLink = "http://localhost/wearpair/resetpass.php";
                $message ="<h2>שלום $firstname</h2>";
                $message .="<p>ביקשת לאפס את הסיסמה שלך ב-WearPair.</p>";
                $message .= "<p>הסיסמה הזמנית שלך היא:</p>";
                $message .=  "<h2>$tempPass</h2>";
                $message .= "<p>יש להיכנס לקישור הבא כדי לבחור סיסמה חדשה:</p>";
                $message .= "<p><a href='$resetLink'>איפוס סיסמה</a></p>";

                // שליחת הסיסמה הזמנית באימייל
                $sent = sendWearPairMail( $userEmail, "WearPair - איפוס סיסמה",$message);

                if ($sent) {
                    // שמירת שלוש הסיסמאות האחרונות
                    $update = mysqli_query($con,
                        "UPDATE users
                         SET pass3=pass2,
                             pass2=pass1,
                             pass1='$tempPass',
                             blocked=1
                         WHERE userid='$userid'"
                    );


                    if ($update) {
                        $msg ="סיסמה זמנית נשלחה לאימייל שלך.";

                    } else {
                        $msg = "אירעה שגיאה במסד הנתונים.";
                    }

                } else {
                    $msg = "לא ניתן לשלוח את האימייל. הסיסמה לא שונתה.";
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

<title>שכחת סיסמה - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.forgot-form {
    width: 90%;
    max-width: 420px;
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
    margin: 12px 0;
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


<form method="post" class="forgot-form">


<h2>שכחת סיסמה?</h2>


<p>
יש להזין את כתובת האימייל ונשלח אליך סיסמה זמנית.
</p>


<input
    type="email"
    name="email"
    placeholder="אימייל"
    required
>


<button
    type="submit"
    name="sendBtn"
>
שליחת סיסמה זמנית
</button>


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
