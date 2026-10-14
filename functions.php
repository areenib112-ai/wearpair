<?php

// משמש בעמודים: women.php, men.php
// בודק אם מוצר כבר נמצא במועדפים של המשתמש
function favoriteExists($con, $userid, $productid)
{
    $result = mysqli_query($con, "
        SELECT productid FROM favorites
        WHERE userid='$userid' AND productid='$productid'
    ");

    return $result && mysqli_num_rows($result) > 0;
}

// משמש בעמודים: women.php, men.php
// מחזיר את שם הקטגוריה בעברית כדי להציג אותו למשתמש
function getCategoryName($category)
{
    if ($category == "blouses") {
        return "חולצות אלגנטיות";
    } elseif ($category == "shirts") {
        return "חולצות מכופתרות";
    } elseif ($category == "tshirts") {
        return "חולצות טי שירט";
    } elseif ($category == "jackets") {
        return "ז'קטים";
    } elseif ($category == "pants") {
        return "מכנסיים";
    } elseif ($category == "skirts") {
        return "חצאיות";
    } elseif ($category == "dresses") {
        return "שמלות";
    } elseif ($category == "shoes") {
        return "נעליים";
    } elseif ($category == "bags") {
        return "תיקים";
    } elseif ($category == "jewelry") {
        return "תכשיטים";
    } elseif ($category == "accessories") {
        return "אביזרים";
    } elseif ($category == "watches") {
        return "שעונים";
    }

    return $category;
}


// משמש בעמודים: women.php, men.php
// מחזיר את סוג הקטגוריה כדי לדעת אילו מידות צריך לבדוק
function getCategoryType($category)
{
    if ($category == "") {
        return "";
    } elseif (
        $category == "blouses" ||  $category == "shirts" ||
        $category == "tshirts" || $category == "jackets"
    ) {
        return "upper";
        
    } elseif ($category == "pants") {
        return "pants";

    } elseif ($category == "skirts") {
        return "skirt";

    } elseif ($category == "dresses") {
        return "dress";

    } elseif ($category == "shoes") {
        return "shoes";
    }

    return "accessory";
}

// משמש בעמודים: women.php, men.php
// מוסיף מוצר לעגלה ובודק שיש מספיק מלאי במידה שנבחרה
function addCartItem($con, $userid, $productid, $sizeid)
{
    $sizeResult = mysqli_query($con, "
        SELECT stock FROM product_sizes
        WHERE sizeid='$sizeid' AND productid='$productid'
    ");

    if (!$sizeResult || mysqli_num_rows($sizeResult) == 0) {
        return "המידה הזאת אינה זמינה.";
    }

    $size = mysqli_fetch_array($sizeResult);
    $stock = $size["stock"];

    $cartResult = mysqli_query($con, "
        SELECT amount FROM cart
        WHERE userid='$userid'
        AND productid='$productid'
        AND sizeid='$sizeid'
    ");

    if ($cartResult && mysqli_num_rows($cartResult) > 0) {
        $cartItem = mysqli_fetch_array($cartResult);
        $newAmount = $cartItem["amount"] + 1;

        if ($newAmount > $stock) {
            return "אין מספיק מלאי.";
        }

        $ok = mysqli_query($con, "
            UPDATE cart SET amount='$newAmount'
            WHERE userid='$userid'
            AND productid='$productid'
            AND sizeid='$sizeid'
        ");
    } else {
        if ($stock <= 0) {
            return "אין מספיק מלאי.";
        }

        $ok = mysqli_query($con, "
            INSERT INTO cart(userid,productid,sizeid,amount)
            VALUES('$userid','$productid','$sizeid','1')
        ");
    }

    if ($ok) {
        return "המוצר נוסף לעגלה.";
    }

    return "אירעה שגיאה בהוספת המוצר לעגלה.";
}

// משמשת בעמודים ששולחים אימיילים במערכת
// שולחת אימייל בשם WearPair
function sendWearPairMail($to, $subject, $message)
{
    $headers = "From: WearPair <areenib112@gmail.com>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";

    return mail(
        $to,
        $subject,
        $message,
        $headers
    );
}
?>