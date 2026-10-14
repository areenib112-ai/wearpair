<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["userid"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION["role"]) || $_SESSION["role"] != 0) {
    header("Location: home.php");
    exit();
}

require("dbconnection.php");

$con = OpenCon();

$userid = (int)$_SESSION["userid"];
$msg = "";

if (isset($_POST["addToCart"])) {
    $productid = (int)$_POST["productid"];
    $sizeid = (int)$_POST["sizeid"];

    $sizeResult = mysqli_query(
        $con,
        "SELECT product_sizes.sizeid,
                product_sizes.stock,
                products.pname
         FROM product_sizes
         INNER JOIN products
         ON product_sizes.productid=products.productid
         WHERE product_sizes.sizeid='$sizeid'
         AND product_sizes.productid='$productid'"
    );

    if (!$sizeResult || mysqli_num_rows($sizeResult) == 0) {
        $msg = "מידת המוצר שנבחרה לא נמצאה.";
    } else {
        $sizeRow = mysqli_fetch_array($sizeResult);
        $stock = $sizeRow["stock"];

        if ($stock <= 0) {
            $msg = "המידה הזו אזלה מהמלאי.";
        } else {
            $cartCheck = mysqli_query(
                $con,
                "SELECT amount
                 FROM cart
                 WHERE userid='$userid'
                 AND productid='$productid'
                 AND sizeid='$sizeid'"
            );

            if ($cartCheck && mysqli_num_rows($cartCheck) > 0) {
                $cartRow = mysqli_fetch_array($cartCheck);
                $newAmount = $cartRow["amount"] + 1;

                if ($newAmount > $stock) {
                    $msg = "לא ניתן להוסיף כמות גדולה מהמלאי הזמין.";
                } else {
                    $updateCart = mysqli_query(
                        $con,
                        "UPDATE cart
                         SET amount='$newAmount'
                         WHERE userid='$userid'
                         AND productid='$productid'
                         AND sizeid='$sizeid'"
                    );

                    if ($updateCart) {
                        $msg = "כמות המוצר בסל עודכנה.";
                    } else {
                        $msg = "אירעה שגיאה בעדכון הסל.";
                    }
                }
            } else {
                $addCart = mysqli_query(
                    $con,
                    "INSERT INTO cart
                    (
                        userid,
                        productid,
                        sizeid,
                        amount
                    )
                    VALUES
                    (
                        '$userid',
                        '$productid',
                        '$sizeid',
                        1
                    )"
                );

                if ($addCart) {
                    $msg = "המוצר נוסף לסל.";
                } else {
                    $msg = "אירעה שגיאה בהוספת המוצר לסל.";
                }
            }
        }
    }
}

if (isset($_POST["updateCart"])) {
    $productid = (int)$_POST["productid"];
    $sizeid = (int)$_POST["sizeid"];
    $amount = (int)$_POST["amount"];

    $stockResult = mysqli_query(
        $con,
        "SELECT stock
         FROM product_sizes
         WHERE sizeid='$sizeid'
         AND productid='$productid'"
    );

    if (!$stockResult || mysqli_num_rows($stockResult) == 0) {
        $msg = "המידה שנבחרה לא נמצאה.";
    } else {
        $stockRow = mysqli_fetch_array($stockResult);
        $stock = $stockRow["stock"];

        if ($amount < 1) {
            $msg = "הכמות חייבת להיות לפחות 1.";
        } elseif ($amount > $stock) {
            $msg = "הכמות גדולה מהמלאי הזמין.";
        } else {
            $updateResult = mysqli_query(
                $con,
                "UPDATE cart
                 SET amount='$amount'
                 WHERE userid='$userid'
                 AND productid='$productid'
                 AND sizeid='$sizeid'"
            );

            if ($updateResult) {
                $msg = "סל הקניות עודכן בהצלחה.";
            } else {
                $msg = "אירעה שגיאה בעדכון הסל.";
            }
        }
    }
}

