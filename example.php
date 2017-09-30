<?php

include('osgb36.php');

$pos = \BlueRhinos\osgb36::createFromWGS84(50.9, -1.4);

echo "Grid Reference: ".$pos->asGridRef(10)."\n";
echo "Grid Reference: ".$pos->asGridRef(6, false)."\n";

$pos2 = \BlueRhinos\osgb36::createFromGridRef('SU422113');

echo "Lat/Long: ".$pos2->lat.','.$pos2->lon."\n";