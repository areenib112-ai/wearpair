<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require("dbconnection.php");


// בדיקה שהמשתמש מחובר
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


// קבלת פרטי המשתמש
$result = mysqli_query($con,
    "SELECT *
     FROM users
     WHERE userid='$userid'
     LIMIT 1"
);


if (!$result || mysqli_num_rows($result) == 0) {

    CloseCon($con);

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}


$userRow = mysqli_fetch_array($result);


$genderText = "לא צוין";

if ($userRow["gender"] == "women") {
    $genderText = "אישה";
} elseif ($userRow["gender"] == "men") {
    $genderText = "גבר";
}



// התאמת שם ותמונה של מבנה הגוף לפי הערך השמור בפרופיל
$bodyTypeText = "לא צוין";
$bodyTypeImage = "";

if ($userRow["body_type"] == "hourglass") {
    $bodyTypeText = "שעון חול";
    $bodyTypeImage = "BodyType/hourglassshape.jpg";
} elseif ($userRow["body_type"] == "pear") {
    $bodyTypeText = "אגס";
    $bodyTypeImage = "BodyType/pear shape.jpg";
} elseif ($userRow["body_type"] == "rectangle") {
    $bodyTypeText = "מלבן";

    if ($userRow["gender"] == "men") {
        $bodyTypeImage = "BodyType/male rectangle.jpg";
    } else {
        $bodyTypeImage = "BodyType/rectangle shape.webp";
    }
} elseif ($userRow["body_type"] == "inverted_triangle") {
    $bodyTypeText = "משולש הפוך";

    if ($userRow["gender"] == "men") {
        $bodyTypeImage = "BodyType/male inverted triangle.jpg";
    } else {
        $bodyTypeImage = "BodyType/inverted triangle.jpg";
    }
} elseif ($userRow["body_type"] == "apple") {
    $bodyTypeText = "תפוח";
    $bodyTypeImage = "BodyType/apple body.jpg";
} elseif ($userRow["body_type"] == "triangle") {
    $bodyTypeText = "משולש";
    $bodyTypeImage = "BodyType/male triangle.jpg";
} elseif ($userRow["body_type"] == "oval") {
    $bodyTypeText = "אליפסה";
    $bodyTypeImage = "BodyType/male oval body.webp";
}


require("nav.php");

?>


<!DOCTYPE html>

<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>הפרופיל שלי - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.profile {
    width: 90%;
    max-width: 750px;
    margin: 60px auto;
    padding: 25px;
    background: #111;
    border: 1px solid #444;
    box-sizing: border-box;
}

h1,
h2 {
    color: white;
    text-align: center;
}

h1 {
    margin-top: 0;
}

h2 {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #444;
}

.row {
    display: flex;
    justify-content: space-between;
    padding: 12px 5px;
    border-bottom: 1px solid #333;
}

.label {
    color: silver;
}

.value {
    color: white;
    font-weight: bold;
}

.body-image {
    display: block;
    width: 170px;
    max-height: 220px;
    object-fit: contain;
    margin: 15px auto;
    background: white;
    border: 1px solid #555;
}

.edit-btn {
    display: block;
    width: 180px;
    margin: 25px auto 0;
    padding: 10px;
    background: white;
    color: black;
    text-align: center;
    text-decoration: none;
    font-weight: bold;
}

@media screen and (max-width:600px) {

    .row {
        display: block;
    }

    .value {
        margin-top: 5px;
    }
}

</style>

</head>


<body>


<div class="profile">


<h1>הפרופיל שלי</h1>

<div class="row">
<div class="label">שם מלא</div>
<div class="value">
<?php echo $userRow["fname"] . " " . $userRow["lname"]; ?>
</div>
</div>


<div class="row">
<div class="label">אימייל</div>
<div class="value"><?php echo $userRow["email"]; ?></div>
</div>


<div class="row">
<div class="label">מספר טלפון</div>
<div class="value"><?php echo $userRow["phone"]; ?></div>
</div>


<div class="row">
<div class="label">כתובת</div>
<div class="value"><?php echo $userRow["address"]; ?></div>
</div>


<div class="row">
<div class="label">תאריך לידה</div>
<div class="value"><?php echo $userRow["birthdate"]; ?></div>
</div>


<h2>המידות שלי</h2>


<div class="row">
<div class="label">מין</div>
<div class="value"><?php echo $genderText; ?></div>
</div>


<div class="row">
<div class="label">גובה</div>
<div class="value">
<?php
if ($userRow["height_cm"] != "") {
    echo $userRow["height_cm"] . " ס\"מ";
} else {
    echo "לא צוין";
}
?>
</div>
</div>


<div class="row">
<div class="label">כתפיים</div>
<div class="value">
<?php
if ($userRow["shoulder_cm"] != "") {
    echo $userRow["shoulder_cm"] . " ס\"מ";
} else {
    echo "לא צוין";
}
?>
</div>
</div>


<div class="row">
<div class="label">חזה</div>
<div class="value">
<?php
if ($userRow["chest_cm"] != "") {
    echo $userRow["chest_cm"] . " ס\"מ";
} else {
    echo "לא צוין";
}
?>
</div>
</div>


<div class="row">
<div class="label">מותניים</div>
<div class="value">
<?php
if ($userRow["waist_cm"] != "") {
    echo $userRow["waist_cm"] . " ס\"מ";
} else {
    echo "לא צוין";
}
?>
</div>
</div>


<div class="row">
<div class="label">ירכיים</div>
<div class="value">
<?php
if ($userRow["hips_cm"] != "") {
    echo $userRow["hips_cm"] . " ס\"מ";
} else {
    echo "לא צוין";
}
?>
</div>
</div>


<div class="row">
<div class="label">אורך רגל פנימי</div>
<div class="value">
<?php
if ($userRow["inseam_cm"] != "") {
    echo $userRow["inseam_cm"] . " ס\"מ";
} else {
    echo "לא צוין";
}
?>
</div>
</div>

<div class="row">
<div class="label">מידת נעליים</div>
<div class="value">
<?php
if ($userRow["shoe_size"] != "") {
    echo $userRow["shoe_size"];
} else {
    echo "לא צוין";
}
?>
</div>
</div>

<div class="row">
<div class="label">מבנה גוף</div>
<div class="value"><?php echo $bodyTypeText; ?></div>
</div>


<?php
if ($bodyTypeImage != "") {
?>
<img
    src="<?php echo $bodyTypeImage; ?>"
    class="body-image"
    alt="מבנה גוף"
>
<?php
}
?>


<a href="editprofile.php" class="edit-btn">עריכת פרופיל</a>
</div>
</body>
</html>
