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

$productid = 0;
$msg = "";

$sizeid = 0;
$sizename = "";
$shoulder = "";
$chest = "";
$waist = "";
$hips = "";
$inseam = "";
$height = "";
$shoeSize = "";
$stock = "";

$editMode = false;


// קבלת מספר המוצר
if (isset($_GET["productid"])) {
    $productid = (int)$_GET["productid"];
}

if (isset($_POST["productid"])) {
    $productid = (int)$_POST["productid"];
}

if ($productid == 0) {
    header("Location: manageproducts.php");
    exit();
}


// מחזירה NULL כאשר לא הוזן ערך של מידה
function numberOrNull($value)
{
    if ($value == "") {
        return "NULL";
    }

    return "'$value'";
}


// מחשבת מחדש את המלאי הכולל של המוצר
function updateTotalStock($conn, $productid)
{
    $result = mysqli_query(
        $conn,
        "SELECT stock
         FROM product_sizes
         WHERE productid='$productid'"
    );

    $totalStock = 0;

    if ($result) {

        while ($row = mysqli_fetch_array($result)) {
            $totalStock = $totalStock + $row["stock"];
        }
    }

    mysqli_query(
        $conn,
        "UPDATE products
         SET amount='$totalStock'
         WHERE productid='$productid'"
    );
}


// בדיקה שהמוצר קיים
$productResult = mysqli_query(
    $conn,
    "SELECT *
     FROM products
     WHERE productid='$productid'"
);

if (!$productResult || mysqli_num_rows($productResult) == 0) {
    CloseCon($conn);
    header("Location: manageproducts.php");
    exit();
}


// טעינת מידה קיימת לעריכה
if (isset($_POST["loadSize"])) {

    $sizeid = (int)$_POST["sizeid"];

    $result = mysqli_query(
        $conn,
        "SELECT *
         FROM product_sizes
         WHERE sizeid='$sizeid'
         AND productid='$productid'"
    );

    if ( $result && mysqli_num_rows($result) > 0 ) {

        $row = mysqli_fetch_array($result);

        $sizename = $row["sizename"];
        $shoulder = $row["shoulder"];
        $chest = $row["chest"];
        $waist = $row["waist"];
        $hips = $row["hips"];
        $inseam = $row["inseam"];
        $height = $row["height"];
        $shoeSize = $row["shoe_size"];
        $stock = $row["stock"];

        $editMode = true;
    }
}


// קבלת הנתונים מהטופס
if ( isset($_POST["addSize"]) || isset($_POST["updateSize"])) {

    $sizename = $_POST["sizename"];
    $shoulder = $_POST["shoulder"];
    $chest = $_POST["chest"];
    $waist = $_POST["waist"];
    $hips = $_POST["hips"];
    $inseam = $_POST["inseam"];
    $height = $_POST["height"];
    $shoeSize = $_POST["shoeSize"];
    $stock = $_POST["stock"];

    if (isset($_POST["sizeid"])) {
        $sizeid = (int)$_POST["sizeid"];
    }


    if ( $sizename == "" || $stock == "" ) {
        $msg = "יש להזין שם מידה ומלאי.";

    } elseif (!is_numeric($stock) || $stock < 0 ) {
        $msg = "יש להזין מלאי תקין.";

    } else {
        // שדות המדידה יכולים להישאר ריקים
        $shoulderSql = numberOrNull($shoulder);
        $chestSql = numberOrNull($chest);
        $waistSql = numberOrNull($waist);
        $hipsSql = numberOrNull($hips);
        $inseamSql = numberOrNull($inseam);
        $heightSql = numberOrNull($height);
        $shoeSql = numberOrNull($shoeSize);


        // הוספת מידה חדשה
        if (isset($_POST["addSize"])) {

            $save = mysqli_query(
                $conn,
                "INSERT INTO product_sizes
                (
                    productid,
                    sizename,
                    shoulder,
                    chest,
                    waist,
                    hips,
                    inseam,
                    height,
                    shoe_size,
                    stock
                )
                VALUES
                (
                    '$productid',
                    '$sizename',
                    $shoulderSql,
                    $chestSql,
                    $waistSql,
                    $hipsSql,
                    $inseamSql,
                    $heightSql,
                    $shoeSql,
                    '$stock'
                )"
            );


            if ($save) {

                updateTotalStock($conn, $productid);

                $msg = "המידה נוספה בהצלחה.";

                $sizename = "";
                $shoulder = "";
                $chest = "";
                $waist = "";
                $hips = "";
                $inseam = "";
                $height = "";
                $shoeSize = "";
                $stock = "";

            } else {

                $msg = "לא ניתן להוסיף את המידה.";
            }
        }


        // עדכון מידה קיימת
        if (isset($_POST["updateSize"])) {

            $save = mysqli_query(
                $conn,
                "UPDATE product_sizes
                 SET sizename='$sizename',
                     shoulder=$shoulderSql,
                     chest=$chestSql,
                     waist=$waistSql,
                     hips=$hipsSql,
                     inseam=$inseamSql,
                     height=$heightSql,
                     shoe_size=$shoeSql,
                     stock='$stock'
                 WHERE sizeid='$sizeid'
                 AND productid='$productid'"
            );


            if ($save) {

                updateTotalStock($conn, $productid);

                $msg = "המידה עודכנה בהצלחה.";
                $editMode = true;

            } else {

                $msg = "שגיאה בעדכון המידה.";
                $editMode = true;
            }
        }
    }
}


