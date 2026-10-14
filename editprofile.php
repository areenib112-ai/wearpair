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
$msg = "";
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


// שמירת השינויים בפרופיל
if (isset($_POST["saveBtn"])) {

    // קבלת הפרטים האישיים
    $fname = trim($_POST["fname"]);
    $lname = trim($_POST["lname"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $birthdate = $_POST["birthdate"];

    // קבלת המידות ופרטי ההתאמה
    $gender = $_POST["gender"];
    $height = trim($_POST["height"]);
    $shoulder = trim($_POST["shoulder"]);
    $chest = trim($_POST["chest"]);
    $waist = trim($_POST["waist"]);
    $hips = trim($_POST["hips"]);
    $inseam = trim($_POST["inseam"]);
    $shoeSize = trim($_POST["shoeSize"]);

    $bodyType = "";

    if (isset($_POST["bodyType"])) {
        $bodyType = $_POST["bodyType"];
    }


    // בדיקת הפרטים האישיים
    if (  $fname == "" || $lname == "" ||  $phone == "" ||
        $address == "" ||  $birthdate == ""  ) {

        $msg = "יש למלא את כל הפרטים האישיים.";

    } elseif ( !is_numeric($phone) ||  strlen($phone) < 9 ||  strlen($phone) > 10) {

        $msg = "יש להזין מספר טלפון תקין.";

    } elseif ( $gender != "women" &&  $gender != "men" ) {

        $msg = "יש לבחור מין תקין.";

    } elseif (
        ($height != "" && !is_numeric($height)) ||
        ($shoulder != "" && !is_numeric($shoulder)) ||
        ($chest != "" && !is_numeric($chest)) ||
        ($waist != "" && !is_numeric($waist)) ||
        ($hips != "" && !is_numeric($hips)) ||
        ($inseam != "" && !is_numeric($inseam)) ||
        ($shoeSize != "" && !is_numeric($shoeSize))
    ) {

        $msg = "המידות חייבות להיות מספרים.";

    } elseif (
        $gender == "women" &&
        $bodyType != "" &&
        $bodyType != "hourglass" &&
        $bodyType != "pear" &&
        $bodyType != "rectangle" &&
        $bodyType != "inverted_triangle" &&
        $bodyType != "apple"
    ) {

        $msg = "יש לבחור מבנה גוף מתאים לנשים.";

    } elseif (
        $gender == "men" &&
        $bodyType != "" &&
        $bodyType != "inverted_triangle" &&
        $bodyType != "rectangle" &&
        $bodyType != "triangle" &&
        $bodyType != "oval"
    ) {

        $msg = "יש לבחור מבנה גוף מתאים לגברים.";

    } else {

        // הכנת הערכים לשמירה במסד הנתונים:
        // אם השדה ריק נשמר NULL
        $genderSql = "NULL";
        $heightSql = "NULL";
        $shoulderSql = "NULL";
        $chestSql = "NULL";
        $waistSql = "NULL";
        $hipsSql = "NULL";
        $inseamSql = "NULL";
        $shoeSql = "NULL";
        $bodyTypeSql = "NULL";


        if ($gender != "") {
            $genderSql = "'$gender'";
        }

        if ($height != "") {
            $heightSql = "'$height'";
        }

        if ($shoulder != "") {
            $shoulderSql = "'$shoulder'";
        }

        if ($chest != "") {
            $chestSql = "'$chest'";
        }

        if ($waist != "") {
            $waistSql = "'$waist'";
        }

        if ($hips != "") {
            $hipsSql = "'$hips'";
        }

        if ($inseam != "") {
            $inseamSql = "'$inseam'";
        }

        if ($shoeSize != "") {
            $shoeSql = "'$shoeSize'";
        }

        if ($bodyType != "") {
            $bodyTypeSql = "'$bodyType'";
        }


        // עדכון פרטי הלקוח, המידות ומבנה הגוף
        $update = mysqli_query($con,
            "UPDATE users
             SET fname='$fname',
                 lname='$lname',
                 phone='$phone',
                 address='$address',
                 birthdate='$birthdate',
                 gender=$genderSql,
                 height_cm=$heightSql,
                 shoulder_cm=$shoulderSql,
                 chest_cm=$chestSql,
                 waist_cm=$waistSql,
                 hips_cm=$hipsSql,
                 inseam_cm=$inseamSql,
                 shoe_size=$shoeSql,
                 body_type=$bodyTypeSql
             WHERE userid='$userid'"
        );


        if ($update) {

            $msg = "הפרטים עודכנו בהצלחה.";

            // עדכון השם שמופיע בתפריט
            $_SESSION["fname"] = $fname;

        } else {

            $msg = "אירעה שגיאה בעדכון הפרטים.";
        }
    }
}


// קבלת הפרטים מחדש לאחר העדכון
$result = mysqli_query($con,
    "SELECT *
     FROM users
     WHERE userid='$userid'
     LIMIT 1"
);

$userRow = mysqli_fetch_array($result);


require("nav.php");

?>


<!DOCTYPE html>

<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>עריכת פרופיל - WearPair</title>


<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial;
    direction: rtl;
}

.edit-form {
    width: 90%;
    max-width: 850px;
    margin: 60px auto;
    padding: 25px;
    background: #111;
    border: 1px solid #444;
    box-sizing: border-box;
}

h2,
h3 {
    color: white;
    text-align: center;
}

h2 {
    margin-top: 0;
}

h3 {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #444;
}

label {
    display: block;
    margin-bottom: 5px;
    color: white;
}

input,
select {
    width: 100%;
    padding: 10px;
    margin-bottom: 14px;
    box-sizing: border-box;
    background: black;
    color: white;
    border: 1px solid #555;
}

input[type="date"] {
    color-scheme: dark;
}

.measurements-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.body-title {
    margin: 20px 0 10px;
    color: white;
    font-weight: bold;
}

.body-grid {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 12px;
}

.body-option {
    display: block;
    padding: 10px;
    margin: 0;
    background: #1b1b1b;
    border: 1px solid #444;
    text-align: center;
    cursor: pointer;
}

.body-option input {
    width: auto;
    margin: 0 0 8px;
}

.body-option img {
    display: block;
    width: 100%;
    height: 180px;
    object-fit: contain;
    margin-bottom: 8px;
    background: white;
}

.body-option span {
    color: white;
}

.help {
    margin: 4px 0 15px;
    color: #bbbbbb;
    font-size: 14px;
}

button {
    width: 100%;
    margin-top: 25px;
    padding: 10px;
    background: white;
    color: black;
    border: none;
    font-weight: bold;
    cursor: pointer;
}

.message {
    color: #ffcc66;
    text-align: center;
    font-weight: bold;
}

.back-link {
    display: block;
    margin-top: 15px;
    color: silver;
    text-align: center;
    text-decoration: none;
}

.back-link:hover {
    color: white;
}

@media screen and (max-width:700px) {

    .measurements-grid,
    .body-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>


<body>


<form method="post" class="edit-form">


<h2>עריכת פרופיל</h2>


<?php

if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}

?>


<h3>פרטים אישיים</h3>


<label>שם פרטי</label>
<input
    type="text"
    name="fname"
    value="<?php echo $userRow["fname"]; ?>"
    required
>


<label>שם משפחה</label>
<input
    type="text"
    name="lname"
    value="<?php echo $userRow["lname"]; ?>"
    required
>


<label>מספר טלפון</label>
<input
    type="text"
    name="phone"
    value="<?php echo $userRow["phone"]; ?>"
    required
>


<label>כתובת</label>
<input
    type="text"
    name="address"
    value="<?php echo $userRow["address"]; ?>"
    required
>


<label>תאריך לידה</label>
<input
    type="date"
    name="birthdate"
    value="<?php echo $userRow["birthdate"]; ?>"
    required
>

<h3>מידות ופרטי התאמה</h3>

<p class="help">
המידות נשמרות בפרופיל ומשמשות להתאמת מוצרים. ניתן לעדכן אותן בכל זמן.
</p>


<label>מין</label>
<select name="gender" required>

<option value="">בחירה</option>

<option value="women"
<?php
if ($userRow["gender"] == "women") {
    echo "selected";
}
?>
>
אישה
</option>

<option value="men"
<?php
if ($userRow["gender"] == "men") {
    echo "selected";
}
?>
>
גבר
</option>

</select>


<div class="measurements-grid">

<div>
<label>גובה בס"מ</label>
<input
    type="number"
    name="height"
    step="1"
    value="<?php echo $userRow["height_cm"]; ?>"
>
</div>

<div>
<label>כתפיים בס"מ</label>
<input
    type="number"
    name="shoulder"
    step="1"
    value="<?php echo $userRow["shoulder_cm"]; ?>"
>
</div>

<div>
<label>חזה בס"מ</label>
<input
    type="number"
    name="chest"
    step="1"
    value="<?php echo $userRow["chest_cm"]; ?>"
>
</div>

<div>
<label>מותניים בס"מ</label>
<input
    type="number"
    name="waist"
    step="1"
    value="<?php echo $userRow["waist_cm"]; ?>"
>
</div>

<div>
<label>ירכיים בס"מ</label>
<input
    type="number"
    name="hips"
    step="1"
    value="<?php echo $userRow["hips_cm"]; ?>"
>
</div>

<div>
<label>אורך רגל פנימי בס"מ</label>
<input
    type="number"
    name="inseam"
    step="1"
    value="<?php echo $userRow["inseam_cm"]; ?>"
>
</div>

<div>
<label>מידת נעליים</label>
<input
    type="number"
    name="shoeSize"
    step="1"
    value="<?php echo $userRow["shoe_size"]; ?>"
>
</div>


</div>


<div class="body-title">מבנה גוף לנשים</div>

<div class="body-grid">

<label class="body-option">
<input type="radio" name="bodyType" value="hourglass"
<?php if ($userRow["body_type"] == "hourglass") { echo "checked"; } ?>
>
<img src="BodyType/hourglassshape.jpg" alt="שעון חול">
<span>שעון חול</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="pear"
<?php if ($userRow["body_type"] == "pear") { echo "checked"; } ?>
>
<img src="BodyType/pear shape.jpg" alt="אגס">
<span>אגס</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="rectangle"
<?php if ($userRow["body_type"] == "rectangle" && $userRow["gender"] == "women") { echo "checked"; } ?>
>
<img src="BodyType/rectangle shape.webp" alt="מלבן">
<span>מלבן</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="inverted_triangle"
<?php if ($userRow["body_type"] == "inverted_triangle" && $userRow["gender"] == "women") { echo "checked"; } ?>
>
<img src="BodyType/inverted triangle.jpg" alt="משולש הפוך">
<span>משולש הפוך</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="apple"
<?php if ($userRow["body_type"] == "apple") { echo "checked"; } ?>
>
<img src="BodyType/apple body.jpg" alt="תפוח">
<span>תפוח</span>
</label>

</div>


<div class="body-title">מבנה גוף לגברים</div>

<div class="body-grid">

<label class="body-option">
<input type="radio" name="bodyType" value="inverted_triangle"
<?php if ($userRow["body_type"] == "inverted_triangle" && $userRow["gender"] == "men") { echo "checked"; } ?>
>
<img src="BodyType/male inverted triangle.jpg" alt="משולש הפוך">
<span>משולש הפוך</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="rectangle"
<?php if ($userRow["body_type"] == "rectangle" && $userRow["gender"] == "men") { echo "checked"; } ?>
>
<img src="BodyType/male rectangle.jpg" alt="מלבן">
<span>מלבן</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="triangle"
<?php if ($userRow["body_type"] == "triangle") { echo "checked"; } ?>
>
<img src="BodyType/male triangle.jpg" alt="משולש">
<span>משולש</span>
</label>

<label class="body-option">
<input type="radio" name="bodyType" value="oval"
<?php if ($userRow["body_type"] == "oval") { echo "checked"; } ?>
>
<img src="BodyType/male oval body.webp" alt="אליפסה">
<span>אליפסה</span>
</label>
</div>

<button type="submit" name="saveBtn">
שמירת שינויים
</button>

<a href="Profile.php" class="back-link">
חזרה לפרופיל
</a>
</form>
</body>
</html>
