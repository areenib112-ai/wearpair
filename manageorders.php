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

$con = OpenCon();

if (!$con) {
    echo "<p>החיבור למסד הנתונים נכשל.</p>";
    exit();
}

$msg = "";
$filterStatus = "";


// קבלת הסטטוס שנבחר לסינון
if (isset($_GET["filterStatus"])) {

    if (
        $_GET["filterStatus"] == "pending" ||
        $_GET["filterStatus"] == "preparing" ||
        $_GET["filterStatus"] == "shipped" ||
        $_GET["filterStatus"] == "delivered" ||
        $_GET["filterStatus"] == "cancelled"
    ) {
        $filterStatus = $_GET["filterStatus"];
    }
}


// עדכון סטטוס של הזמנה
if (isset($_POST["updateStatus"])) {

    $orderid = (int)$_POST["orderid"];
    $newStatus = $_POST["newStatus"];

    if (
        $newStatus != "pending" &&
        $newStatus != "preparing" &&
        $newStatus != "shipped" &&
        $newStatus != "delivered" &&
        $newStatus != "cancelled"
    ) {

        $msg = "סטטוס ההזמנה אינו תקין.";

    } else {

        // קבלת פרטי הלקוח לפני עדכון ההזמנה
        $orderResult = mysqli_query(
            $con,
            "SELECT customername,email
             FROM orders
             WHERE orderid='$orderid'"
        );

        if ( !$orderResult || mysqli_num_rows($orderResult) == 0) {
            $msg = "ההזמנה לא נמצאה.";

        } else {

            $orderRow = mysqli_fetch_array($orderResult);

            // עדכון סטטוס ההזמנה
            $updateResult = mysqli_query(
                $con,
                "UPDATE orders
                 SET deliverystatus='$newStatus'
                 WHERE orderid='$orderid'"
            );

            if ($updateResult) {

                if ($newStatus == "pending") {
                    $statusText = "ממתינה";
                } elseif ($newStatus == "preparing") {
                    $statusText = "בהכנה";
                } elseif ($newStatus == "shipped") {
                    $statusText = "נשלחה";
                } elseif ($newStatus == "delivered") {
                    $statusText = "נמסרה";
                } else {
                    $statusText = "בוטלה";
                }

                $subject = "WearPair - עדכון סטטוס הזמנה";

                $message =
                    "<h2>שלום " . $orderRow["customername"] . "</h2>" .
                    "<p>סטטוס ההזמנה שלך ב-WearPair עודכן.</p>" .
                    "<p><b>מספר הזמנה:</b> " . $orderid . "</p>" .
                    "<p><b>סטטוס חדש:</b> " . $statusText . "</p>";

                // שליחת אימייל ללקוח לאחר עדכון הסטטוס
                $mailSent = sendWearPairMail(
                    $orderRow["email"],
                    $subject,
                    $message
                );

                if ($mailSent) {
                    $msg = "סטטוס ההזמנה עודכן והאימייל נשלח.";
                } else {
                    $msg = "סטטוס ההזמנה עודכן, אך האימייל לא נשלח.";
                }

            } else {

                $msg = "שגיאה בעדכון ההזמנה.";
            }
        }
    }
}


// קבלת כל ההזמנות
$orderSql = "SELECT * FROM orders";

if ($filterStatus != "") {
    $orderSql .= " WHERE deliverystatus='$filterStatus'";
}

$orderSql .= " ORDER BY orderdate DESC";

$ordersResult = mysqli_query($con, $orderSql);

?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>ניהול הזמנות - WearPair</title>

<style>

body {
    margin: 0;
    background: #151515;
    color: silver;
    font-family: Arial, sans-serif;
    direction: rtl;
}

.page {
    max-width: 1200px;
    margin: 30px auto;
    padding: 20px;
}

h1 {
    color: white;
    text-align: center;
}

.message {
    color: #ffcc66;
    font-weight: bold;
    text-align: center;
}

.filter-box {
    max-width: 600px;
    margin: 0 auto 25px;
    padding: 18px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
}

.filter-form {
    display: flex;
    gap: 10px;
    align-items: end;
}

.filter-field {
    flex: 1;
}

label {
    display: block;
    margin-bottom: 6px;
    color: white;
    font-weight: bold;
}

select {
    width: 100%;
    padding: 9px;
    background: black;
    color: white;
    border: 1px solid #555;
    border-radius: 6px;
    box-sizing: border-box;
}

button {
    padding: 9px 14px;
    border: 0;
    border-radius: 6px;
    background: white;
    color: black;
    font-weight: bold;
    cursor: pointer;
}

.order {
    margin-bottom: 25px;
    padding: 20px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
}

