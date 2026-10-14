<?php
// פתיחת Session אם עדיין לא נפתחה
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// אם המשתמש לא מחובר מעבירים אותו לעמוד ההתחברות
if (!isset($_SESSION["userid"])) {
    header("Location: login.php");
    exit();
}

// לעמוד יכולים להיכנס רק לקוח role=0 או סטייליסט role=2
if (!isset($_SESSION["role"]) || ($_SESSION["role"] != 0 && $_SESSION["role"] != 2)) {
    header("Location: home.php");
    exit();
}

// שמירת התפקיד של המשתמש המחובר
$role = $_SESSION["role"];

// קבצים לחיבור למסד הנתונים ולפונקציות העזר
require("dbconnection.php");
require("functions.php");

// פתיחת החיבור למסד הנתונים
$con = OpenCon();
$gender = "women";
$userid = (int)$_SESSION["userid"];

// משתנים שמשמשים את הסינון וההתאמה
$msg = "";
$selectedCategory = "";
$matchFor = "";
$profileMissing = false;
$wrongGender = false;
$matchMode = false;

$height = "";
$shoulder = "";
$chest = "";
$waist = "";
$hips = "";
$inseam = "";
$shoeSize = "";
$budget = "";

// הפרש מקסימלי שמותר בין מידת המשתמש למידת המוצר
$measurementDifference = 5;

// בנעליים מותר הפרש של מידה אחת
$shoeDifference = 1;

$matchProductsResult = false;

// קבלת הבחירה: התאמה לעצמי או למישהו אחר
if (isset($_POST["matchFor"])) {
    $matchFor = $_POST["matchFor"];
}

if ($matchFor != "self" && $matchFor != "other") {
    $matchFor = "";
}

// קבלת הקטגוריה שנבחרה בטופס
if (isset($_POST["selectedCategory"])) {
    $selectedCategory = $_POST["selectedCategory"];
}

// קבלת התקציב, אם המשתמש הזין תקציב
if (isset($_POST["budget"])) {
    $budget = $_POST["budget"];
}

// הפונקציה מחזירה את סוג הקטגוריה כדי לדעת אילו מידות צריך לבדוק
$categoryType = getCategoryType($selectedCategory);

