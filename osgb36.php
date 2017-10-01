<?php

namespace BlueRhinos;

/*
 	osgb36
	Simple osgb36 conversion toolkit

    Attributions
        The conversion functions are based on the work of http://www.movable-type.co.uk
        The point in polygon function is from http://assemblysys.com/php-point-in-polygon-algorithm/
*/

/*
	Licence

	Copyright (c) 2017 Blue Rhinos Consulting | Andrew Milsted
	andrew@bluerhinos.co.uk | http://www.bluerhinos.co.uk

	Permission is hereby granted, free of charge, to any person obtaining a copy
	of this software and associated documentation files (the "Software"), to deal
	in the Software without restriction, including without limitation the rights
	to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
	copies of the Software, and to permit persons to whom the Software is
	furnished to do so, subject to the following conditions:

	The above copyright notice and this permission notice shall be included in
	all copies or substantial portions of the Software.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

*/

/* osgb36 */
class osgb36 {

    /**
     * The current latitude (WGS84)
     * @var float
     */
    public $lat;

    /**
     * The current longitude (WGS84)
     * @var float
     */
    public $lon;

    /**
     * The current altitude (WGS84)
     * @var float
     */
    public $height;

    /**
     * The current latitude (OSGB36)
     * @var float
     */
    public $os_lat;

    /**
     * The current longitude (OSGB36)
     * @var float
     */
    public $os_lon;

    /**
     * The current altitude (OSGB36)
     * @var float
     */
    public $os_height;

    /**
     * The northings position (OSGB36)
     * @var float
     */
    public $north;

    /**
     * The eastings position (OSGB36)
     * @var float
     */
    public $east;

    /**
     * ellipse parameters
     * @var array
     */
    protected $e = array(
        'WGS84' => array(
            'a'=>6378137,
            'b'=> 6356752.3142,
            'f'=> 0.00335281066475
        ),
        'Airy1830' => array(
            'a'=> 6377563.396,
            'b'=>6356256.910,
            'f'=> 0.0033408506415
        )
    );

    /**
     * helmert transform parameters
     * @var array
     */
    private $h = array(
        'WGS84toOSGB36'=> array(
            'tx' => -446.448,
            'ty' => 125.157,
            'tz' => -542.060,
            'rx' => -0.1502,
            'ry' => -0.2470,
            'rz' => -0.8421,
            's' => 20.4894
        ),
        'OSGB36toWGS84'=> array(
            'tx' => 446.448,
            'ty' => -125.157,
            'tz' => 542.060,
            'rx' => 0.1502,
            'ry' => 0.2470,
            'rz' => 0.8421,
            's' => -20.4894
        )
    );

    /**
     * GB polygon that encircles the coverage of OS
     * @var array
     */
    private $gb = array(
        array(67919, 0),
        array(199488, 0),
        array(307502, 30473),
        array(414691, 60832),
        array(540032, 75685),
        array(632034, 98379),
        array(664677, 151949),
        array(677568, 258087),
        array(658620, 359199),
        array(586103, 393753),
        array(473094, 591204),
        array(389931, 717210),
        array(435056, 799984),
        array(427111, 906851),
        array(348400, 904402),
        array(490128, 1133972),
        array(516120, 1234632),
        array(455840, 1264920),
        array(135463, 1115119),
        array(0, 983469),
        array(0, 725288),
        array(71476, 667325),
        array(139734, 619270),
        array(163110, 581867),
        array(205709, 519035),
        array(237191, 516155),
        array(276613, 529647),
        array(293901, 464542),
        array(310046, 394559),
        array(230583, 406901),
        array(208024, 363916),
        array(202386, 310331),
        array(155062, 248502),
        array(130167, 215750),
        array(173779, 182643),
        array(206111, 147420),
        array(106242, 42291),
        array(61292, 20190),
        array(67919, 0),
    );

    /**
     * Urls to the json that describe the available maps.
     * @var array
     */
    protected $osmaps = array(
        'explorer' => 'https://api.ordnancesurvey.co.uk/osl/v1/mapsheet/explorer?bbox=0,0,700000,1300000,27700&tsrs=27700',
        'landranger' => 'https://api.ordnancesurvey.co.uk/osl/v1/mapsheet/landranger?bbox=0,0,700000,1300000,27700&tsrs=27700',
        'historic1896' => 'https://www.ordnancesurvey.co.uk/shop/clickable-map/assets/historic1896.json'
    );

