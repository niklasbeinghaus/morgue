<h3><?php
$count = count($events);
echo $count;
?> postmortem<?php
    if ($count > 1) {
        echo "s";
    }
?> from the last <?php echo $days_back ?> <?php
echo ($days_back === 1) ? 'day' : 'days';
?></h3>
<?php

include __DIR__ . "/../../../views/content/frontpage.php";

