<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$herLink = "login.php";
$himLink = "login.php";

if (isset($_SESSION["fname"])) {
    $herLink = "women.php";
    $himLink = "men.php";
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>WearPair - סטיילינג אישי</title>

<style>

body {
    margin: 0;
    background: #111111;
    color: silver;
    font-family: Arial, sans-serif;
    text-align: center;
    direction: rtl;
}

.hero {
    position: relative;
    width: 100%;
    height: 100vh;
}

.hero img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.collection-link {
    position: absolute;
    top: 58%;
    color: black;
    font-family: Georgia, serif;
    font-size: 42px;
    font-weight: bold;
    text-decoration: none;
    border-bottom: 2px solid black;
}

.collection-link:hover {
    color: #333333;
    border-color: white;
}

.for-her {
    left: 20%;
}

.for-him {
    right: 20%;
}

.about-section {
    padding: 60px 20px;
    background: #0d0d0d;
}

.about-section h2 {
    margin-top: 0;
    color: white;
    font-size: 34px;
}

.about-section > p {
    max-width: 850px;
    margin: auto;
    color: #d0d0d0;
    font-size: 18px;
    line-height: 1.7;
}

.cards {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 25px;
    margin-top: 40px;
}

.card {
    width: 260px;
    padding: 25px;
    background: #181818;
    border: 1px solid #333333;
    border-radius: 12px;
}

.card h3 {
    margin-top: 0;
    color: white;
    font-size: 22px;
}

.card p {
    color: #d3d3d3;
    font-size: 16px;
    line-height: 1.6;
}

@media screen and (max-width: 700px) {

    .collection-link {
        top: 82%;
        font-size: 24px;
    }

    .for-her {
        left: 8%;
    }

    .for-him {
        right: 8%;
    }

    .card {
        width: 85%;
    }

    .about-section h2 {
        font-size: 28px;
    }
}

</style>

</head>

<body>

<?php require("nav.php"); ?>

<div class="hero">

    <img src="homepic.png" alt="WearPair">

    <a href="<?php echo $herLink; ?>"
       class="collection-link for-her">
        לנשים
    </a>

    <a href="<?php echo $himLink; ?>"
       class="collection-link for-him">
        לגברים
    </a>

</div>


<div class="about-section">

    <h2>הסטייל שלך, בדרך שמתאימה לך</h2>

    <p>
        WearPair היא מערכת לסטיילינג אישי שעוזרת ללקוחות למצוא
        מוצרים שמתאימים למידות ולהעדפות שלהם, ולקבל המלצות אישיות
        מסטייליסט.
    </p>


    <div class="cards">

        <div class="card">
            <h3>מציאת מוצרים מתאימים</h3>

            <p>
                ניתן לבחור קטגוריה, להזין מידות ולמצוא מוצרים
                שמתאימים למידות האישיות של הלקוח.
            </p>
        </div>


        <div class="card">
            <h3>פגישה עם סטייליסט</h3>

            <p>
                הלקוח יכול לקבוע פגישה עם סטייליסט ולהזין את
                המידות וההעדפות שלו לפני הפגישה.
            </p>
        </div>


        <div class="card">
            <h3>המלצות אישיות</h3>

            <p>
                הסטייליסט יכול לראות את פרטי הלקוח והמידות שלו,
                לבדוק מוצרים מתאימים ולשלוח המלצות אישיות.
            </p>
        </div>

    </div>

</div>

</body>
</html>