// מחיקת מידה
if (isset($_POST["deleteSize"])) {

    $sizeid = (int)$_POST["sizeid"];

    mysqli_query(
        $conn,
        "DELETE FROM cart
         WHERE sizeid='$sizeid'"
    );

    $delete = mysqli_query(
        $conn,
        "DELETE FROM product_sizes
         WHERE sizeid='$sizeid'
         AND productid='$productid'"
    );

    if ($delete) {
        updateTotalStock($conn, $productid);
        $msg = "המידה נמחקה בהצלחה.";

    } else {
        $msg = "שגיאה במחיקת המידה.";
    }
}


// קבלת פרטי המוצר
$productResult = mysqli_query(
    $conn,
    "SELECT *
     FROM products
     WHERE productid='$productid'"
);

$product = mysqli_fetch_array($productResult);


// קבלת כל המידות של המוצר
$sizesResult = mysqli_query(
    $conn,
    "SELECT *
     FROM product_sizes
     WHERE productid='$productid'
     ORDER BY sizeid"
);

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ניהול מידות - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    text-align: center;
    direction: rtl;
}

.sizes-page {
    max-width: 1100px;
    margin: 30px auto;
    padding: 20px;
}

h1,
h2 {
    color: white;
}

.product-info,
.size-form,
.sizes-list {
    margin: 20px auto;
    padding: 20px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
}

.product-info img {
    width: 120px;
    height: 120px;
    object-fit: contain;
    background: white;
}

.size-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    text-align: right;
}

label {
    display: block;
    margin-top: 8px;
    color: white;
}

input {
    width: 100%;
    padding: 9px;
    margin-top: 6px;
    background: black;
    color: white;
    border: 1px solid #555;
    border-radius: 7px;
    box-sizing: border-box;
}

.buttons {
    display: flex;
    gap: 8px;
    margin-top: 15px;
}

.buttons button {
    flex: 1;
}

button {
    padding: 10px;
    background: #eee;
    color: #111;
    border: none;
    border-radius: 7px;
    font-weight: bold;
    cursor: pointer;
}

.deleteBtn {
    background: #4b0000;
    color: #ffcccc;
}

.msgText {
    color: #ffcc66;
    font-weight: bold;
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
    font-size: 13px;
}

th {
    background: black;
    color: white;
}

.table-actions form {
    display: inline-block;
    margin: 2px;
}

@media screen and (max-width: 700px) {

    .size-grid {
        grid-template-columns: 1fr;
    }

    .buttons {
        flex-direction: column;
    }
}

</style>

</head>

<body>

<div class="sizes-page">

<h1>ניהול מידות ומלאי</h1>


<div class="product-info">

<?php

if ($product["pic"] != "") {

?>

<img src="images/<?php echo $product["pic"]; ?>"
     alt="<?php echo $product["pname"]; ?>">

<?php
}

?>


<h2>
<?php echo $product["pname"]; ?>
</h2>


<p>

<?php

if ($product["gender"] == "women") {
    echo "נשים";
} else {
    echo "גברים";
}

?>

-

<?php echo getCategoryName($product["category"]); ?>

</p>


<p>
מלאי כולל:
<?php echo $product["amount"]; ?>
</p>

</div>


<?php

if ($msg != "") {
    echo "<p class='msgText'>$msg</p>";
}

