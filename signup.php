<?php

require("dbconnection.php");
require("functions.php");

$con = OpenCon();

$msg = "";


// הרשמת לקוח חדש
if (isset($_POST["signupBtn"])) {

    $password = $_POST["password"];
    $confirmPassword = $_POST["password2"];

    $firstname = $_POST["firstname"];
    $lastname = $_POST["lastname"];
    $phone = $_POST["phone"];
    $email = $_POST["email"];
    $address = $_POST["address"];
    $birthdate = $_POST["birthdate"];


    // בדיקה שהסיסמאות זהות
    if ($password != $confirmPassword) {
        $msg = "הסיסמאות אינן תואמות.";

    } else {
        // בדיקה אם האימייל כבר קיים
        $checkEmail = mysqli_query($con,
            "SELECT userid
             FROM users
             WHERE email='$email'"
        );


        if ($checkEmail && mysqli_num_rows($checkEmail) > 0) {
            $msg = "האימייל כבר קיים במערכת.";

        } else {

            // הוספת הלקוח למסד הנתונים
            $result = mysqli_query($con,
                "INSERT INTO users
                (
                    pass1,
                    pass2,
                    pass3,
                    fname,
                    lname,
                    phone,
                    email,
                    address,
                    birthdate,
                    role,
                    blocked
                )
                VALUES
                (
                    '$password',
                    NULL,
                    NULL,
                    '$firstname',
                    '$lastname',
                    '$phone',
                    '$email',
                    '$address',
                    '$birthdate',
                    0,
                    0
                )"
            );


            if ($result) {
                // שליחת אימייל ברוכים הבאים
                $message = "<h2>שלום $firstname</h2>";
                $message .= "<p>תודה שנרשמת ל-WearPair.</p>";
                $message .= "<p>אנחנו שמחים שהצטרפת אלינו.</p>";

                sendWearPairMail( $email, "WearPair - ברוכים הבאים", $message );

                header("Location: login.php");
                exit();

            } else {
                $msg = "אירעה שגיאה בהרשמה.";
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

<meta
    name="viewport"
    content="width=device-width,initial-scale=1.0"
>

<title>הרשמה - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.signup-form {
    width: 90%;
    max-width: 500px;
    margin: 60px auto;
    padding: 25px;
    background: #111;
    border: 1px solid #444;
    text-align: center;
}

h2 {
    color: white;
}

.input-row {
    display: flex;
    gap: 10px;
}

.input-row input {
    width: 50%;
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

input[type="date"] {
    color-scheme: dark;
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
    margin-top: 15px;
    color: silver;
    text-decoration: none;
}

.login-link:hover {
    color: white;
}

@media screen and (max-width:600px) {

    .input-row {
        display: block;
    }

    .input-row input {
        width: 100%;
    }
}

</style>
</head>

<body>

<form method="post" class="signup-form">

<h2>הרשמה</h2>


<?php
if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}
?>


<div class="input-row">

<input
    type="text"
    name="firstname"
    placeholder="שם פרטי"
    required
>

<input
    type="text"
    name="lastname"
    placeholder="שם משפחה"
    required
>

</div>


<input
    type="email"
    name="email"
    placeholder="אימייל"
    required
>


<label>תאריך לידה</label>

<input
    type="date"
    name="birthdate"
    required
>


<input
    type="tel"
    name="phone"
    placeholder="מספר טלפון"
    required
>


<input
    type="text"
    name="address"
    placeholder="כתובת"
    required
>


<input
    type="password"
    name="password"
    placeholder="סיסמה"
    required
>


<input
    type="password"
    name="password2"
    placeholder="אימות סיסמה"
    required
>


<button
    type="submit"
    name="signupBtn"
>
הרשמה
</button>

<a
    href="login.php"
    class="login-link"
>
כבר יש לך חשבון? התחברות
</a>

</form>
</body>
</html>