    /**
     * Location of remote files that should be cached (eg the os maps json)
     * @var string
     */
    public $cache = '/tmp/osmaps';

    /**
     * Number of seconds that the cached items should be kept for
     * 2592000 = 30days
     * @var int
     */
    public $cache_time = 2592000;

    /**
     * Will convert the current OSGB36 lat/long into easting/northings in m
     */
    public function toGrid() {

        $lat = deg2rad($this->os_lat);
        $lon = deg2rad($this->os_lon);

        $a = 6377563.396; $b = 6356256.910;          // Airy 1830 major & minor semi-axes
        $F0 = 0.9996012717;                         // NatGrid scale factor on central meridian
        $lat0 = deg2rad(49); $lon0 = deg2rad(-2);  // NatGrid true origin
        $N0 = -100000; $E0 = 400000;                 // northing & easting of true origin, metres
        $e2 = 1 - ($b*$b)/($a*$a);                      // eccentricity squared
        $n = ($a-$b)/($a+$b); $n2 = $n*$n; $n3 = $n*$n*$n;

        $cosLat = cos($lat); $sinLat = sin($lat);
        $nu = $a*$F0/sqrt(1-$e2*$sinLat*$sinLat);              // transverse radius of curvature
        $rho = $a*$F0*(1-$e2)/pow(1-$e2*$sinLat*$sinLat, 1.5);  // meridional radius of curvature
        $eta2 = $nu/$rho-1;

        $Ma = (1 + $n + (5/4)*$n2 + (5/4)*$n3) * ($lat-$lat0);
        $Mb = (3*$n + 3*$n2 + (21/8)*$n3) * sin($lat-$lat0) * cos($lat+$lat0);
        $Mc = ((15/8)*$n2 + (15/8)*$n3) * sin(2*($lat-$lat0)) * cos(2*($lat+$lat0));
        $Md = (35/24)*$n3 * sin(3*($lat-$lat0)) * cos(3*($lat+$lat0));
        $M = $b * $F0 * ($Ma - $Mb + $Mc - $Md);              // meridional arc

        $cos3lat = $cosLat*$cosLat*$cosLat;
        $cos5lat = $cos3lat*$cosLat*$cosLat;
        $tan2lat = tan($lat)*tan($lat);
        $tan4lat = $tan2lat*$tan2lat;

        $I = $M + $N0;
        $II = ($nu/2)*$sinLat*$cosLat;
        $III = ($nu/24)*$sinLat*$cos3lat*(5-$tan2lat+9*$eta2);
        $IIIA = ($nu/720)*$sinLat*$cos5lat*(61-58*$tan2lat+$tan4lat);
        $IV = $nu*$cosLat;
        $V = ($nu/6)*$cos3lat*($nu/$rho-$tan2lat);
        $VI = ($nu/120) * $cos5lat * (5 - 18*$tan2lat + $tan4lat + 14*$eta2 - 58*$tan2lat*$eta2);

        $dLon = $lon-$lon0;
        $dLon2 = $dLon*$dLon; $dLon3 = $dLon2*$dLon; $dLon4 = $dLon3*$dLon; $dLon5 = $dLon4*$dLon; $dLon6 = $dLon5*$dLon;

        $this->north = $I + $II*$dLon2 + $III*$dLon4 + $IIIA*$dLon6;
        $this->east = $E0 + $IV*$dLon + $V*$dLon3 + $VI*$dLon5;

    }

    /**
     * Converts the current OSGB36 position into WGS84
     */
    protected function _convertOSGB36toWGS84() {
        $p1 = array("lat"=>$this->os_lat,"lon"=>$this->os_lon,"height"=>$this->os_height);
        $p2 = $this->_convert($p1, $this->e['Airy1830'], $this->h['OSGB36toWGS84'], $this->e['WGS84']);
        $this->lat = $p2['lat'];
        $this->lon = $p2['lon'];
        $this->height = $p2['height'];
    }

    /**
     * Converts the current WGS84 position into OSGB36
     */
    protected function _convertWGS84toOSGB36() {
        $p1 = array("lat"=>$this->lat,"lon"=>$this->lon,"height"=>(float)$this->height);
        $p2 = $this->_convert($p1, $this->e['WGS84'], $this->h['WGS84toOSGB36'], $this->e['Airy1830']);
        $this->os_lat = $p2['lat'];
        $this->os_lon = $p2['lon'];
        $this->os_height = $p2['height'];
    }