?>


<div class="size-form">

<h2>

<?php

if ($editMode) {
    echo "עדכון מידה";
} else {
    echo "הוספת מידה";
}

?>

</h2>


<form method="post">

<input type="hidden"
       name="productid"
       value="<?php echo $productid; ?>">


<?php

if ($editMode) {

?>

<input type="hidden"
       name="sizeid"
       value="<?php echo $sizeid; ?>">

<?php
}

?>


<div class="size-grid">


<div>
<label>שם המידה</label>
<input type="text"
       name="sizename"
       value="<?php echo $sizename; ?>"
       placeholder="S, M, L, 42"
       required>
</div>


<div>
<label>כתפיים</label>
<input type="number"
       step="0.1"
       name="shoulder"
       value="<?php echo $shoulder; ?>">
</div>


<div>
<label>חזה</label>
<input type="number"
       step="0.1"
       name="chest"
       value="<?php echo $chest; ?>">
</div>


<div>
<label>מותניים</label>
<input type="number"
       step="0.1"
       name="waist"
       value="<?php echo $waist; ?>">
</div>


<div>
<label>ירכיים</label>
<input type="number"
       step="0.1"
       name="hips"
       value="<?php echo $hips; ?>">
</div>


<div>
<label>אורך רגל פנימי</label>
<input type="number"
       step="0.1"
       name="inseam"
       value="<?php echo $inseam; ?>">
</div>


<div>
<label>גובה</label>
<input type="number"
       step="0.1"
       name="height"
       value="<?php echo $height; ?>">
</div>


<div>
<label>מידת נעל</label>
<input type="number"
       step="0.1"
       name="shoeSize"
       value="<?php echo $shoeSize; ?>">
</div>


<div>
<label>מלאי</label>
<input type="number"
       min="0"
       name="stock"
       value="<?php echo $stock; ?>"
       required>
</div>


</div>


<div class="buttons">

<?php

if ($editMode) {

?>

<button type="submit"
        name="updateSize">
עדכון מידה
</button>

<?php

} else {

?>

<button type="submit"
        name="addSize">
הוספת מידה
</button>

<?php
}

?>

</div>

</form>


<?php

if ($editMode) {

?>

<form method="get">

<input type="hidden"
       name="productid"
       value="<?php echo $productid; ?>">

<div class="buttons">

<button type="submit">
הוספת מידה חדשה
</button>

</div>

</form>

<?php
}

?>

</div>


<div class="sizes-list">

<h2>מידות המוצר</h2>


<div class="table-wrap">

<table>

<tr>
<th>מידה</th>
<th>כתפיים</th>
<th>חזה</th>
<th>מותניים</th>
<th>ירכיים</th>
<th>אורך רגל</th>
<th>גובה</th>
<th>מידת נעל</th>
<th>מלאי</th>
<th>פעולות</th>
</tr>

<?php
if ( $sizesResult && mysqli_num_rows($sizesResult) > 0) {
    while ($sizeRow = mysqli_fetch_array($sizesResult)) {
?>

<tr>
<td><?php echo $sizeRow["sizename"]; ?></td>
<td><?php echo $sizeRow["shoulder"]; ?></td>
<td><?php echo $sizeRow["chest"]; ?></td>
<td><?php echo $sizeRow["waist"]; ?></td>
<td><?php echo $sizeRow["hips"]; ?></td>
<td><?php echo $sizeRow["inseam"]; ?></td>
<td><?php echo $sizeRow["height"]; ?></td>
<td><?php echo $sizeRow["shoe_size"]; ?></td>
<td><?php echo $sizeRow["stock"]; ?></td>

<td class="table-actions">

<form method="post">
<input type="hidden"
       name="productid"
       value="<?php echo $productid; ?>">

<input type="hidden"
       name="sizeid"
       value="<?php echo $sizeRow["sizeid"]; ?>">

<button type="submit"
        name="loadSize">
עריכה
</button>

</form>

<form method="post">

<input type="hidden"
       name="productid"
       value="<?php echo $productid; ?>">

<input type="hidden"
       name="sizeid"
       value="<?php echo $sizeRow["sizeid"]; ?>">

<button type="submit"
        name="deleteSize"
        class="deleteBtn"
        onclick="return confirm('למחוק את המידה?')">
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
<td colspan="10">
לא נמצאו מידות למוצר.
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
