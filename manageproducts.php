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

$editId = 0;
$editProduct = null;

$filterGender = "";
$filterCategory = "";
$filterStock = "";


// קבלת הסינון שנבחר
if (isset($_GET["filterGender"])) {
    $filterGender = $_GET["filterGender"];
}

if (isset($_GET["filterCategory"])) {
    $filterCategory = $_GET["filterCategory"];
}

if (isset($_GET["filterStock"])) {
    $filterStock = $_GET["filterStock"];
}


// טעינת מוצר לעריכה
if (isset($_POST["loadProduct"])) {
    $editId = (int)$_POST["editid"];
}


// הוספת מוצר חדש
if (isset($_POST["adminAdd"])) {

    $productName = $_POST["pname"];
    $productPrice = $_POST["price"];
    $productGender = $_POST["gender"];
    $productCategory = $_POST["category"];
    $productPic = $_FILES["picture"]["name"];

    if ( $productName == "" || $productPrice == "" ||
        $productGender == "" || $productCategory == "" || $productPic == ""
    ) {
        $msg = "יש למלא את כל השדות.";

    } elseif ( !is_numeric($productPrice) || $productPrice <= 0 ) {
        $msg = "יש להזין מחיר תקין.";

    } else {
        $targetPath = "images/" . $productPic;

        if (  move_uploaded_file( $_FILES["picture"]["tmp_name"], $targetPath ) ) {

            $addProduct = mysqli_query(
                $conn,
                "INSERT INTO products
                (
                    pname,
                    price,
                    pic,
                    amount,
                    gender,
                    category
                )
                VALUES
                (
                    '$productName',
                    '$productPrice',
                    '$productPic',
                    0,
                    '$productGender',
                    '$productCategory'
                )"
            );

            if ($addProduct) {
                $msg = "המוצר נוסף בהצלחה.";

            } else {
                $msg = "שגיאה בהוספת המוצר.";
            }

        } else {
            $msg = "שגיאה בהעלאת התמונה.";
        }
    }
}


// עדכון מוצר קיים
if (isset($_POST["adminUpdate"])) {

    $productId = (int)$_POST["productid"];

    $productName = $_POST["pname"];
    $productPrice = $_POST["price"];
    $productGender = $_POST["gender"];
    $productCategory = $_POST["category"];

    $newPic = $_FILES["picture"]["name"];

    $editId = $productId;


    if ( $productName == "" || $productPrice == "" ||
        $productGender == "" || $productCategory == ""
    ) {
        $msg = "יש למלא את כל השדות.";

    } elseif ( !is_numeric($productPrice) || $productPrice <= 0 ) {
        $msg = "יש להזין מחיר תקין.";

    } else {
        // אם נבחרה תמונה חדשה מעדכנים גם אותה
        if ($newPic != "") {
            $targetPath = "images/" . $newPic;

            if ( move_uploaded_file( $_FILES["picture"]["tmp_name"], $targetPath ) ) {
                $updateProduct = mysqli_query(
                    $conn,
                    "UPDATE products
                     SET pname='$productName',
                         price='$productPrice',
                         pic='$newPic',
                         gender='$productGender',
                         category='$productCategory'
                     WHERE productid='$productId'"
                );

            } else {
                $updateProduct = false;
                $msg = "שגיאה בהעלאת התמונה.";
            }

        } else {
            // אם לא נבחרה תמונה חדשה משאירים את התמונה הקיימת
            $updateProduct = mysqli_query(
                $conn,
                "UPDATE products
                 SET pname='$productName',
                     price='$productPrice',
                     gender='$productGender',
                     category='$productCategory'
                 WHERE productid='$productId'"
            );
        }


        if ($updateProduct) {
            $msg = "המוצר עודכן בהצלחה.";

        } elseif ($msg == "") {
            $msg = "שגיאה בעדכון המוצר.";
        }
    }
}


