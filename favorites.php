<?php

require('nav.php');
require('dbconnection.php');


// בדיקה שהמשתמש מחובר
if (!isset($_SESSION["userid"])) {
    echo "<p style='text-align:center;color:white;margin-top:70px;'>יש להתחבר תחילה.</p>";
    exit();
}

// רק לקוחות יכולים להיכנס לעמוד
if (!isset($_SESSION["role"]) || $_SESSION["role"] != 0) {
    echo "<p style='text-align:center;color:white;margin-top:70px;'>העמוד מיועד ללקוחות בלבד.</p>";
    exit();
}

$con = OpenCon();

// בדיקת חיבור למסד הנתונים
if (!$con) {
    echo "החיבור למסד הנתונים נכשל.";
    exit();
}

$userid = (int)$_SESSION["userid"];
$message = "";

// הסרת מוצר מהמועדפים
if (isset($_POST["removeFavorite"])) {

    $productid = (int)$_POST["productid"];

    $remove = mysqli_query($con, "
        DELETE FROM favorites
        WHERE userid='$userid'
        AND productid='$productid'
    ");

    if ($remove) {
        $message = "המוצר הוסר מהמועדפים.";

    } else {
        $message = "אירעה שגיאה בהסרת המוצר מהמועדפים.";
    }
}

// הוספת מוצר מהמועדפים לסל
if (isset($_POST["favoriteAddCart"])) {

    $productid = (int)$_POST["productid"];
    $sizeid = 0;

    if (isset($_POST["sizeid"])) {
        $sizeid = (int)$_POST["sizeid"];
    }

    // בדיקה שנבחרה מידה
    if ($sizeid == 0) {
        $message = "יש לבחור מידה.";

    } else {
        // קבלת המלאי של המידה שנבחרה
        $sizeResult = mysqli_query($con, "
            SELECT stock
            FROM product_sizes
            WHERE sizeid='$sizeid'
            AND productid='$productid'
        ");


        if (!$sizeResult || mysqli_num_rows($sizeResult) == 0) {
            $message = "המידה אינה זמינה.";

        } else {
            $sizeRow = mysqli_fetch_array($sizeResult);
            $stock = $sizeRow["stock"];


            if ($stock <= 0) {
                $message = "המוצר אזל מהמלאי.";

            } else {
                // בדיקה אם המוצר כבר נמצא בסל
                $cartResult = mysqli_query($con, "
                    SELECT amount
                    FROM cart
                    WHERE userid='$userid'
                    AND productid='$productid'
                    AND sizeid='$sizeid'
                ");


                if ($cartResult && mysqli_num_rows($cartResult) > 0) {
                    $cartRow = mysqli_fetch_array($cartResult);

                    $newAmount = $cartRow["amount"] + 1;

                    // לא ניתן לעבור את כמות המלאי
                    if ($newAmount > $stock) {
                        $message = "הגעת לכמות המלאי הזמינה.";

                    } else {
                        // הגדלת הכמות בסל
                        $update = mysqli_query($con, "
                            UPDATE cart
                            SET amount='$newAmount'
                            WHERE userid='$userid'
                            AND productid='$productid'
                            AND sizeid='$sizeid'
                        ");


                        if ($update) {
                            $message = "כמות המוצר בסל עודכנה.";

                        } else {
                            $message = "אירעה שגיאה בעדכון הסל.";
                        }
                    }

                } else {
                    // הוספת מוצר חדש לסל
                    $add = mysqli_query($con, "
                        INSERT INTO cart
                        (userid,productid,sizeid,amount)
                        VALUES
                        ('$userid','$productid','$sizeid',1)
                    ");


                    if ($add) {
                        $message = "המוצר נוסף לסל.";

                    } else {
                        $message = "אירעה שגיאה בהוספת המוצר לסל.";
                    }
                }
            }
        }
    }
}

// קבלת כל המוצרים שהמשתמש הוסיף למועדפים
$favoritesResult = mysqli_query($con, "
    SELECT favorites.productid,
           products.pname,
           products.price,
           products.pic,
           products.category
    FROM favorites
    INNER JOIN products
    ON favorites.productid=products.productid
    WHERE favorites.userid='$userid'
    ORDER BY favorites.productid DESC
");

?>


<!DOCTYPE html>

<html lang="he" dir="rtl">


<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1.0">

<title>המועדפים שלי - WearPair</title>


<style>

body {
    margin:0;
    background:#151515;
    color:silver;
    font-family:Arial;
    text-align:center;
}

.favorites-page {
    max-width:1200px;
    margin: auto;
}

h1 {
    color:white;
    font-family:Georgia;
}

.message {
    color:#ffcc66;
}

.favorites-container {
    display:flex;
    justify-content:center;
    flex-wrap:wrap;
    gap:25px;
    margin-top:25px;
}

.favorite-card {
    width:260px;
    background:#111;
    border:1px solid #333;
    border-radius:12px;
    overflow:hidden;
    text-align:right;
}

.favorite-image {
    width:100%;
    height:270px;
    object-fit:contain;/* הצגת כל תמונת המוצר בתוך המסגרת בלי לחתוך או לעוות אותה. */
    background:#222;
}

.favorite-info {
    padding:15px;
}

.favorite-name {
    min-height:48px;
    color:white;
    font-size:20px;
}

.favorite-info p {
    margin:7px 0;
}

.favorite-buttons {
    display:flex;
    gap:8px;
    margin-top:12px;
}

.cart-form {
    flex:1;
}

.size-select {
    width:100%;
    padding:9px;
    margin-bottom:8px;
    box-sizing:border-box;
    background:black;
    color:white;
    border-radius:7px;
}

.add-button,.remove-button {
    padding:10px;
    border:0;
    border-radius:7px;
    cursor:pointer;
}

.add-button {
    width:100%;
    background:white;
    color:black;
}

.remove-button {
    background:#4b0000;
    color:white;
}

.out-stock {
    display:block;
    flex:1;
    padding:10px;
    color:#ff9999;
    border:1px solid #5d2525;
    border-radius:7px;
    font-weight:bold;
    text-align:center;
}

.stock {
    color:#ccc;
}

.empty {
    margin-top:40px;
    color:#ccc;
    font-size:18px;
}

@media(max-width:700px) {

    .favorite-card {
        width:90%;
    }
}
</style>
</head>

<body>


<div class="favorites-page">
<h1>המועדפים שלי</h1>

<?php
if ($message != "") {
    echo "<p class='message'>$message</p>";
}
?>


<div class="favorites-container">
<?php

$found = false;


// מעבר על כל המוצרים שנמצאים במועדפים
while ( $favoritesResult && $row = mysqli_fetch_array($favoritesResult)) {

    $found = true;
    $totalStock = 0;

    // קבלת כל המידות של המוצר לצורך חישוב המלאי הכולל
    $sizeResult = mysqli_query($con, "
        SELECT sizeid,sizename,stock
        FROM product_sizes
        WHERE productid='" . $row["productid"] . "'
        ORDER BY sizeid
    ");

    // חישוב המלאי הכולל של המוצר
    while ( $sizeResult && $sizeRow = mysqli_fetch_array($sizeResult) ) {
        $totalStock =  $totalStock + $sizeRow["stock"];
    }

    // קבלת המידות שיש להן מלאי
    $availableSizes = mysqli_query($con, "
        SELECT sizeid,sizename,stock
        FROM product_sizes
        WHERE productid='" . $row["productid"] . "'
        AND stock>0
        ORDER BY sizeid
    ");

?>


<div class="favorite-card">


<?php
// הצגת תמונת המוצר
if ($row["pic"] != "") {
?>

<img class="favorite-image"
     src="images/<?php echo $row["pic"]; ?>"
     alt="<?php echo $row["pname"]; ?>">


<?php

} else {
    echo "<div class='favorite-image'></div>";
}

?>


<div class="favorite-info">


<div class="favorite-name">

<?php echo $row["pname"]; ?>

</div>


<p>
מחיר:
<?php echo $row["price"]; ?> ₪
</p>


<p>
קטגוריה:
<?php echo $row["category"]; ?>
</p>


<?php
// הצגת המלאי הכולל
if ($totalStock > 0) {
?>


<p class="stock">
מלאי כולל:
<?php echo $totalStock; ?>

</p>

<?php
} else {
?>


<p class="out-stock">
אזל מהמלאי
</p>


<?php
}
?>

<div class="favorite-buttons">

<?php
// הצגת בחירת מידה רק אם יש מידות זמינות
if ( $availableSizes && mysqli_num_rows($availableSizes) > 0) {
?>


<form class="cart-form"
      method="post">


<input type="hidden"
       name="productid"
       value="<?php echo $row["productid"]; ?>">


<select class="size-select"
        name="sizeid"
        required>


<option value="">
בחירת מידה
</option>


<?php
// מעבר ישיר על המידות הזמינות
while ( $sizeRow = mysqli_fetch_array($availableSizes)) {
?>

<option value="<?php echo $sizeRow["sizeid"]; ?>">

<?php

echo $sizeRow["sizename"];
echo " - מלאי: ";
echo $sizeRow["stock"];

?>

</option>

<?php
}
?>

</select>

<button class="add-button"
        type="submit"
        name="favoriteAddCart">

הוספה לסל
</button>
</form>

<?php
}
?>

<form method="post">

<input type="hidden"
       name="productid"
       value="<?php echo $row["productid"]; ?>">


<button class="remove-button"
        type="submit"
        name="removeFavorite"
        onclick="return confirm('להסיר את המוצר מהמועדפים?')">

 הסרה

</button>
</form>

</div>
</div>
</div>

<?php
}

if (!$found) {
    echo "<div class='empty'>אין לך מוצרים במועדפים.</div>";
}
?>

</div>
</div>
</body>
</html>