.order-header {
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

.order-info {
    line-height: 1.8;
}

.order h2 {
    color: white;
    margin-top: 0;
}

.status-box {
    min-width: 210px;
}

.status {
    margin-bottom: 10px;
    padding: 9px;
    background: black;
    border-radius: 6px;
    text-align: center;
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

.update-form {
    display: flex;
    gap: 8px;
}

.update-form select {
    flex: 1;
}

.table-box {
    margin-top: 20px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 10px;
    border: 1px solid #444;
    text-align: center;
}

th {
    background: black;
    color: white;
}

.product-name {
    color: white;
    font-weight: bold;
}

.summary {
    margin-top: 18px;
    padding-top: 15px;
    border-top: 1px solid #444;
    line-height: 1.9;
}

.total {
    color: white;
    font-size: 20px;
    font-weight: bold;
}

.empty {
    padding: 30px;
    background: #111;
    border: 1px solid #444;
    border-radius: 10px;
    color: #ffcc66;
    text-align: center;
}

@media screen and (max-width: 750px) {

    .filter-form,
    .order-header,
    .update-form {
        flex-direction: column;
    }

    .status-box {
        width: 100%;
    }
}

</style>

</head>

<body>

<div class="page">

<h1>ניהול הזמנות</h1>

<?php

if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}

?>


<div class="filter-box">

<form class="filter-form" method="get">

<div class="filter-field">

<label>סטטוס הזמנה</label>

<select name="filterStatus">

<option value="">
כל ההזמנות
</option>

<option value="pending"
<?php
if ($filterStatus == "pending") {
    echo "selected";
}
?>>
ממתינה
</option>

<option value="preparing"
<?php
if ($filterStatus == "preparing") {
    echo "selected";
}
?>>
בהכנה
</option>

<option value="shipped"
<?php
if ($filterStatus == "shipped") {
    echo "selected";
}
?>>
נשלחה
</option>

<option value="delivered"
<?php
if ($filterStatus == "delivered") {
    echo "selected";
}
?>>
נמסרה
</option>

<option value="cancelled"
<?php
if ($filterStatus == "cancelled") {
    echo "selected";
}
?>>
בוטלה
</option>

</select>

</div>

<button type="submit">
סינון
</button>

</form>

</div>


<?php

if (
    !$ordersResult ||
    mysqli_num_rows($ordersResult) == 0
) {

?>

<div class="empty">
לא נמצאו הזמנות.
</div>

<?php

} else {

    while ($orderRow = mysqli_fetch_array($ordersResult)) {

        $orderid = $orderRow["orderid"];

        // קבלת המוצרים של ההזמנה
        $itemsResult = mysqli_query(
            $con,
            "SELECT pname,sizename,price,amount
             FROM purchasehistory
             WHERE orderid='$orderid'
             ORDER BY historyid"
        );


        // קביעת שם הסטטוס שיוצג למנהל
        if ($orderRow["deliverystatus"] == "pending") {
            $statusText = "ממתינה";
        } elseif ($orderRow["deliverystatus"] == "preparing") {
            $statusText = "בהכנה";
        } elseif ($orderRow["deliverystatus"] == "shipped") {
            $statusText = "נשלחה";
        } elseif ($orderRow["deliverystatus"] == "delivered") {
            $statusText = "נמסרה";
        } else {
            $statusText = "בוטלה";
        }

?>


<div class="order">

<div class="order-header">


<div class="order-info">

<h2>
הזמנה מספר <?php echo $orderRow["orderid"]; ?>
</h2>

<strong>לקוח:</strong>
<?php echo $orderRow["customername"]; ?>
<br>

<strong>אימייל:</strong>
<?php echo $orderRow["email"]; ?>
<br>

<strong>טלפון:</strong>
<?php echo $orderRow["phone"]; ?>
<br>

<strong>כתובת:</strong>
<?php echo $orderRow["address"]; ?>
<br>

<strong>תאריך הזמנה:</strong>
<?php echo $orderRow["orderdate"]; ?>

</div>


<div class="status-box">

<div class="status <?php echo $orderRow["deliverystatus"]; ?>">
<?php echo $statusText; ?>
</div>


<form class="update-form" method="post">

<input type="hidden"
       name="orderid"
       value="<?php echo $orderRow["orderid"]; ?>">

<select name="newStatus" required>

<option value="pending"
<?php
if ($orderRow["deliverystatus"] == "pending") {
    echo "selected";
}
?>>
ממתינה
</option>

<option value="preparing"
<?php
if ($orderRow["deliverystatus"] == "preparing") {
    echo "selected";
}
?>>
בהכנה
</option>

<option value="shipped"
<?php
if ($orderRow["deliverystatus"] == "shipped") {
    echo "selected";
}
?>>
נשלחה
</option>

<option value="delivered"
<?php
if ($orderRow["deliverystatus"] == "delivered") {
    echo "selected";
}
?>>
נמסרה
</option>

<option value="cancelled"
<?php
if ($orderRow["deliverystatus"] == "cancelled") {
    echo "selected";
}
?>>
בוטלה
</option>

</select>

<button type="submit" name="updateStatus">
עדכון
</button>

</form>

</div>

</div>


<div class="table-box">

<table>

<tr>
<th>מוצר</th>
<th>מידה</th>
<th>מחיר</th>
<th>כמות</th>
<th>סה״כ</th>
</tr>


<?php

if ($itemsResult) {

    while ($item = mysqli_fetch_array($itemsResult)) {

        $itemTotal =
            $item["price"] *
            $item["amount"];

?>

<tr>

<td class="product-name">
<?php echo $item["pname"]; ?>
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

<strong>סכום מוצרים:</strong>
<?php echo $orderRow["subtotal"]; ?> ₪
<br>

<strong>דמי משלוח:</strong>
<?php echo $orderRow["deliveryfee"]; ?> ₪
<br>

<span class="total">
סה״כ:
<?php echo $orderRow["totalprice"]; ?> ₪
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