    /**
     * converts lat/long from one ellipse to another
     *
     * @param array $p   source position as lat, lon, height
     * @param array $e1  source ellipse parameter
     * @param array $t   helmert transform parameters
     * @param array $e2  destination ellipse parameter
     *
     * @return array as lat, lon, height
     */
    protected function _convert($p, $e1, $t, $e2) {
        // -- convert polar to cartesian coordinates (using ellipse 1)

        $p1['lat'] = deg2rad($p['lat']);
        $p1['lon'] = deg2rad($p['lon']);
        $p1['height'] = 0;
        $a = $e1['a']; $b = $e1['b'];

        $sinPhi = sin($p1['lat']); $cosPhi = cos($p1['lat']);
        $sinLambda = sin($p1['lon']); $cosLambda = cos($p1['lon']);
        $H = $p1['height'];

        $eSq = ($a*$a - $b*$b) / ($a*$a);
        $nu = $a / sqrt(1 - $eSq*$sinPhi*$sinPhi);

        $x1 = ($nu+$H) * $cosPhi * $cosLambda;
        $y1 = ($nu+$H) * $cosPhi * $sinLambda;
        $z1 = ((1-$eSq)*$nu + $H) * $sinPhi;


        // -- apply helmert transform using appropriate params

        $tx = $t['tx']; $ty = $t['ty']; $tz = $t['tz'];
        $rx = $t['rx']/3600 * M_PI/180;  // normalise seconds to radians
        $ry = $t['ry']/3600 * M_PI/180;
        $rz = $t['rz']/3600 * M_PI/180;
        $s1 = $t['s']/1e6 + 1;              // normalise ppm to (s+1)

        // apply transform
        $x2 = $tx + $x1*$s1 - $y1*$rz + $z1*$ry;
        $y2 = $ty + $x1*$rz + $y1*$s1 - $z1*$rx;
        $z2 = $tz - $x1*$ry + $y1*$rx + $z1*$s1;

        // -- convert cartesian to polar coordinates (using ellipse 2)

        $a = $e2['a']; $b = $e2['b'];
        $precision = 4 / $a;  // results accurate to around 4 metres

        $eSq = ($a*$a - $b*$b) / ($a*$a);
        $p = sqrt($x2*$x2 + $y2*$y2);
        $phi = atan2($z2, $p*(1-$eSq)); $phiP = 2*M_PI;
        while (abs($phi-$phiP) > $precision) {
            $nu = $a / sqrt(1 - $eSq*sin($phi)*sin($phi));
            $phiP = $phi;
            $phi = atan2($z2 + $eSq*$nu*sin($phi), $p);
        }
        $lambda = atan2($y2, $x2);
        $H = $p/cos($phi) - $nu;

        return array("lat"=> rad2deg($phi) , "lon"=> rad2deg($lambda), "height"=> $H);
    }

    /**
     * convert standard grid reference ('SU387148') to fully numeric ref ([438700,114800])
     *
     * @param string $gridref
     */
    protected function _posFromGrid($gridref) {
        // get numeric values of letter references, mapping A->0, B->1, C->2, etc:
        $l1 = ord(strtoupper($gridref{0})) - ord('A');
        $l2 = ord(strtoupper($gridref{1})) - ord('A');
        // shuffle down letters after 'I' since 'I' is not used in grid:
        if ($l1 > 7) $l1--;
        if ($l2 > 7) $l2--;

        // convert grid letters into 100km-square indexes from false origin (grid square SV):
        $e = (($l1-2)%5)*5 + ($l2%5);
        $n = (19-floor($l1/5)*5) - floor($l2/5);
        $e *= 100000;
        $n *= 100000;
        // skip grid letters to get numeric part of ref, stripping any spaces:
        $gridref = str_replace(" ","",substr($gridref,2));

        switch (strlen($gridref)) {
            case 4: $sc = 1000; break;
            case 6: $sc = 100; break;
            case 8: $sc = 10; break;
            case 10: $sc = 1; break;
        }

        // append numeric part of references to grid index:
        $e += substr($gridref,0, strlen($gridref)/2)*$sc;
        $n += substr($gridref,strlen($gridref)/2)*$sc;

        // normalise to 1m grid, rounding up to centre of grid square:
        switch (strlen($gridref)) {
            case 4: $e += '500';$n += '500'; break;
            case 6: $e += '50';$n += '50'; break;
            case 8: $e += '5'; $n += '5'; break;
            // 10-digit refs are already 1m
        }

        $this->north = $n;
        $this->east = $e;
    }

