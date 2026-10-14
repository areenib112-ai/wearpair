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

if (!isset($_SESSION["checkout_ready"]) || $_SESSION["checkout_ready"] != 1) {
    header("Location: checkout.php");
    exit();
}

require("dbconnection.php");
require("functions.php");
date_default_timezone_set("Asia/Jerusalem");

$con = OpenCon();
$userid = (int)$_SESSION["userid"];
$msg = "";
$orderSuccess = false;
$orderid = 0;

$customername = $_SESSION["checkout_customername"];
$email = $_SESSION["checkout_email"];
$phone = $_SESSION["checkout_phone"];
$address = $_SESSION["checkout_address"];

// ניקוי טקסט לפני שמירה במסד הנתונים
function cleanOrderText($value)
{
    $value = str_replace("\\","\\\\",$value);
    $value = str_replace("'","''",$value);
    return $value;
}

// קבלת מוצרי הסל
$cartResult = mysqli_query($con,"
    SELECT cart.productid,cart.sizeid,cart.amount,
           products.pname,products.price,
           product_sizes.sizename,product_sizes.stock
    FROM cart
    INNER JOIN products
    ON cart.productid=products.productid
    INNER JOIN product_sizes
    ON cart.sizeid=product_sizes.sizeid
    AND cart.productid=product_sizes.productid
    WHERE cart.userid='$userid'
    ORDER BY products.pname
");

$cartCount = 0;
$subtotal = 0;

if ($cartResult) {
    $cartCount = mysqli_num_rows($cartResult);

    while ($cartRow = mysqli_fetch_array($cartResult)) {
        $subtotal = $subtotal + ($cartRow["price"] * $cartRow["amount"]);
    }
}

$deliveryfee = 25;

if ($subtotal >= 300) {
    $deliveryfee = 0;
}

$totalprice = $subtotal + $deliveryfee;

// אישור סופי וביצוע ההזמנה
if (isset($_POST["confirmBtn"])) {
    if ($cartCount == 0) {
        $msg = "סל הקניות שלך ריק.";
    } else {
        // בדיקה שיש מספיק מלאי לכל המוצרים
        $stockAvailable = true;

        $stockCartResult = mysqli_query($con,"
            SELECT productid,sizeid,amount
            FROM cart
            WHERE userid='$userid'
        ");

        if (!$stockCartResult) {
            $stockAvailable = false;
        } else {
            // מעבר על מוצרי הסל ובדיקה שיש מספיק מלאי לכל מוצר
            while ($stockCartRow = mysqli_fetch_array($stockCartResult)) {
                $productid = $stockCartRow["productid"];
                $sizeid = $stockCartRow["sizeid"];
                $amount = $stockCartRow["amount"];

                // קבלת המלאי של המידה שנבחרה
                $stockCheck = mysqli_query($con,"
                    SELECT stock
                    FROM product_sizes
                    WHERE productid='$productid'
                    AND sizeid='$sizeid'
                ");

                if (!$stockCheck || mysqli_num_rows($stockCheck) == 0) {
                    $stockAvailable = false;
                } else {
                    $stockRow = mysqli_fetch_array($stockCheck);

                    if ($stockRow["stock"] < $amount) {
                        $stockAvailable = false;
                    }
                }
            }
        }

        if (!$stockAvailable) {
            $msg = "לאחד או יותר מהמוצרים אין מספיק מלאי. יש לעדכן את סל הקניות.";
        } else {
            $customernameSql = cleanOrderText($customername);
            $emailSql = cleanOrderText($email);
            $phoneSql = cleanOrderText($phone);
            $addressSql = cleanOrderText($address);

            $addOrder = mysqli_query($con,"
                INSERT INTO orders
                (userid,customername,email,phone,address,subtotal,
                 deliveryfee,totalprice,deliverystatus,orderdate)
                VALUES
                ('$userid','$customernameSql','$emailSql','$phoneSql',
                 '$addressSql','$subtotal','$deliveryfee','$totalprice',
                 'pending',NOW())
            ");

            if (!$addOrder) {
                $msg = "אירעה שגיאה ביצירת ההזמנה.";
            } else {
                // קבלת מספר ההזמנה החדשה
                $orderResult = mysqli_query($con,"
                    SELECT orderid
                    FROM orders
                    WHERE userid='$userid'
                    ORDER BY orderid DESC
                    LIMIT 1
                ");

                $orderRow = mysqli_fetch_array($orderResult);
                $orderid = $orderRow["orderid"];

                $orderSaved = true;
                $emailRows = "";

                //קבלת כל המוצרים שנמצאים בסל של הלקוח 
                $orderItemsResult = mysqli_query($con,"
                    SELECT cart.productid,cart.sizeid,cart.amount,
                           products.pname,products.price,
                           product_sizes.sizename
                    FROM cart
                    INNER JOIN products
                    ON cart.productid=products.productid
                    INNER JOIN product_sizes
                    ON cart.sizeid=product_sizes.sizeid
                    AND cart.productid=product_sizes.productid
                    WHERE cart.userid='$userid'
                    ORDER BY products.pname
                ");

                if (!$orderItemsResult) {
                    $orderSaved = false;
                } else {
                    // מעבר על כל המוצרים בסל ושמירת הפרטים שלהם במשתנים.
                    while ($cartRow = mysqli_fetch_array($orderItemsResult)) {
                        $productid = $cartRow["productid"];
                        $sizeid = $cartRow["sizeid"];
                        $sizename = $cartRow["sizename"];
                        $pname = $cartRow["pname"];
                        $price = $cartRow["price"];
                        $amount = $cartRow["amount"];

                        $sizenameSql = cleanOrderText($sizename);
                        $pnameSql = cleanOrderText($pname);

                        $addHistory = mysqli_query($con,"
                            INSERT INTO purchasehistory
                            (orderid,userid,customername,email,phone,address,
                             productid,sizeid,sizename,pname,price,amount,purchasedate)
                            VALUES
                            ('$orderid','$userid','$customernameSql','$emailSql',
                             '$phoneSql','$addressSql','$productid','$sizeid',
                             '$sizenameSql','$pnameSql','$price','$amount',NOW())
                        ");

                        if (!$addHistory) {
                            $orderSaved = false;
                        }

                        $updateStock = mysqli_query($con,"
                            UPDATE product_sizes
                            SET stock=stock-'$amount'
                            WHERE productid='$productid'
                            AND sizeid='$sizeid'
                            AND stock>='$amount'
                        ");

                        if (!$updateStock) {
                            $orderSaved = false;
                        }

                        // חישוב המלאי הכולל של המוצר בלי כינוי לשדה
                        $totalStockResult = mysqli_query($con,"
                            SELECT SUM(stock)
                            FROM product_sizes
                            WHERE productid='$productid'
                        ");

                        $totalStockRow = mysqli_fetch_array($totalStockResult);
                        $totalStock = 0;

                        if ($totalStockRow && $totalStockRow[0] != "") {
                            $totalStock = $totalStockRow[0];
                        }

                        mysqli_query($con,"
                            UPDATE products
                            SET amount='$totalStock'
                            WHERE productid='$productid'
                        ");

                        $itemTotal = $price * $amount;

                        $emailRows .= "<tr>";
                        $emailRows .= "<td style='border:1px solid #999;padding:8px;'>$pname</td>";
                        $emailRows .= "<td style='border:1px solid #999;padding:8px;'>$sizename</td>";
                        $emailRows .= "<td style='border:1px solid #999;padding:8px;'>$amount</td>";
                        $emailRows .= "<td style='border:1px solid #999;padding:8px;'>$itemTotal ₪</td>";
                        $emailRows .= "</tr>";
                    }
                }

                if ($orderSaved) {
                    $emailMessage = "<h2>תודה על ההזמנה, $customername</h2>";
                    $emailMessage .= "<p>הזמנת WearPair שלך התקבלה בהצלחה.</p>";
                    $emailMessage .= "<p><b>מספר הזמנה:</b> $orderid</p>";
                    $emailMessage .= "<p><b>כתובת למשלוח:</b> $address</p>";
                    $emailMessage .= "<table style='width:100%;border-collapse:collapse;'>";
                    $emailMessage .= "<tr>";
                    $emailMessage .= "<th style='border:1px solid #999;padding:8px;'>מוצר</th>";
                    $emailMessage .= "<th style='border:1px solid #999;padding:8px;'>מידה</th>";
                    $emailMessage .= "<th style='border:1px solid #999;padding:8px;'>כמות</th>";
                    $emailMessage .= "<th style='border:1px solid #999;padding:8px;'>מחיר</th>";
                    $emailMessage .= "</tr>";
                    $emailMessage .= $emailRows;
                    $emailMessage .= "</table>";
                    $emailMessage .= "<p><b>סכום ביניים:</b> $subtotal ₪</p>";
                    $emailMessage .= "<p><b>דמי משלוח:</b> $deliveryfee ₪</p>";
                    $emailMessage .= "<p><b>סה\"כ:</b> $totalprice ₪</p>";
                    $emailMessage .= "<p><b>סטטוס משלוח:</b> ממתין</p>";

                    mysqli_query($con,"
                        DELETE FROM cart
                        WHERE userid='$userid'
                    ");

                    $mailSent = sendWearPairMail(
                        $email,
                        "WearPair - אישור הזמנה",
                        $emailMessage
                    );

                    // מחיקת פרטי ההזמנה הזמניים מה-Session לאחר השלמת ההזמנה
                    unset($_SESSION["checkout_customername"]);
                    unset($_SESSION["checkout_email"]);
                    unset($_SESSION["checkout_phone"]);
                    unset($_SESSION["checkout_address"]);
                    unset($_SESSION["checkout_ready"]);

                    $orderSuccess = true;

                    if ($mailSent) {
                        $msg = "ההזמנה הושלמה והקבלה נשלחה לאימייל שלך.";
                    } else {
                        $msg = "ההזמנה הושלמה, אך האימייל לא נשלח.";
                    }
                } else {
                    $msg = "אירעה שגיאה בשמירת פריטי ההזמנה.";
                }
            }
        }
    }
}

// קבלת מוצרי הסל מחדש לצורך ההצגה
$summaryResult = mysqli_query($con,"
    SELECT cart.amount,products.pname,products.price,product_sizes.sizename
    FROM cart
    INNER JOIN products
    ON cart.productid=products.productid
    INNER JOIN product_sizes
    ON cart.sizeid=product_sizes.sizeid
    AND cart.productid=product_sizes.productid
    WHERE cart.userid='$userid'
    ORDER BY products.pname
");

require("nav.php");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>אישור הזמנה - WearPair</title>

<style>
body{
    margin:0;
    min-height:100vh;
    background:linear-gradient(135deg,#000,#171717,#292929);
    color:silver;
    font-family:Arial,sans-serif;
    direction:rtl;
}
.page{
    max-width:950px;
    margin:35px auto;
    padding:20px;
}
h1,h2{
    color:white;
}
h1{
    text-align:center;
    font-family:Georgia,serif;
    font-size:40px;
}
.message{
    color:#ffcc66;
    font-weight:bold;
    text-align:center;
}
.confirm-box{
    padding:24px;
    background:#111;
    border:1px solid #444;
    border-radius:12px;
}
.details-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
    margin-bottom:25px;
}
.detail{
    padding:12px;
    background:#181818;
    border:1px solid #333;
    border-radius:7px;
}
.detail strong{
    color:white;
}
table{
    width:100%;
    border-collapse:collapse;
    margin:15px 0 25px;
}
th,td{
    padding:10px;
    border:1px solid #444;
    text-align:center;
}
th{
    background:black;
    color:white;
}
.price-line{
    display:flex;
    justify-content:space-between;
    margin:10px 0;
}
.total-line{
    padding-top:12px;
    border-top:1px solid #555;
    color:white;
    font-size:20px;
    font-weight:bold;
}
.buttons{
    display:flex;
    gap:10px;
    margin-top:25px;
}
.confirm-button,.back-button,.success-button{
    flex:1;
    display:inline-block;
    padding:12px;
    box-sizing:border-box;
    border:1px solid transparent;
    border-radius:7px;
    font-weight:bold;
    cursor:pointer;
    text-decoration:none;
    text-align:center;
}
.confirm-button,.success-button{
    background:white;
    color:black;
}
.back-button{
    background:#333;
    color:white;
}
@media screen and (max-width:650px){
    .details-grid{
        grid-template-columns:1fr;
    }
    .buttons{
        flex-direction:column;
    }
}
</style>
</head>

<body>
<div class="page">

<h1>אישור הזמנה</h1>

<?php
if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}

if ($orderSuccess) {
?>

<div class="buttons">
<a class="success-button" href="myorders.php">צפייה בהזמנות שלי</a>
<a class="success-button" href="home.php">חזרה לדף הבית</a>
</div>

<?php
} elseif ($cartCount == 0) {
?>

<div class="success-box">
<h2>סל הקניות שלך ריק</h2>
<a class="back-button" href="cart.php">חזרה לסל</a>
</div>

<?php
} else {
?>

<div class="confirm-box">

<h2>פרטי משלוח</h2>

<div class="details-grid">
<div class="detail"><strong>שם:</strong> <?php echo $customername; ?></div>
<div class="detail"><strong>אימייל:</strong> <?php echo $email; ?></div>
<div class="detail"><strong>טלפון:</strong> <?php echo $phone; ?></div>
<div class="detail"><strong>כתובת:</strong> <?php echo $address; ?></div>
</div>

<h2>סיכום הזמנה</h2>

<table>
<tr>
<th>מוצר</th>
<th>מידה</th>
<th>כמות</th>
<th>מחיר</th>
</tr>

<?php
// מעבר על כל מוצרי הסל וחישוב המחיר לפי הכמות
while ($summaryResult && $summaryRow = mysqli_fetch_array($summaryResult)) {
    $itemTotal = $summaryRow["price"] * $summaryRow["amount"];
?>
<tr>
<td><?php echo $summaryRow["pname"]; ?></td>
<td><?php echo $summaryRow["sizename"]; ?></td>
<td><?php echo $summaryRow["amount"]; ?></td>
<td><?php echo $itemTotal; ?> ₪</td>
</tr>
<?php
}
?>

</table>

<div class="price-line">
<span>סכום ביניים</span>
<strong><?php echo $subtotal; ?> ₪</strong>
</div>

<div class="price-line">
<span>דמי משלוח</span>
<strong><?php echo $deliveryfee; ?> ₪</strong>
</div>

<div class="price-line total-line">
<span>סה"כ</span>
<span><?php echo $totalprice; ?> ₪</span>
</div>

<form method="post">
<div class="buttons">
<a class="back-button" href="checkout.php">חזרה לתשלום</a>
<button class="confirm-button" type="submit" name="confirmBtn">אישור וביצוע הזמנה</button>
</div>
</form>

</div>

<?php
}
?>

</div>
</body>
</html>