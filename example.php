<?php

include('osgb36.php');

echo  "Example 1: from lat/long\n";
echo  "\$pos1: 50.9, -1.4 (Southampton,UK)\n";
$pos1 = \BlueRhinos\osgb36::createFromWGS84(50.9, -1.4);
echo "10 figure grid reference: ".$pos1->asGridRef(10)."\n";
echo "6 figure grid reference: ".$pos1->asGridRef(6, false)."\n";

echo "\n";

echo  "Example 2: from Grid Reference\n";
echo  "\$pos2: SU422113 (Southampton,UK)\n";
$pos2 = \BlueRhinos\osgb36::createFromGridRef('SU422113');
echo "Lat/Long: ".$pos2->lat.','.$pos2->lon."\n";
echo "Lat/Long DM: ".$pos2->asDMS('DM', 2)."\n";
echo "Lat/Long DMS: ".$pos2->asDMS('DMS', 2)."\n";
echo "is it GB: ".($pos2->isInGB() === true ? 'yes' : 'no')."\n";
echo "landranger map: ".print_r($pos2->hasAMap('landranger'),1 );
echo "explorer map: ".print_r($pos2->hasAMap('explorer'),1 );

echo "\n";

echo  "Example 3: location outside uk\n";
echo  "\$pos3: 48.8567, 2.3508 (Paris, France)\n";
$pos3 = \BlueRhinos\osgb36::createFromWGS84(48.8567, 2.3508);
echo "is in GB: ".($pos3->isInGB() === true ? 'yes' : 'no')."\n";

echo  "Example 4: Distance from Southampton to London\n";
echo  "\$pos4: 50.9, -1.4 (Southampton,UK) and 51.507222, -0.1275, (London,UK)\n";
$pos4 = \BlueRhinos\osgb36::createFromWGS84( 50.9, -1.4);
echo "distance in km: ".number_format($pos4->distanceFromWGS84(51.507222, -0.1275), 1)."\n";