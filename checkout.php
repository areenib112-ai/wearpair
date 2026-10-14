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
date_default_timezone_set("Asia/Jerusalem");

$con = OpenCon();
$userid = (int)$_SESSION["userid"];
$msg = "";

$customername = "";
$email = "";
$phone = "";
$address = "";
$cardholder = "";
$cardnumber = "";
$expiryMonth = "";
$expiryYear = "";
$cvv = "";

// קבלת שם ואימייל של הלקוח
$userResult = mysqli_query($con,"
    SELECT fname,lname,email
    FROM users
    WHERE userid='$userid'
");

$userRow = mysqli_fetch_array($userResult);

if ($userRow) {
    $customername = $userRow["fname"]." ".$userRow["lname"];
    $email = $userRow["email"];
}

// אם הלקוח חזר מעמוד האישור, מחזירים את פרטי המשלוח
if (isset($_SESSION["checkout_customername"])) {
    $customername = $_SESSION["checkout_customername"];
    $email = $_SESSION["checkout_email"];
    $phone = $_SESSION["checkout_phone"];
    $address = $_SESSION["checkout_address"];
}

// בדיקה אם הסל ריק
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

if ($cartResult) {
    $cartCount = mysqli_num_rows($cartResult);
}

// בדיקת פרטי התשלום והמעבר לעמוד האישור
if (isset($_POST["continueBtn"])) {
    $customername = $_POST["customername"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];
    $cardholder = $_POST["cardholder"];
    $cardnumber = $_POST["cardnumber"];
    $expiryMonth = $_POST["expiryMonth"];
    $expiryYear = $_POST["expiryYear"];
    $cvv = $_POST["cvv"];

    $cardnumber = str_replace(" ","",$cardnumber);
    $cardnumber = str_replace("-","",$cardnumber);

    $phoneCheck = str_replace(" ","",$phone);
    $phoneCheck = str_replace("-","",$phoneCheck);

    $cardholderHasNumber = false;

    for ($i = 0; $i < strlen($cardholder); $i++) {
        if (is_numeric($cardholder[$i])) {
            $cardholderHasNumber = true;
        }
    }

    $currentMonth = (int)date("m");
    $currentYear = (int)date("Y");

    if ($cartCount == 0) {
        $msg = "סל הקניות שלך ריק.";

    } elseif ($customername == "" || $email == "" || $phone == "" ||
              $address == "" || $cardholder == "" || $cardnumber == "" ||
              $expiryMonth == "" || $expiryYear == "" || $cvv == "") {
        $msg = "יש למלא את כל השדות.";

    } elseif ($cardholderHasNumber) {
        $msg = "שם בעל הכרטיס לא יכול להכיל מספרים.";

    } elseif (!is_numeric($phoneCheck) || strlen($phoneCheck) != 10) {
        $msg = "מספר הטלפון חייב להכיל 10 ספרות.";

    } elseif (strpos($email,"@") === false) {
        $msg = "יש להזין כתובת אימייל תקינה.";

    } elseif (!is_numeric($cardnumber) || strlen($cardnumber) != 16) {
        $msg = "מספר הכרטיס חייב להכיל 16 ספרות.";

    } elseif (!is_numeric($cvv) || strlen($cvv) != 3) {
        $msg = "קוד ה-CVV חייב להכיל 3 ספרות.";

    } elseif (!is_numeric($expiryMonth) || !is_numeric($expiryYear)) {
        $msg = "יש להזין תאריך תפוגה תקין.";

    } elseif ($expiryMonth < 1 || $expiryMonth > 12) {
        $msg = "יש להזין חודש תפוגה תקין.";

    } elseif ($expiryYear < $currentYear || ($expiryYear == $currentYear && $expiryMonth < $currentMonth)) {
        $msg = "תוקף הכרטיס פג.";
        
    } else {
        // שומרים רק את פרטי המשלוח הנחוצים לעמוד האישור
        $_SESSION["checkout_customername"] = $customername;
        $_SESSION["checkout_email"] = $email;
        $_SESSION["checkout_phone"] = $phone;
        $_SESSION["checkout_address"] = $address;
        $_SESSION["checkout_ready"] = 1;

        header("Location: confirm_order.php");
        exit();
    }
}

require("nav.php");
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>תשלום - WearPair</title>

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
    max-width:800px;
    margin:35px auto;
    padding:20px;
}
h1,h2{
    color:white;
}
h1{
    margin-bottom:30px;
    font-family:Georgia,serif;
    font-size:42px;
    text-align:center;
}
.message{
    margin:20px 0;
    color:#ffcc66;
    font-weight:bold;
    text-align:center;
}
.form-box,.empty-box{
    padding:24px;
    background:linear-gradient(145deg,#0d0d0d,#1b1b1b);
    border:1px solid #444;
    border-radius:14px;
}
.form-section{
    margin-bottom:28px;
}
.form-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:15px;
}
.full-width{
    grid-column:1 / 3;
}
label{
    display:block;
    margin-bottom:6px;
    color:white;
    font-weight:bold;
}
input,select,textarea{
    width:100%;
    padding:11px;
    box-sizing:border-box;
    background:black;
    color:white;
    border:1px solid #555;
    border-radius:7px;
}
textarea{
    min-height:90px;
    resize:vertical;
}
.expiry-fields{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:10px;
}
.continue-button,.back-button{
    display:inline-block;
    width:100%;
    padding:13px;
    box-sizing:border-box;
    background:white;
    color:black;
    border:1px solid transparent;
    border-radius:7px;
    font-size:17px;
    font-weight:bold;
    cursor:pointer;
    text-align:center;
    text-decoration:none;
}
.empty-box{
    text-align:center;
}
.back-button{
    width:auto;
    margin-top:15px;
}
@media screen and (max-width:600px){
    h1{
        font-size:32px;
    }
    .form-grid{
        grid-template-columns:1fr;
    }
    .full-width{
        grid-column:1;
    }
}
</style>
</head>