    /**
     * convert OS grid reference to geodesic co-ordinates
     */
    protected function _toLatLong() {
        $E = $this->east; $N = $this->north;

        $a = 6377563.396; $b = 6356256.910;              // Airy 1830 major & minor semi-axes
        $F0 = 0.9996012717;                             // NatGrid scale factor on central meridian
        $lat0 = 49*M_PI/180; $lon0 = -2*M_PI/180;  // NatGrid true origin
        $N0 = -100000; $E0 = 400000;                     // northing & easting of true origin, metres
        $e2 = 1 - ($b*$b)/($a*$a);                          // eccentricity squared
        $n = ($a-$b)/($a+$b); $n2 = $n*$n; $n3 = $n*$n*$n;

        $lat=$lat0; $M=0;
        do {
            $lat = ($N-$N0-$M)/($a*$F0) + $lat;

            $Ma = (1 + $n + (5/4)*$n2 + (5/4)*$n3) * ($lat-$lat0);
            $Mb = (3*$n + 3*$n*$n + (21/8)*$n3) * sin($lat-$lat0) * cos($lat+$lat0);
            $Mc = ((15/8)*$n2 + (15/8)*$n3) * sin(2*($lat-$lat0)) * cos(2*($lat+$lat0));
            $Md = (35/24)*$n3 * sin(3*($lat-$lat0)) * cos(3*($lat+$lat0));
            $M = $b * $F0 * ($Ma - $Mb + $Mc - $Md);                // meridional arc

        } while ($N-$N0-$M >= 0.00001);  // ie until < 0.01mm

        $cosLat = cos($lat); $sinLat = sin($lat);
        $nu = $a*$F0/sqrt(1-$e2*$sinLat*$sinLat);              // transverse radius of curvature
        $rho = $a*$F0*(1-$e2)/pow(1-$e2*$sinLat*$sinLat, 1.5);  // meridional radius of curvature
        $eta2 = $nu/$rho-1;

        $tanLat = tan($lat);
        $tan2lat = $tanLat*$tanLat; $tan4lat = $tan2lat*$tan2lat; $tan6lat = $tan4lat*$tan2lat;
        $secLat = 1/$cosLat;
        $nu3 = $nu*$nu*$nu; $nu5 = $nu3*$nu*$nu; $nu7 = $nu5*$nu*$nu;
        $VII = $tanLat/(2*$rho*$nu);
        $VIII = $tanLat/(24*$rho*$nu3)*(5+3*$tan2lat+$eta2-9*$tan2lat*$eta2);
        $IX = $tanLat/(720*$rho*$nu5)*(61+90*$tan2lat+45*$tan4lat);
        $X = $secLat/$nu;
        $XI = $secLat/(6*$nu3)*($nu/$rho+2*$tan2lat);
        $XII = $secLat/(120*$nu5)*(5+28*$tan2lat+24*$tan4lat);
        $XIIA = $secLat/(5040*$nu7)*(61+662*$tan2lat+1320*$tan4lat+720*$tan6lat);

        $dE = ($E-$E0); $dE2 = $dE*$dE; $dE3 = $dE2*$dE; $dE4 = $dE2*$dE2; $dE5 = $dE3*$dE2; $dE6 = $dE4*$dE2; $dE7 = $dE5*$dE2;
        $lat = $lat - $VII*$dE2 + $VIII*$dE4 - $IX*$dE6;
        $lon = $lon0 + $X*$dE - $XI*$dE3 + $XII*$dE5 - $XIIA*$dE7;

        $this->os_lat = rad2deg($lat);
        $this->os_lon = rad2deg($lon);

    }