// מחיקה - המוצר נשאר במסד הנתונים והמלאי שלו מתאפס
if (isset($_POST["adminOutOfStock"])) {
    $productId = (int)$_POST["productid"];


    // איפוס המלאי של כל המידות
    $sizesOk = mysqli_query(
        $conn,
        "UPDATE product_sizes
         SET stock=0
         WHERE productid='$productId'"
    );


    // איפוס המלאי הכולל של המוצר
    $productOk = mysqli_query(
        $conn,
        "UPDATE products
         SET amount=0
         WHERE productid='$productId'"
    );


    if ($sizesOk && $productOk) {
        $msg = "המוצר הוסר מהמלאי בהצלחה.";
        $editId = 0;

    } else {
        $msg = "שגיאה במחיקת המוצר.";
    }
}


// קבלת המוצר שנבחר לעריכה
if ($editId > 0) {
    $editResult = mysqli_query(
        $conn,
        "SELECT *
         FROM products
         WHERE productid='$editId'"
    );

    if ( $editResult && mysqli_num_rows($editResult) > 0 ) {
        $editProduct = mysqli_fetch_array($editResult);
    }
}


$nameValue = "";
$priceValue = "";
$genderValue = "";
$categoryValue = "";


// הצגת הנתונים של המוצר בטופס העריכה
if ($editProduct != null) {
    $nameValue = $editProduct["pname"];
    $priceValue = $editProduct["price"];
    $genderValue = $editProduct["gender"];
    $categoryValue = $editProduct["category"];
}

// קבלת רשימת המוצרים לפי הסינון
$sql = "SELECT *
        FROM products
        WHERE 1=1";

if ($filterGender != "") {
    $sql .= " AND gender='$filterGender'";
}

if ($filterCategory != "") {
    $sql .= " AND category='$filterCategory'";
}

if ($filterStock == "in") {
    $sql .= " AND amount>0";
}

if ($filterStock == "out") {
    $sql .= " AND amount=0";
}

$sql .= " ORDER BY productid DESC";

$productsResult = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>ניהול מוצרים - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    direction: rtl;
}

.products-page {
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
}

h1,
h2 {
    color: white;
    text-align: center;
}

.product-form,
.products-list,
.filter-box {
    margin: 20px auto;
    padding: 20px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
}