<body>
<div class="page">

<h1>תשלום</h1>

<?php
if ($msg != "") {
    echo "<p class='message'>$msg</p>";
}

if ($cartCount == 0) {
?>
<div class="empty-box">
<h2>סל הקניות שלך ריק</h2>
<p>יש להוסיף מוצרים לסל לפני המשך לתשלום.</p>
<a class="back-button" href="cart.php">חזרה לסל</a>
</div>

<?php
} else {
?>

<div class="form-box">
<form method="post">

<div class="form-section">
<h2>פרטי משלוח</h2>

<div class="form-grid">

<div class="full-width">
<label>שם מלא</label>
<input type="text" name="customername" value="<?php echo $customername; ?>" required>
</div>

<div>
<label>אימייל</label>
<input type="email" name="email" value="<?php echo $email; ?>" required>
</div>

<div>
<label>מספר טלפון</label>
<input type="text" name="phone" value="<?php echo $phone; ?>" required>
</div>

<div class="full-width">
<label>כתובת למשלוח</label>
<textarea name="address" required><?php echo $address; ?></textarea>
</div>

</div>
</div>

<div class="form-section">
<h2>פרטי תשלום</h2>

<div class="form-grid">

<div class="full-width">
<label>שם בעל הכרטיס</label>
<input type="text" name="cardholder" value="<?php echo $cardholder; ?>" required>
</div>

<div class="full-width">
<label>מספר כרטיס</label>
<input type="text" name="cardnumber" maxlength="19"
       placeholder="16 ספרות"
       value="<?php echo $cardnumber; ?>" required>
</div>

<div>
<label>תאריך תפוגה</label>

<div class="expiry-fields">
<select name="expiryMonth" required>
<option value="">חודש</option>

<?php
// יצירת רשימת החודשים מ-1 עד 12
for ($month = 1; $month <= 12; $month++) {
    $selected = "";

    // שמירת החודש שהלקוח בחר
    if ($expiryMonth == $month) {
        $selected = "selected";
    }

    echo "<option value='$month' $selected>$month</option>";
}
?>

</select>

<select name="expiryYear" required>
<option value="">שנה</option>

<?php
// קבלת השנה הנוכחית
$currentYear = (int)date("Y");

// יצירת רשימת שנים מהשנה הנוכחית ועד 10 שנים קדימה
for ($year = $currentYear; $year <= $currentYear + 10; $year++) {
    $selected = "";

    // שמירת השנה שהלקוח בחר
    if ($expiryYear == $year) {
        $selected = "selected";
    }

    echo "<option value='$year' $selected>$year</option>";
}
?>

</select>
</div>
</div>

<div>
<label>CVV</label>
<input type="password" name="cvv" maxlength="3" required>
</div>
</div>
</div>

<button class="continue-button" type="submit" name="continueBtn">
המשך לאישור ההזמנה
</button>

</form>
</div>
<?php
}
?>
</div>
</body>
</html>