if (isset($_POST["removeItem"])) {
    $productid = (int)$_POST["productid"];
    $sizeid = (int)$_POST["sizeid"];

    $removeResult = mysqli_query(
        $con,
        "DELETE FROM cart
         WHERE userid='$userid'
         AND productid='$productid'
         AND sizeid='$sizeid'"
    );

    if ($removeResult) {
        $msg = "המוצר הוסר מהסל.";
    } else {
        $msg = "אירעה שגיאה בהסרת המוצר.";
    }
}

if (isset($_POST["clearCart"])) {
    $clearResult = mysqli_query(
        $con,
        "DELETE FROM cart
         WHERE userid='$userid'"
    );

    if ($clearResult) {
        $msg = "סל הקניות נוקה.";
    } else {
        $msg = "אירעה שגיאה בניקוי הסל.";
    }
}

$cartResult = mysqli_query(
    $con,
    "SELECT cart.productid,
            cart.sizeid,
            cart.amount,
            products.pname,
            products.price,
            products.pic,
            product_sizes.sizename,
            product_sizes.stock
     FROM cart
     INNER JOIN products
     ON cart.productid=products.productid
     INNER JOIN product_sizes
     ON cart.sizeid=product_sizes.sizeid
     AND cart.productid=product_sizes.productid
     WHERE cart.userid='$userid'
     ORDER BY products.pname,
              product_sizes.sizename"
);

$subtotal = 0;

require("nav.php");
?>

<!DOCTYPE html>

<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width,initial-scale=1.0">

<title>סל הקניות שלי - WearPair</title>

<style>

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg,#000000,#171717,#292929);
    color: silver;
    font-family: Arial,sans-serif;
    direction: rtl;
}

.page {
    max-width: 1200px;
    margin: 35px auto;
    padding: 20px;
}

h1 {
    margin-bottom: 30px;
    color: white;
    font-family: Georgia,serif;
    font-size: 42px;
    text-align: center;
}

.message {
    margin: 20px 0;
    color: #ffcc66;
    font-weight: bold;
    text-align: center;
}

