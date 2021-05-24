<?php

require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

if ($_GET) {
    $to = $_GET['directTo'];
    switch ($to) {
        case "studyskills":
            $form = "form";
            $action = "http://www.studyskillshandbook.com.au/admin/users/login.php?ce=0&group=1&url=http://www.studyskillshandbook.com.au/inside/&amp;clogin=0";
            $method = "POST";
            $formArray['username'] = 'kings';
            $formArray['password'] = '77success';
            break;


        default:
            echo "Did not recognise the site";
            break;
    }


    ?>

    <html>
    <head>
        <title>Redirecting to <?php echo htmlentities($to) ?></title>
    </head>

    <?php
    echo "<body>";

    echo "<form method='{$method}' name='{$form}' action='{$action}'>";
    if (is_array($formArray)) {
        foreach ($formArray as $key => $value) {
            if ($key == "submit") {
                echo "<br><br><br><br><br><br><br><br><div align='center'>Please $value to continue<br>";
                echo "<input name='$key' type='submit' value='$value'></div>";
                $nojs = 1;
            } else {
                echo "<input name='$key' type='hidden' value='$value'>";
            }
        }
    }
    ?>
    </form>
    <script>
        <?php
        if (!($nojs)) {
            echo "document.{$form}.submit();";
        }
        ?>
    </script>
    </body>
    </html>
    <?php
}
?>