// בדיקת המידות בפרופיל מיד אחרי בחירת "התאמה לפי המידות שלי"
if ($role == 0 && $matchFor == "self") {

    // שליפת המגדר והמידות השמורות בפרופיל של הלקוח
    $profileResult = mysqli_query($con, "
        SELECT gender,
               height_cm,
               chest_cm,
               waist_cm,
               hips_cm,
               inseam_cm,
               shoe_size
        FROM users
        WHERE userid='$userid'
    ");

    // אם השאילתה הצליחה ונמצאה רשומה של המשתמש
    if ($profileResult && mysqli_num_rows($profileResult) > 0) {

        // הפיכת הרשומה שהתקבלה למערך כדי להשתמש בשדות שלה
        $profileRow = mysqli_fetch_array($profileResult);

        // לפני השימוש במידות בודקים שהמגדר בפרופיל מתאים לקולקציה
        if ($profileRow["gender"] == "") {
            $profileMissing = true;
        } elseif ($profileRow["gender"] != $gender) {
            $wrongGender = true;
            $profileMissing = true;
        } else {
            $height = $profileRow["height_cm"];
            $chest = $profileRow["chest_cm"];
            $waist = $profileRow["waist_cm"];
            $hips = $profileRow["hips_cm"];
            $inseam = $profileRow["inseam_cm"];
            $shoeSize = $profileRow["shoe_size"];

            if (
                $height == "" ||
                $chest == "" ||
                $waist == "" ||
                $hips == "" ||
                $inseam == "" ||
                $shoeSize == ""
            ) {
                $profileMissing = true;
            }
        }

    } else {

        $profileMissing = true;
        $msg = "חסרות מידות בפרופיל. יש לעדכן את המידות לפני ביצוע התאמה.";
    }
}


// הוספה למועדפים
if ($role == 0 && isset($_POST["addFavorite"])) {
    $productid = (int)$_POST["productid"];

    // הוספת המוצר לטבלת המועדפים של המשתמש
    $ok = mysqli_query($con, "
        INSERT INTO favorites(userid,productid)
        VALUES('$userid','$productid')
    ");

    if ($ok) {
        $msg = "המוצר נוסף למועדפים.";
    } else {
        $msg = "אירעה שגיאה בהוספת המוצר למועדפים.";
    }
}

// הסרה מהמועדפים
if ($role == 0 && isset($_POST["removeFavorite"])) {
    $productid = (int)$_POST["productid"];

    // מחיקת המוצר מטבלת המועדפים של המשתמש
    $ok = mysqli_query($con, "
        DELETE FROM favorites
        WHERE userid='$userid' AND productid='$productid'
    ");

    if ($ok) {
        $msg = "המוצר הוסר מהמועדפים.";
    } else {
        $msg = "אירעה שגיאה בהסרת המוצר מהמועדפים.";
    }
}

// הוספה לסל
if ($role == 0 && isset($_POST["addToCart"])) {
    $productid = (int)$_POST["productid"];
    $sizeid = (int)$_POST["sizeid"];
    // הפונקציה מוסיפה את המוצר והמידה שנבחרה לסל
    $msg = addCartItem($con, $userid, $productid, $sizeid);
}

// מציאת התאמה
if ($role == 0 && isset($_POST["findMatch"])) {

    // מתחילים מצב התאמה רק אחרי לחיצה על כפתור מציאת התאמה
    $matchMode = true;

    if ($matchFor == "") {
        $msg = "יש לבחור למי מיועדת ההתאמה.";
        $matchMode = false;
    }

    if ($matchFor == "self" && $profileMissing) {
        $msg = "חסרות מידות בפרופיל. יש לעדכן את המידות לפני ביצוע התאמה.";
        $matchMode = false;
    }

    // התאמה למישהו אחר - קבלת המידות מהטופס
    if ($matchFor == "other") {
        if (isset($_POST["height"])) $height = $_POST["height"];
        if (isset($_POST["chest"])) $chest = $_POST["chest"];
        if (isset($_POST["waist"])) $waist = $_POST["waist"];
        if (isset($_POST["hips"])) $hips = $_POST["hips"];
        if (isset($_POST["inseam"])) $inseam = $_POST["inseam"];
        if (isset($_POST["shoeSize"])) $shoeSize = $_POST["shoeSize"];
    }

    // בדיקה אם חסרות מידות שנדרשות לקטגוריה שנבחרה
    $missingMeasurements = false;

    if ($selectedCategory == "") {
        $msg = "יש לבחור קטגוריה תחילה.";
        $matchMode = false;
    } elseif ($categoryType == "upper") {
        if ($height == "" || $chest == "") {
            $missingMeasurements = true;
        }
    } elseif ($categoryType == "pants") {
        if ($height == "" || $waist == "" || $hips == "" || $inseam == "") {
            $missingMeasurements = true;
        }
    } elseif ($categoryType == "skirt") {
        if ($height == "" || $waist == "" || $hips == "") {
            $missingMeasurements = true;
        }
    } elseif ($categoryType == "dress") {
        if ($height == "" || $chest == "" || $waist == "" || $hips == "") {
            $missingMeasurements = true;
        }
    } elseif ($categoryType == "shoes") {
        if ($shoeSize == "") {
            $missingMeasurements = true;
        }
    }

    if ($missingMeasurements) {

        if ($matchFor == "self") {
            $msg = "חסרות מידות בפרופיל. יש לעדכן את המידות בפרופיל.";
            $profileMissing = true;
        } else {
            $msg = "יש למלא את כל המידות הנדרשות.";
        }

        $matchMode = false;

    // בדיקה שכל המידות שהוזנו הן מספרים
    } elseif (
        ($height != "" && !is_numeric($height)) ||
        ($chest != "" && !is_numeric($chest)) ||
        ($waist != "" && !is_numeric($waist)) ||
        ($hips != "" && !is_numeric($hips)) ||
        ($inseam != "" && !is_numeric($inseam)) ||
        ($shoeSize != "" && !is_numeric($shoeSize))
    ) {
        $msg = "יש להזין מידות במספרים.";
        $matchMode = false;
    } elseif ($budget != "" && !is_numeric($budget)) {
        $msg = "יש להזין תקציב במספרים.";
        $matchMode = false;
    }

    // אם כל הבדיקות עברו אפשר לחפש מוצרים מתאימים
    if ($matchMode) {

        // אם הוזן תקציב מחפשים רק מוצרים שמחירם עד התקציב
        if ($budget != "") {
            $matchSql = "
                SELECT *
                FROM products
                WHERE gender='$gender'
                AND category='$selectedCategory'
                AND amount>0
                AND price<='$budget'
                ORDER BY productid DESC
            ";
        } else {

            // אם לא הוזן תקציב מחפשים לפי מגדר וקטגוריה בלבד
            $matchSql = "
                SELECT *
                FROM products
                WHERE gender='$gender'
                AND category='$selectedCategory'
                AND amount>0
                ORDER BY productid DESC
            ";
        }

        // ביצוע שאילתת המוצרים המתאימים ושמירת התוצאה
        $matchProductsResult = mysqli_query($con, $matchSql);
    }
}

// מוצרים להצגה רגילה, גם כשלא מבצעים התאמה
if ($selectedCategory != "") {

    // אם נבחרה קטגוריה מציגים רק מוצרים מאותה קטגוריה
    $productSql = "
        SELECT *
        FROM products
        WHERE gender='$gender'
        AND category='$selectedCategory'
        ORDER BY productid DESC
    ";
} else {

    // אם לא נבחרה קטגוריה מציגים את כל מוצרי הקולקציה
    $productSql = "
        SELECT *
        FROM products
        WHERE gender='$gender'
        ORDER BY productid DESC
    ";
}

// ביצוע השאילתה של המוצרים שיוצגו בעמוד
$productsResult = mysqli_query($con, $productSql);

require("nav.php");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>קולקציית נשים - WearPair</title>

<style>
body {
    margin:0;
    min-height:100vh;
    background:linear-gradient(135deg,#000,#171717,#292929);
    color:silver;
    font-family:Arial;
}

.page {
    max-width:1350px;
    margin:30px auto;
    padding:20px;
}

.page-header {
    text-align:center;
    margin-bottom:25px;
}

.page-header h1 {
    margin:0;
    color:white;
    font:42px Georgia;
}

.message {
    color:#ffcc66;
    font-weight:bold;
    text-align:center;
}

.empty {
    color:#ffcc66;
    font-weight:bold;
    text-align:center;
    padding:30px;
    background:#111;
    border:1px solid #444;
    border-radius:10px;
}

.no-sizes,.out-stock {
    color:#ff9999;
    font-weight:bold;
    text-align:center;
}

.match-filter {
    max-width:1050px;
    margin:25px auto 35px;
    padding:22px;
    background:#111;
    border:1px solid #444;
    border-radius:14px;
}


.match-choice {
    max-width:1050px;
    margin:25px auto;
    padding:22px;
    background:#111;
    border:1px solid #444;
    border-radius:14px;
    text-align:center;
}

.match-choice-buttons {
    display:flex;
    gap:12px;
    margin-top:15px;
}

.match-choice-buttons button {
    flex:1;
    padding:12px;
    border:1px solid #777;
    border-radius:7px;
    background:white;
    color:black;
    font-weight:bold;
    cursor:pointer;
}

.match-note {
    text-align:center;
    color:#ccc;
    margin-bottom:16px;
}

.edit-profile-button {
    display:inline-block;
    margin:10px 0 18px;
    padding:10px 16px;
    background:white;
    color:black;
    border-radius:7px;
    text-decoration:none;
    font-weight:bold;
}

h2 {
    color:white;
    text-align:center;
}

.filter-grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:14px;
}

label {
    display:block;
    margin-bottom:6px;
    color:white;
    font-weight:bold;
}

input,select {
    width:100%;
    padding:10px;
    box-sizing:border-box;
    background:black;
    color:white;
    border:1px solid #555;
    border-radius:6px;
}

.filter-buttons,.card-actions {
    display:flex;
    gap:8px;
    margin-top:12px;
}

.filter-buttons button,.card-actions button{
    flex:1;
    padding:10px;
        border:1px solid transparent;
    border-radius:7px;
    font-weight:bold;
    cursor:pointer;
}

.filter-buttons button,.cart-button ,.favorite-add{
    background:white;
    color:black;
}

.favorite-remove {
    background:#790000;
    color:white;
}

.products {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:22px;
}

.product-card {
    padding:16px;
    background:#151515;
    border:1px solid #444;
    border-radius:13px;
    text-align:center;
}

.product-card img {
    width:100%;
    height:250px;
    object-fit:contain;
    background:#222;
    border-radius:8px;
}

.product-card h3 {
    min-height:48px;
    color:white;
}

.price,.recommended-size {
    color:white;
    font-size:18px;
    font-weight:bold;
}

.match-score {
    display:inline-block;
    padding:8px 12px;
    background:white;
    color:black;
    border-radius:7px;
    font-weight:bold;
}

@media screen and (max-width:1050px) {
    .filter-grid,.products {
        grid-template-columns:repeat(2,1fr);
    }
}

@media screen and (max-width:600px) {
    .filter-grid,.products {
        grid-template-columns:1fr;
    }

    .filter-buttons,
    .match-choice-buttons {
        flex-direction:column;
    }
}
</style>
</head>

<body>
<div class="page">

<div class="page-header">
<h1>קולקציית נשים</h1>
</div>

<?php
if ($msg != "" && !$profileMissing) {
    echo "<p class='message'>$msg</p>";
}
?>

<?php
// התאמה מוצגת רק ללקוח
if ($role == 0) {
?>

<div class="match-choice">
<h2>למי מיועדת ההתאמה?</h2>

<form method="post" class="match-choice-buttons">
<button type="submit" name="matchFor" value="self">התאמה לפי המידות שלי</button>
<button type="submit" name="matchFor" value="other">התאמה למישהו אחר</button>
</form>
</div>

<?php
if ($matchFor != "") {
?>

<div class="match-filter">

<?php
if ($matchFor == "self") {
?>
<h2>התאמה לפי המידות שלי</h2>

<?php
if ($wrongGender) {
?>
<p class="match-note">
המגדר בפרופיל שלך הוא גבר. להתאמה בקולקציית נשים ניתן לבחור "התאמה למישהו אחר".
</p>

<?php
} elseif ($profileMissing) {
?>
<p class="match-note">
חסרות מידות בפרופיל שלך. יש לעדכן את המידות לפני בחירת קטגוריה.
</p>

<a class="edit-profile-button" href="editprofile.php">
עדכון המידות בפרופיל
</a>

<?php
} else {
?>
<p class="match-note">
המידות נמצאו בפרופיל. עכשיו אפשר לבחור קטגוריה ותקציב.
</p>
<?php
}
?>

<?php
} else {
?>
<h2>התאמה למישהו אחר</h2>
<p class="match-note">
בחרי קטגוריה והזיני את המידות המתאימות.
</p>
<?php
}
?>

<?php
if (!($matchFor == "self" && $profileMissing)) {
?>

<form method="post">

<input type="hidden" name="matchFor" value="<?php echo $matchFor; ?>">

<div class="filter-grid">

<div>
<label>קטגוריה</label>
<select name="selectedCategory">
<option value="" <?php if ($selectedCategory == "") echo "selected"; ?>>כל המוצרים</option>
<option value="blouses" <?php if ($selectedCategory == "blouses") echo "selected"; ?>>חולצות אלגנטיות</option>
<option value="shirts" <?php if ($selectedCategory == "shirts") echo "selected"; ?>>חולצות מכופתרות</option>
<option value="tshirts" <?php if ($selectedCategory == "tshirts") echo "selected"; ?>>חולצות טי שירט</option>
<option value="jackets" <?php if ($selectedCategory == "jackets") echo "selected"; ?>>ז'קטים</option>
<option value="pants" <?php if ($selectedCategory == "pants") echo "selected"; ?>>מכנסיים</option>
<option value="skirts" <?php if ($selectedCategory == "skirts") echo "selected"; ?>>חצאיות</option>
<option value="dresses" <?php if ($selectedCategory == "dresses") echo "selected"; ?>>שמלות</option>
<option value="shoes" <?php if ($selectedCategory == "shoes") echo "selected"; ?>>נעליים</option>
<option value="bags" <?php if ($selectedCategory == "bags") echo "selected"; ?>>תיקים</option>
<option value="jewelry" <?php if ($selectedCategory == "jewelry") echo "selected"; ?>>תכשיטים</option>
<option value="accessories" <?php if ($selectedCategory == "accessories") echo "selected"; ?>>אביזרים</option>
<option value="watches" <?php if ($selectedCategory == "watches") echo "selected"; ?>>שעונים</option>
</select>
</div>

<?php

if ($matchFor == "other") {

    if ($categoryType == "upper" || $categoryType == "pants" || $categoryType == "skirt" || $categoryType == "dress") {
?>
<div>
<label>גובה</label>
<input type="text" name="height" value="<?php echo $height; ?>" required>
</div>
<?php
    }

    if ($categoryType == "upper" || $categoryType == "dress") {
?>
<div>
<label>חזה</label>
<input type="text" name="chest" value="<?php echo $chest; ?>" required>
</div>
<?php
    }

    if ($categoryType == "pants" || $categoryType == "skirt" || $categoryType == "dress") {
?>
<div>
<label>מותניים</label>
<input type="text" name="waist" value="<?php echo $waist; ?>" required>
</div>

<div>
<label>ירכיים</label>
<input type="text" name="hips" value="<?php echo $hips; ?>" required>
</div>
<?php
    }

    if ($categoryType == "pants") {
?>
<div>
<label>אורך רגל פנימי</label>
<input type="text" name="inseam" value="<?php echo $inseam; ?>" required>
</div>
<?php
    }

    if ($categoryType == "shoes") {
?>
<div>
<label>מידת נעליים</label>
<input type="text" name="shoeSize" value="<?php echo $shoeSize; ?>" required>
</div>
<?php
    }
}

?>
<div>
<label>תקציב מקסימלי (₪)</label>
<input type="text" name="budget" value="<?php echo $budget; ?>" placeholder="לא חובה">
</div>
<?php
?>

</div>

<div class="filter-buttons">

<?php
if ($matchFor == "self") {
?>
<button type="submit" name="findMatch">מציאת התאמה</button>
<?php
} else {

    if ($selectedCategory == "") {
?>
<button type="submit" name="chooseCategory" formnovalidate>המשך להזנת מידות</button>
<?php
    } else {
?>
<button type="submit" name="chooseCategory" formnovalidate>שינוי קטגוריה</button>
<button type="submit" name="findMatch">מציאת התאמה</button>
<?php
    }
}
?>

</div>

</form>

<?php
}
?>

</div>

<?php
}
?>

<?php
}
?>

<?php
if ($matchMode) {
?>

<h2>המוצרים המתאימים ביותר</h2>
<div class="products">

<?php
$foundMatch = false;

// מעבר על כל המוצרים שהתקבלו משאילתת ההתאמה
while ($product = mysqli_fetch_array($matchProductsResult)) {
    $productid = $product["productid"];
    $productCategoryType = getCategoryType($product["category"]);

    // לכל מוצר שולפים את המידות שיש להן מלאי
    $sizesResult = mysqli_query($con, "
        SELECT *
        FROM product_sizes
        WHERE productid='$productid'
        AND stock>0
        ORDER BY sizeid
    ");

    $bestScore = -1;
    $bestSizeId = 0;
    $bestSizeName = "";
    $bestStock = 0;

    // מעבר על כל המידות של המוצר כדי למצוא את המידה עם אחוז ההתאמה הגבוה ביותר
    while ($size = mysqli_fetch_array($sizesResult)) {
        $totalChecks = 0;
        $passedChecks = 0;
        $score = 0;

        if ($productCategoryType == "accessory") {
            $score = 100;
        } elseif ($productCategoryType == "upper") {
            $totalChecks = 2;

            if ($size["chest"] != "" && abs($chest - $size["chest"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["height"] != "" && abs($height - $size["height"]) <= $measurementDifference) {
                $passedChecks++;
            }
        } elseif ($productCategoryType == "pants") {
            $totalChecks = 4;

            if ($size["waist"] != "" && abs($waist - $size["waist"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["hips"] != "" && abs($hips - $size["hips"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["inseam"] != "" && abs($inseam - $size["inseam"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["height"] != "" && abs($height - $size["height"]) <= $measurementDifference) {
                $passedChecks++;
            }
        } elseif ($productCategoryType == "skirt") {
            $totalChecks = 3;

            if ($size["waist"] != "" && abs($waist - $size["waist"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["hips"] != "" && abs($hips - $size["hips"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["height"] != "" && abs($height - $size["height"]) <= $measurementDifference) {
                $passedChecks++;
            }
        } elseif ($productCategoryType == "dress") {
            $totalChecks = 4;

            if ($size["chest"] != "" && abs($chest - $size["chest"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["waist"] != "" && abs($waist - $size["waist"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["hips"] != "" && abs($hips - $size["hips"]) <= $measurementDifference) {
                $passedChecks++;
            }

            if ($size["height"] != "" && abs($height - $size["height"]) <= $measurementDifference) {
                $passedChecks++;
            }
        } elseif ($productCategoryType == "shoes") {
            $totalChecks = 1;

            if ($size["shoe_size"] != "" && abs($shoeSize - $size["shoe_size"]) <= $shoeDifference) {
                $passedChecks++;
            }
        }

        // חישוב אחוז ההתאמה: כמה בדיקות עברו מתוך כל הבדיקות
        if ($productCategoryType != "accessory" && $totalChecks > 0) {
            $score = round(($passedChecks / $totalChecks) * 100);
        }

        // אם המידה הנוכחית קיבלה ציון גבוה יותר שומרים אותה כמידה המומלצת
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestSizeId = $size["sizeid"];
            $bestSizeName = $size["sizename"];
            $bestStock = $size["stock"];
        }
    }

    // מציגים את המוצר רק אם ההתאמה הטובה ביותר שלו היא לפחות 80%
    if ($bestScore >= 80) {
        $foundMatch = true;
        $isFavorite = favoriteExists($con, $userid, $productid);
?>

<div class="product-card">
<img src="images/<?php echo $product["pic"]; ?>" alt="<?php echo $product["pname"]; ?>">
<h3><?php echo $product["pname"]; ?></h3>
<p class="price"><?php echo $product["price"]; ?> ₪</p>
<p><span class="match-score"><?php echo $bestScore; ?>% התאמה</span></p>
<p class="recommended-size">מידה מומלצת: <?php echo $bestSizeName; ?></p>
<p>קטגוריה: <?php echo getCategoryName($product["category"]); ?></p>
<p>מלאי: <?php echo $bestStock; ?></p>

<form method="post">
<input type="hidden" name="productid" value="<?php echo $productid; ?>">
<input type="hidden" name="sizeid" value="<?php echo $bestSizeId; ?>">
<input type="hidden" name="selectedCategory" value="<?php echo $selectedCategory; ?>">
<input type="hidden" name="matchFor" value="<?php echo $matchFor; ?>">
<input type="hidden" name="height" value="<?php echo $height; ?>">
<input type="hidden" name="shoulder" value="<?php echo $shoulder; ?>">
<input type="hidden" name="chest" value="<?php echo $chest; ?>">
<input type="hidden" name="waist" value="<?php echo $waist; ?>">
<input type="hidden" name="hips" value="<?php echo $hips; ?>">
<input type="hidden" name="inseam" value="<?php echo $inseam; ?>">
<input type="hidden" name="shoeSize" value="<?php echo $shoeSize; ?>">
<input type="hidden" name="budget" value="<?php echo $budget; ?>">
<input type="hidden" name="findMatch" value="1">

<div class="card-actions">
<button class="cart-button" type="submit" name="addToCart">הוספה לעגלה</button>

<?php
if ($isFavorite) {
?>
<button class="favorite-remove" type="submit" name="removeFavorite">הסרה מהמועדפים</button>
<?php
} else {
?>
<button class="favorite-add" type="submit" name="addFavorite">הוספה למועדפים</button>
<?php
}
?>

</div>
</form>
</div>

<?php
    }
}

if (!$foundMatch) {
    echo "<div class='empty'>לא נמצאו מוצרים עם התאמה של 80% ומעלה.</div>";
}
?>

</div>

<?php
} else {
?>

<h2>מוצרים</h2>
<div class="products">

<?php
$foundProducts = false;

// מעבר על כל המוצרים שהשאילתה הרגילה החזירה והצגתם אחד אחד
while ($product = mysqli_fetch_array($productsResult)) {
    $foundProducts = true;
    $productid = $product["productid"];
    $totalStock = 0;

    // שליפת המלאי של כל המידות של המוצר כדי לחשב מלאי כולל
    $stockResult = mysqli_query($con, "
        SELECT stock
        FROM product_sizes
        WHERE productid='$productid'
    ");

    // מעבר על כל שורות המלאי וחיבור הכמויות למלאי הכולל
    while ($stockRow = mysqli_fetch_array($stockResult)) {
        $totalStock = $totalStock + $stockRow["stock"];
    }

    // שליפת המידות הזמינות בלבד, כלומר מידות שהמלאי שלהן גדול מ-0
    $sizeResult = mysqli_query($con, "
        SELECT sizeid,sizename,stock
        FROM product_sizes
        WHERE productid='$productid'
        AND stock>0
        ORDER BY sizeid
    ");

    $isFavorite = false;

    // בדיקת מועדפים נחוצה רק ללקוח, לא לסטייליסט
    if ($role == 0) {
        $isFavorite = favoriteExists($con, $userid, $productid);
    }
?>

<div class="product-card">
<img src="images/<?php echo $product["pic"]; ?>" alt="<?php echo $product["pname"]; ?>">
<h3><?php echo $product["pname"]; ?></h3>
<p class="price"><?php echo $product["price"]; ?> ₪</p>
<p>קטגוריה: <?php echo getCategoryName($product["category"]); ?></p>

<?php
if ($totalStock > 0) {
?>
<p>מלאי כולל: <?php echo $totalStock; ?></p>
<?php
} else {
?>
<p class="out-stock">אזל מהמלאי</p>
<?php
}

// לקוח יכול לבחור מידה, לקנות ולהוסיף למועדפים
if ($role == 0) {

    if (mysqli_num_rows($sizeResult) > 0) {
?>

<form method="post">
<input type="hidden" name="productid" value="<?php echo $productid; ?>">
<input type="hidden" name="selectedCategory" value="<?php echo $selectedCategory; ?>">
<input type="hidden" name="matchFor" value="<?php echo $matchFor; ?>">

<select name="sizeid" required>
<option value="">בחירת מידה</option>

<?php
// מעבר על כל המידות הזמינות והוספת כל מידה לרשימת הבחירה
while ($size = mysqli_fetch_array($sizeResult)) {
?>
<option value="<?php echo $size["sizeid"]; ?>">
<?php echo $size["sizename"]; ?> - מלאי: <?php echo $size["stock"]; ?>
</option>
<?php
}
?>

</select>

<div class="card-actions">
<button class="cart-button" type="submit" name="addToCart">הוספה לעגלה</button>

<?php
if ($isFavorite) {
?>
<button class="favorite-remove" type="submit" name="removeFavorite" formnovalidate>הסרה מהמועדפים</button>
<?php
} else {
?>
<button class="favorite-add" type="submit" name="addFavorite" formnovalidate>הוספה למועדפים</button>
<?php
}
?>

</div>
</form>

<?php
    } else {
?>

<p class="no-sizes">אזל מהמלאי</p>

<form method="post">
<input type="hidden" name="productid" value="<?php echo $productid; ?>">
<input type="hidden" name="selectedCategory" value="<?php echo $selectedCategory; ?>">
<input type="hidden" name="matchFor" value="<?php echo $matchFor; ?>">

<div class="card-actions">

<?php
if ($isFavorite) {
?>
<button class="favorite-remove" type="submit" name="removeFavorite">הסרה מהמועדפים</button>
<?php
} else {
?>
<button class="favorite-add" type="submit" name="addFavorite">הוספה למועדפים</button>
<?php
}
?>

</div>
</form>

<?php
    }

// סטייליסט לא יכול לקנות, הוא יכול רק לצפות במוצרים ובמידות
} elseif ($role == 2) {

    if (mysqli_num_rows($sizeResult) > 0) {
?>

<p><b>מידות זמינות:</b></p>

<?php
// מעבר על המידות והצגתן לסטייליסט בלי כפתורי קנייה
while ($size = mysqli_fetch_array($sizeResult)) {
?>
<p><?php echo $size["sizename"]; ?> - מלאי: <?php echo $size["stock"]; ?></p>
<?php
}
    } else {
?>
<p class="no-sizes">אזל מהמלאי</p>
<?php
    }
}
?>

</div>

<?php
}

if (!$foundProducts) {
    echo "<div class='empty'>לא נמצאו מוצרים.</div>";
}
?>

</div>

<?php
}
?>

</div>
</body>
</html>