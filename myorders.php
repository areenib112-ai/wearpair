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

$ordersResult = mysqli_query($con, "
    SELECT * FROM orders
    WHERE userid='$userid'
    ORDER BY orderdate DESC
");

require("nav.php");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">

<title>ההזמנות שלי - WearPair</title>

<style>

body {
    margin: 0;
    min-height: 100vh;
    background: linear-gradient(135deg,#000,#171717,#292929);
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
    color: white;
    font: 42px Georgia,serif;
    text-align: center;
}

.order {
    margin-bottom: 25px;
    padding: 22px;
    background: linear-gradient(145deg,#0d0d0d,#1b1b1b);
    border: 1px solid #444;
    border-radius: 14px;
}

.order-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 18px;
}

.order h2 {
    margin: 0 0 8px;
    color: white;
}

.order-info {
    line-height: 1.8;
}

.status {
    height: fit-content;
    padding: 9px 14px;
    border-radius: 7px;
    background: black;
    color: white;
    font-weight: bold;
}

.pending {
    color: #ffcc66;
}

.preparing {
    color: #b9d7ff;
}

.shipped {
    color: #d6b6ff;
}

.delivered {
    color: #b6ffb6;
}

.cancelled {
    color: #ff9999;
}

.table-box {
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 700px;
    border-collapse: collapse;
    background: #111;
}

th,
td {
    padding: 12px;
    border: 1px solid #333;
    text-align: center;
}

th {
    background: black;
    color: white;
}

.product-info {
    display: flex;
    align-items: center;
    gap: 12px;
    text-align: right;
}

.product-image {
    width: 70px;
    height: 70px;
    object-fit: contain;
    background: #222;
    border-radius: 7px;
}

.product-name,
.total {
    color: white;
    font-weight: bold;
}

.summary {
    margin-top: 18px;
    padding-top: 15px;
    border-top: 1px solid #444;
    line-height: 1.9;
    text-align: right;
}

.total {
    font-size: 21px;
}

.empty {
    padding: 45px 20px;
    background: #111;
    border: 1px solid #444;
    border-radius: 14px;
    text-align: center;
}

.empty h2 {
    color: white;
}

.shop-button {
    display: inline-block;
    margin: 8px;
    padding: 11px 17px;
    background: white;
    color: black;
    border-radius: 7px;
    font-weight: bold;
    text-decoration: none;
}

@media screen and (max-width:650px) {

    h1 {
        font-size: 32px;
    }

    .order-header {
        flex-direction: column;
    }

    .status {
        width: fit-content;
    }
}

</style>

</head>

<body>

<div class="page">

<h1>ההזמנות שלי</h1>

<?php

if (!$ordersResult || mysqli_num_rows($ordersResult) == 0) {

?>

<div class="empty">

<h2>עדיין אין לך הזמנות</h2>

<p>
הרכישות שהושלמו יופיעו כאן.
</p>

<a class="shop-button"
   href="women.php">
קניות לנשים
</a>

<a class="shop-button"
   href="men.php">
קניות לגברים
</a>

</div>

<?php

} else {
// מעבר על כל ההזמנות של הלקוח וקבלת המוצרים ששייכים לכל הזמנה.
    while ($order = mysqli_fetch_array($ordersResult)) {

        $orderid = $order["orderid"];

        $itemsResult = mysqli_query($con, "
            SELECT * FROM purchasehistory
            WHERE orderid='$orderid'
            AND userid='$userid'
            ORDER BY historyid
        ");

?>

<div class="order">

<div class="order-header">

<div class="order-info">

<h2>
הזמנה מספר
<?php echo $order["orderid"]; ?>
</h2>

<strong>תאריך:</strong>
<?php echo $order["orderdate"]; ?>
<br>

<strong>שם:</strong>
<?php echo $order["customername"]; ?>
<br>

<strong>מספר טלפון:</strong>
<?php echo $order["phone"]; ?>
<br>

<strong>כתובת:</strong>
<?php echo $order["address"]; ?>

</div>


<div class="status <?php echo $order["deliverystatus"]; ?>">

<?php

if ($order["deliverystatus"] == "pending") {
    echo "ממתין";

} elseif ($order["deliverystatus"] == "preparing") {
    echo "בהכנה";

} elseif ($order["deliverystatus"] == "shipped") {
    echo "נשלח";

} elseif ($order["deliverystatus"] == "delivered") {
    echo "נמסר";

} elseif ($order["deliverystatus"] == "cancelled") {
    echo "בוטל";

} else {
    echo $order["deliverystatus"];
}

?>

</div>

</div>


<div class="table-box">

<table>

<tr>
<th>מוצר</th>
<th>מידה</th>
<th>מחיר</th>
<th>כמות</th>
<th>סה"כ</th>
</tr>

<?php

if ($itemsResult) {

    while ($item = mysqli_fetch_array($itemsResult)) {

        $itemTotal = $item["price"] * $item["amount"];

?>

<tr>

<td>

<div class="product-info">

<?php
// קבלת תמונת המוצר לפי מספר המוצר כדי להציג אותה בהזמנה.
$picResult = mysqli_query($con, "
    SELECT pic
    FROM products
    WHERE productid='" . $item["productid"] . "'
");

$picRow = mysqli_fetch_array($picResult);

if ($picRow && $picRow["pic"] != "") {

?>

<img class="product-image"
     src="images/<?php echo $picRow["pic"]; ?>"
     alt="<?php echo $item["pname"]; ?>">

<?php

}

?>

<span class="product-name">

<?php echo $item["pname"]; ?>

</span>

</div>

</td>


<td>
<?php echo $item["sizename"]; ?>
</td>


<td>
<?php echo $item["price"]; ?> ₪
</td>


<td>
<?php echo $item["amount"]; ?>
</td>


<td>
<?php echo $itemTotal; ?> ₪
</td>

</tr>

<?php

    }
}

?>

</table>

</div>


<div class="summary">

<strong>סכום ביניים:</strong>

<?php echo $order["subtotal"]; ?> ₪

<br>


<strong>דמי משלוח:</strong>

<?php echo $order["deliveryfee"]; ?> ₪

<br>


<span class="total">

סה"כ:
<?php echo $order["totalprice"]; ?> ₪

</span>

</div>

</div>

<?php

    }
}

?>

</div>

</body>

</html>