.cart-box {
    overflow-x: auto;
    background: linear-gradient(145deg,#0d0d0d,#1b1b1b);
    border: 1px solid #444444;
    border-radius: 14px;
}

table {
    width: 100%;
    min-width: 850px;
    border-collapse: collapse;
}

th,
td {
    padding: 15px;
    border-bottom: 1px solid #333333;
    text-align: center;
}

th {
    background: black;
    color: white;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 15px;
    text-align: right;
}

.product-image {
    width: 90px;
    height: 90px;
    object-fit: contain;
    background: #222222;
    border-radius: 8px;
}

.product-name {
    color: white;
    font-size: 17px;
    font-weight: bold;
}

.size-name {
    color: white;
    font-weight: bold;
}

.amount-input {
    width: 75px;
    padding: 9px;
    box-sizing: border-box;
    background: black;
    color: white;
    border: 1px solid #555555;
    border-radius: 6px;
    text-align: center;
}

button,
.checkout-button {
    display: inline-block;
    padding: 10px 15px;
    border: 0;
    border-radius: 7px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

.update-button {
    margin-top: 7px;
    background: white;
    color: black;
}

.remove-button {
    background: #650000;
    color: white;
}

.clear-button {
    background: #650000;
    color: white;
}

.checkout-button {
    background: white;
    color: black;
}

.cart-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-top: 25px;
    padding: 22px;
    background: linear-gradient(145deg,#0d0d0d,#1b1b1b);
    border: 1px solid #444444;
    border-radius: 14px;
}

.total {
    color: white;
    font-size: 24px;
    font-weight: bold;
}

.summary-buttons {
    display: flex;
    align-items: center;
    gap: 12px;
}

.empty-cart {
    padding: 50px 20px;
    background: linear-gradient(145deg,#0d0d0d,#1b1b1b);
    border: 1px solid #444444;
    border-radius: 14px;
    text-align: center;
}

.empty-cart h2 {
    color: white;
}

.shop-button {
    display: inline-block;
    margin: 10px;
    padding: 11px 17px;
    background: white;
    color: black;
    border-radius: 7px;
    font-weight: bold;
    text-decoration: none;
}

.stock-warning {
    color: #ffcc66;
    font-size: 13px;
}

@media screen and (max-width:700px) {

    h1 {
        font-size: 32px;
    }

    .cart-summary {
        flex-direction: column;
    }

    .summary-buttons {
        flex-direction: column;
        width: 100%;
    }

    .summary-buttons form,
    .summary-buttons a {
        width: 100%;
        box-sizing: border-box;
    }

    .summary-buttons button {
        width: 100%;
    }

    .checkout-button {
        text-align: center;
    }
}

</style>

</head>

<body>

<div class="page">

<h1>סל הקניות שלי</h1>

<?php

if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}

?>

<?php

if (!$cartResult || mysqli_num_rows($cartResult) == 0) {

?>

<div class="empty-cart">

<h2>סל הקניות שלך ריק</h2>

<p>
יש לבחור מוצרים ומידות מקולקציית הנשים או הגברים.
</p>

<a class="shop-button" href="women.php">
קניות לנשים
</a>

<a class="shop-button" href="men.php">
קניות לגברים
</a>

</div>

<?php

} else {

?>

<div class="cart-box">

<table>

<tr>
<th>מוצר</th>
<th>מידה</th>
<th>מחיר</th>
<th>כמות</th>
<th>מלאי זמין</th>
<th>סה"כ</th>
<th>הסרה</th>
</tr>

<?php

while ($row = mysqli_fetch_array($cartResult)) {

    $itemTotal = $row["price"] * $row["amount"];
    $subtotal += $itemTotal;

?>

<tr>

<td>

<div class="product-info">

<img class="product-image"
     src="images/<?php echo $row["pic"]; ?>"
     alt="<?php echo $row["pname"]; ?>">

<span class="product-name">

<?php echo $row["pname"]; ?>

</span>

</div>

</td>

<td class="size-name">

<?php echo $row["sizename"]; ?>

</td>

<td>

<?php echo $row["price"]; ?> ₪

</td>

<td>

<form method="post">

<input type="hidden"
       name="productid"
       value="<?php echo $row["productid"]; ?>">

<input type="hidden"
       name="sizeid"
       value="<?php echo $row["sizeid"]; ?>">

<input class="amount-input"
       type="number"
       name="amount"
       min="1"
       max="<?php echo $row["stock"]; ?>"
       value="<?php echo $row["amount"]; ?>"
       required>

<br>

<button class="update-button"
        type="submit"
        name="updateCart">
עדכון
</button>

</form>

</td>

<td>

<?php echo $row["stock"]; ?>

<?php

if ($row["amount"] == $row["stock"]) {

?>

<p class="stock-warning">
הגעת לכמות המלאי הזמינה.
</p>

<?php

}

?>

</td>

<td>

<?php echo $itemTotal; ?> ₪

</td>

<td>

<form method="post">

<input type="hidden"
       name="productid"
       value="<?php echo $row["productid"]; ?>">

<input type="hidden"
       name="sizeid"
       value="<?php echo $row["sizeid"]; ?>">

<button class="remove-button"
        type="submit"
        name="removeItem"
        onclick="return confirm('להסיר את המוצר מהסל?')">
הסרה
</button>

</form>

</td>

</tr>

<?php

}

?>

</table>

</div>

<div class="cart-summary">

<div class="total">

סכום ביניים:
<?php echo $subtotal; ?> ₪

</div>

<div class="summary-buttons">

<form method="post">

<button class="clear-button"
        type="submit"
        name="clearCart"
        onclick="return confirm('למחוק את כל המוצרים מהסל?')">
ניקוי הסל
</button>

</form>

<a class="checkout-button"
   href="checkout.php">
המשך לתשלום
</a>

</div>

</div>

<?php

}

?>
</div>
</body>
</html>