    /**
     * Convert numeric grid reference (in metres) to standard-form grid ref
     *
     * @param int  $digits number of digits
     * @param bool $spaces should the gridreference have spaces.
     *
     * @return bool|string grid reference (false on possition outside grid references)
     */
    public function asGridRef($digits, $spaces = true) {

        $e = $this->east;
        $n = $this->north;

        // get the 100km-grid indices
        $e100k = floor($e/100000); $n100k = floor($n/100000);

        if ($e100k<0 || $e100k>6 || $n100k<0 || $n100k>12) return false;

        // translate those into numeric equivalents of the grid letters
        $l1 = (19-$n100k) - (19-$n100k)%5 + floor(($e100k+10)/5);
        $l2 = (19-$n100k)*5%25 + $e100k%5;

        // compensate for skipped 'I' and build grid letter-pairs
        if ($l1 > 7) $l1++;
        if ($l2 > 7) $l2++;
        $letPair = chr($l1+ord('A')).chr($l2+ord('A'));

        if(!$digits)
            return $letPair;
        // strip 100km-grid indices from easting & northing, and reduce precision
        $e = floor(($e%100000)/pow(10,5-$digits/2));
        $n = floor(($n%100000)/pow(10,5-$digits/2));
        // note use of floor, as ref is bottom-left of relevant square!
        $d = $digits/2;

        $space = ($spaces === true) ? ' ' : '';

        $gridRef = sprintf("%s%s%0{$d}d%s%0{$d}d",$letPair,$space, $e, $space, $n);

        return $gridRef;
    }


    /**
     * Creates a osgb36 object from a WGS84 latitude/longitude
     *
     * @param float $lat decimal latitude (- for southern latitudes)
     * @param float $lond ecimal logitude (- for western latitudes)
     *
     * @return osgb36
     */
    public static function createFromWGS84($lat, $lon) {

        $pos = new osgb36();

        $pos->lat = $lat;
        $pos->lon = $lon;

        $pos->_convertWGS84toOSGB36();
        $pos->toGrid();

        return $pos;
    }

    /**
     * Creates a osgb36 object from a Grid Reference
     *
     * @param string $grid Grid Reference eg SU 127 277
     *
     * @return osgb36
     */
    public static function createFromGridRef($grid) {

        $pos = new osgb36();

        $pos->_posFromGrid($grid);
        $pos->_toLatLong();
        $pos->_convertOSGB36toWGS84();

        return $pos;
    }

    /**
     *  Creates a osgb36 object from an easting and northing
     *
     * @param float $east easting in m
     * @param float $north northing in m
     *
     * @return osgb36
     */
    public static function createFromEastNorth($east, $north) {

        $pos = new osgb36();

        $pos->north = $north;
        $pos->east = $east;

        $pos->_toLatLong();
        $pos->_convertOSGB36toWGS84();

        return $pos;
    }

    /**
     * formats a decimal latitude/longitude to a display string
     *
     * @param float  $dec    decimal lat or long
     * @param string $format format D - decimal ($digits is the number of decimal places)
     *                              DM - decimal minutes 50°54.01'N, -2°35.97'W ($digits is the number of decimal places of mins)
     *                              DMS - decimal minutes seconds 050°54'00" N, 001°24'02" W ($digits is the number of leading zeros of whole degrees)
     * @param string $which   lat or long (used for N/S/E/W)
     * @param int $digits     number of digits (see above)
     *
     * @return string
     */
    public static function formatDMS($dec, $format,$which, $digits = 0){

        $dir['lat']['+'] = "N";
        $dir['lat']['-'] = "S";
        $dir['long']['-'] = "W";
        $dir['long']['+'] = "E";

        switch($format){
            case "D":
                $ret = round($dec, $digits);
                break;
            case "DMS":
                $de = abs($dec);
                $deg = floor($de);
                $de = 60 * ($de-$deg);
                $min = floor($de);
                $de = 60 * ($de-$min);
                $sec = round($de);

                $sign = $dec > 0 ? "+" : "-";
                $ret = sprintf("%03d".chr(176)."%02d'%02d\" ".$dir[$which][$sign], $deg,$min,$sec);

                break;
            case "DM":
                $de = abs($dec);
                $deg = floor($de);
                $de = 60 * ($de-$deg);
                $min = round($de,$digits);

                $sign = $dec > 0 ? "+" : "-";
                $ret = $deg.chr(176).$min."'".$dir[$which][$sign];

                break;
        }

        return $ret;
    }

    /**
     * formats current WGS84 position as a displayed string
     *
     * @param string $format format D - decimal ($digits is the number of decimal places)
     *                              DM - decimal minutes 50°54.01'N, -2°35.97'W ($digits is the number of decimal places of mins)
     *                              DMS - decimal minutes seconds 050°54'00" N, 001°24'02" W ($digits is the number of leading zeros of whole degrees)
     * @param int $digits    number of digits (see above)
     * @param string $join   the string used to join the lat/long
     *
     * @return string
     */
    public function asDMS($format, $digits = 0, $join = ', ') {
        return self::formatDMS($this->lat, $format, 'lat', $digits).$join.self::formatDMS($this->lon, $format, 'long', $digits);
    }