.product-form {
    max-width: 700px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

label {
    display: block;
    margin-top: 8px;
    color: white;
}

input,
select {
    width: 100%;
    padding: 9px;
    margin-top: 6px;
    background: black;
    color: white;
    border: 1px solid #555;
    border-radius: 7px;
    box-sizing: border-box;
}

button {
    padding: 9px 14px;
    background: #eee;
    color: #111;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.full-button {
    width: 100%;
    margin-top: 15px;
}

.deleteBtn {
    background: #4b0000;
    color: white;
}

.msgText {
    color: #ffcc66;
    font-weight: bold;
    text-align: center;
}

.current-pic {
    width: 120px;
    height: 120px;
    object-fit: contain;
    background: white;
    margin-top: 10px;
}

.small-pic {
    width: 70px;
    height: 70px;
    object-fit: contain;
    background: white;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.filter-button {
    width: 100%;
    margin-top: 15px;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    margin-top: 15px;
    border-collapse: collapse;
}

th,
td {
    padding: 9px;
    border: 1px solid #444;
    text-align: center;
    font-size: 13px;
}

th {
    background: black;
    color: white;
}

.table-actions {
    white-space: nowrap;
}

.table-actions form {
    display: inline-block;
    margin: 2px;
}

.empty {
    padding: 30px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
}

@media screen and (max-width: 700px) {

    .form-grid,
    .filter-grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<div class="products-page">

<h1>ניהול מוצרים</h1>


<?php

if ($msg != "") {
    echo "<p class='msgText'>$msg</p>";
}

?>


<div class="product-form">

<h2>

<?php

if ($editProduct == null) {
    echo "הוספת מוצר";
} else {
    echo "עדכון מוצר";
}

?>

</h2>


<form method="post"
      enctype="multipart/form-data">


<?php

if ($editProduct != null) {

?>

<input type="hidden"
       name="productid"
       value="<?php echo $editProduct["productid"]; ?>">

<?php
}

?>


<div class="form-grid">


<div>

<label>שם המוצר</label>

<input type="text"
       name="pname"
       value="<?php echo $nameValue; ?>"
       required>

</div>


<div>

<label>מחיר</label>

<input type="number"
       name="price"
       min="1"
       value="<?php echo $priceValue; ?>"
       required>

</div>


<div>

<label>מגדר</label>

<select name="gender" required>

<option value="">
בחירת מגדר
</option>

<option value="women"
<?php
if ($genderValue == "women") {
    echo "selected";
}
?>>
נשים
</option>

<option value="men"
<?php
if ($genderValue == "men") {
    echo "selected";
}
?>>
גברים
</option>

</select>

</div>


<div>

<label>קטגוריה</label>

<select name="category" required>

<option value="">
בחירת קטגוריה
</option>


<option value="blouses"
<?php if ($categoryValue == "blouses") { echo "selected"; } ?>>
חולצות אלגנטיות
</option>

<option value="shirts"
<?php if ($categoryValue == "shirts") { echo "selected"; } ?>>
חולצות מכופתרות
</option>

<option value="tshirts"
<?php if ($categoryValue == "tshirts") { echo "selected"; } ?>>
חולצות טי שירט
</option>

<option value="jackets"
<?php if ($categoryValue == "jackets") { echo "selected"; } ?>>
ז'קטים
</option>

<option value="pants"
<?php if ($categoryValue == "pants") { echo "selected"; } ?>>
מכנסיים
</option>

<option value="skirts"
<?php if ($categoryValue == "skirts") { echo "selected"; } ?>>
חצאיות
</option>

<option value="dresses"
<?php if ($categoryValue == "dresses") { echo "selected"; } ?>>
שמלות
</option>

<option value="shoes"
<?php if ($categoryValue == "shoes") { echo "selected"; } ?>>
נעליים
</option>

<option value="bags"
<?php if ($categoryValue == "bags") { echo "selected"; } ?>>
תיקים
</option>

<option value="jewelry"
<?php if ($categoryValue == "jewelry") { echo "selected"; } ?>>
תכשיטים
</option>

<option value="accessories"
<?php if ($categoryValue == "accessories") { echo "selected"; } ?>>
אביזרים
</option>

<option value="watches"
<?php if ($categoryValue == "watches") { echo "selected"; } ?>>
שעונים
</option>

</select>

</div>


<div>

<?php

if ($editProduct == null) {

?>

<label>תמונה</label>

<input type="file"
       name="picture"
       accept=".png,.jpg,.jpeg,.webp"
       required>

<?php

} else {

?>

<label>תמונה חדשה - לא חובה</label>

<input type="file"
       name="picture"
       accept=".png,.jpg,.jpeg,.webp">


<?php

if ($editProduct["pic"] != "") {

?>

<img class="current-pic"
     src="images/<?php echo $editProduct["pic"]; ?>"
     alt="<?php echo $editProduct["pname"]; ?>">

<?php
}

}

?>

</div>


</div>


<?php

if ($editProduct == null) {

?>

<button class="full-button"
        type="submit"
        name="adminAdd">
הוספת מוצר
</button>

<?php

} else {

?>

<button class="full-button"
        type="submit"
        name="adminUpdate">
עדכון מוצר
</button>

<?php
}

?>


</form>

</div>


<div class="products-list">

<h2>רשימת מוצרים</h2>


<div class="filter-box">

<form method="get">

<div class="filter-grid">


<div>

<label>מגדר</label>

<select name="filterGender">

<option value="">
כל המגדרים
</option>

<option value="women"
<?php
if ($filterGender == "women") {
    echo "selected";
}
?>>
נשים
</option>

<option value="men"
<?php
if ($filterGender == "men") {
    echo "selected";
}
?>>
גברים
</option>

</select>

</div>


<div>

<label>קטגוריה</label>

<select name="filterCategory">

<option value="">
כל הקטגוריות
</option>

<option value="blouses"
<?php if ($filterCategory == "blouses") { echo "selected"; } ?>>
חולצות אלגנטיות
</option>

<option value="shirts"
<?php if ($filterCategory == "shirts") { echo "selected"; } ?>>
חולצות מכופתרות
</option>

<option value="tshirts"
<?php if ($filterCategory == "tshirts") { echo "selected"; } ?>>
חולצות טי שירט
</option>

<option value="jackets"
<?php if ($filterCategory == "jackets") { echo "selected"; } ?>>
ז'קטים
</option>

<option value="pants"
<?php if ($filterCategory == "pants") { echo "selected"; } ?>>
מכנסיים
</option>

<option value="skirts"
<?php if ($filterCategory == "skirts") { echo "selected"; } ?>>
חצאיות
</option>

<option value="dresses"
<?php if ($filterCategory == "dresses") { echo "selected"; } ?>>
שמלות
</option>

<option value="shoes"
<?php if ($filterCategory == "shoes") { echo "selected"; } ?>>
נעליים
</option>

<option value="bags"
<?php if ($filterCategory == "bags") { echo "selected"; } ?>>
תיקים
</option>

<option value="jewelry"
<?php if ($filterCategory == "jewelry") { echo "selected"; } ?>>
תכשיטים
</option>

<option value="accessories"
<?php if ($filterCategory == "accessories") { echo "selected"; } ?>>
אביזרים
</option>

<option value="watches"
<?php if ($filterCategory == "watches") { echo "selected"; } ?>>
שעונים
</option>

</select>

</div>


<div>

<label>מלאי</label>

<select name="filterStock">

<option value="">
כל המוצרים
</option>

<option value="in"
<?php
if ($filterStock == "in") {
    echo "selected";
}
?>>
במלאי
</option>

<option value="out"
<?php
if ($filterStock == "out") {
    echo "selected";
}
?>>
אזל מהמלאי
</option>

</select>

</div>


</div>


<button class="filter-button"
        type="submit">
סינון
</button>

</form>

</div>


<div class="table-wrap">

<table>

<tr>
<th>תמונה</th>
<th>שם</th>
<th>מחיר</th>
<th>מלאי כולל</th>
<th>מגדר</th>
<th>קטגוריה</th>
<th>פעולות</th>
</tr>


<?php

if ($productsResult && mysqli_num_rows($productsResult) > 0) {
    while ( $productRow = mysqli_fetch_array($productsResult) ) {

?>
<tr>
<td>

<?php

if ($productRow["pic"] != "") {

?>

<img class="small-pic"
     src="images/<?php echo $productRow["pic"]; ?>"
     alt="<?php echo $productRow["pname"]; ?>">

<?php
}

?>

</td>


<td>
<?php echo $productRow["pname"]; ?>
</td>


<td>
<?php echo $productRow["price"]; ?> ₪
</td>


<td>
<?php echo $productRow["amount"]; ?>
</td>


<td>

<?php

if ($productRow["gender"] == "women") {
    echo "נשים";
} else {
    echo "גברים";
}

?>

</td>


<td>
<?php echo getCategoryName($productRow["category"]); ?>
</td>


<td class="table-actions">


<form method="post">

<input type="hidden"
       name="editid"
       value="<?php echo $productRow["productid"]; ?>">

<button type="submit"
        name="loadProduct">
עריכה
</button>

</form>


<form method="get"
      action="manageproductsizes.php">

<input type="hidden"
       name="productid"
       value="<?php echo $productRow["productid"]; ?>">

<button type="submit">
מידות
</button>

</form>


<form method="post">

<input type="hidden"
       name="productid"
       value="<?php echo $productRow["productid"]; ?>">

<button class="deleteBtn"
        type="submit"
        name="adminOutOfStock"
        onclick="return confirm('למחוק את המוצר? המלאי שלו יעודכן ל-0.')">
מחיקה
</button>

</form>


</td>

</tr>


<?php

    }

} else {

?>

<tr>

<td colspan="7">
לא נמצאו מוצרים.
</td>

</tr>

<?php
}

?>
</table>
</div>
</div>
</div>
</body>
</html>
