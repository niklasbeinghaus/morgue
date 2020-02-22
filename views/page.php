<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="csrf-token" content="$csrf_token" />
    <meta name="csrf-param" content="_csrf" />
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="/assets/css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="/assets/css/bootstrap-datepicker.css" />
    <link rel="stylesheet" href="/assets/css/chosen.css" />
    <link rel="stylesheet" href="/assets/css/image_sizing.css" />
    <link rel="stylesheet" href="/assets/css/morgue.css" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="/assets/favicon/site.webmanifest">

    <title><?php echo isset($page_title) ? htmlentities($page_title) : 'Morgue' ?></title>

    <script crossorigin="anonymous" type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
    <script crossorigin="anonymous" type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>
    <script crossorigin="anonymous"
            type="text/javascript"
            src="https://cdnjs.cloudflare.com/ajax/libs/underscore.js/1.3.3/underscore-min.js"></script>
</head>
<body>
<?php include __DIR__ . '/header.php' ?>

<div class="container-fluid">
    <div class="row-fluid">
        <div class="span9">
            <?php
            // include our $content view if we can find it
            $incpath = stream_resolve_include_path($content . ".php");
            if ($incpath !== false) {
                include $incpath;
            } else {
                echo "Could not find $content";
            }
            ?>
        </div>
        <div class="span3">
            <?php if (!empty($show_sidebar)) {
                include __DIR__ . '/sidebar.php';
            } ?>
            <?php include __DIR__ . '/timezone.php' ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer.php' ?>
</body>
</html>