    /**
     * Is the current position in GB
     * @return bool
     */
    public function isInGB() {
        if($this->east < 0 || $this->east > 700000 || $this->north < 0 || $this->north > 1300000) {
            return false;
        }
        return $this->_pointInPolygon($this->east, $this->north, $this->gb) === 'inside';
    }

    /**
     * Checks the current position is on a map and returns info about the maps.
     *
     * @param string $map_type Map type you are looking for, eg explorer, landranger or historic
     *
     * @return array
     */
    public function hasAMap($map_type) {

        if (isset($this->osmaps[$map_type]) === false) {
            throw new \RuntimeException('$map_type not found.');
        }

        $url = $this->osmaps[$map_type];
        $cache_file = $this->cache.'/'.$map_type.'.json';
        if (file_exists($cache_file) === false || filemtime($cache_file) < (time() - $this->cache_time)) {
            if (file_exists($this->cache) === false) {
                mkdir($this->cache, 0777, true);
            }
            copy($url, $cache_file);
        }

        $data = json_decode(file_get_contents($cache_file), true);
        $distance_from_center = 999999999;
        $found = false;
        foreach ($data['features'] as $feature) {
            foreach($feature['geometry']['coordinates'] as $polygon) {
                if ($this->_pointInPolygon($this->east, $this->north, $polygon) !== 'outside') {
                    $bbox = $feature['geometry']['bbox'];
                    $center = array(
                        ($bbox[3] + $bbox[0]) / 2,
                        ($bbox[4] + $bbox[1]) / 2,
                    );
                    $distance = round(
                        sqrt(
                        pow($center[0] - $this->east,2) + pow($center[1] - $this->north,2)
                        )
                    );
                    if ($distance_from_center > $distance) {
                        $found = $feature['properties'];
                        $distance_from_center = $distance;
                    }
                }
            }

        }

        if ($found !== false) {
           return array(
               'url' => $found['url'],
               'sheet' => $found['SHEET'],
               'title' => $found['TITLE'],
               'sub_title' => $found['SUB_TITLE'],
               'number' => $found['NUMBER'],
               'pic' => ($map_type === 'historic') ? 'https://www.ordnancesurvey.co.uk/shop/clickable-map//assets/historic1896-front-cover/historic1896.jpg' : sprintf('https://www.ordnancesurvey.co.uk/shop/clickable-map//assets/%s-front-cover/%03d.jpg', $map_type, $found['NUMBER'])
           );
        }

    }

    /**
     * looks where a point is in relation to a polygon
     *
     * @param float $east
     * @param float $north
     * @param array $polygon array of ponts
     *
     * @return string - where the point is 'vertex', 'boundary', 'inside', 'outside'
     */
    protected function _pointInPolygon($east, $north, $polygon) {

        $point = array($east, $north);

        // Check if the point sits exactly on a vertex
        foreach($polygon as $vertex) {
            if ($point == $vertex) {
                return "vertex";
            }
        }

        // Check if the point is inside the polygon or on the boundary
        $intersections = 0;
        $vertices_count = count($polygon);

        for ($i=1; $i < $vertices_count; $i++) {
            $vertex1 = $polygon[$i-1];
            $vertex2 = $polygon[$i];
            if ($vertex1[1] == $vertex2[1] and $vertex1[1] == $point[1] and $point[0] > min($vertex1[0], $vertex2[0]) and $point[0] < max($vertex1[0], $vertex2[0])) { // Check if point is on an horizontal polygon boundary
                return "boundary";
            }
            if ($point[1] > min($vertex1[1], $vertex2[1]) and $point[1] <= max($vertex1[1], $vertex2[1]) and $point[0] <= max($vertex1[0], $vertex2[0]) and $vertex1[1] != $vertex2[1]) {
                $xinters = ($point[1] - $vertex1[1]) * ($vertex2[0] - $vertex1[0]) / ($vertex2[1] - $vertex1[1]) + $vertex1[0];
                if ($xinters == $point[0]) { // Check if point is on the polygon boundary (other than horizontal)
                    return "boundary";
                }
                if ($vertex1[0] == $vertex2[0] || $point[0] <= $xinters) {
                    $intersections++;
                }
            }
        }
        // If the number of edges we passed through is odd, then it's in the polygon.
        if ($intersections % 2 != 0) {
            return "inside";
        } else {
            return "outside";
        }
    }


}