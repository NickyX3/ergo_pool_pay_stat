<?php
    /*
     * ERGO Miner Pools Payment Statistic, periods between payments in hours and minutes
     * Copyright by NickyX3 (nickyx3@gmail.com) (c) 2022. GPL Licence
     * Spagetti code style, because I didn't want to do beautiful :-) PHP version 7.0+
     * available request parameters: pool=nanopool|herominers, wallet=your_ergo_wallet, begindate=DD.MM.YYYY (only for nanopool)
     * if you want send donation in ERGO: 9hcGFUNgyRUaBFct2uYFanUgXppY3mCmmUzdmwkvYMaUaWxutr1
    */

    // set your default ERGO wallet
    $default_wallet = '9hcGFUNgyRUaBFct2uYFanUgXppY3mCmmUzdmwkvYMaUaWxutr1';
    $default_pool   = 'nanopool'; // avaible 'nanopool' or 'herominers'

    // reject records more that hours, example this is rig maintenance time
    $exclude_more_hours = 300;

    // check wallet in request
    if ( isset($_REQUEST['wallet']) && $_REQUEST['wallet'] != '' ) {
        // set ERGO wallet from request
        $wallet = trim($_REQUEST['wallet']);
    } else {
        // default wallet
        $wallet	= $default_wallet;
    }
    if ( isset($_REQUEST['pool']) && $_REQUEST['pool'] != '') {
        $pool   = trim($_REQUEST['pool']);
    } else {
        $pool   = $default_pool;
    }

    if ( $pool == 'nanopool' ) {
        $data = getErgoPaymentsNanoPool($wallet);
    } elseif ( $pool == 'herominers' ) {
        $data = getErgoPaymentsHeroMiners($wallet);
    } else {
        echo 'No pool!'.PHP_EOL; exit;
    }

	$seconds		= array_column($data, 'diff');
	$maxseconds		= max($seconds);
	$minseconds		= min($seconds);
	$one_percent	= intval($maxseconds/200);
	
	$colors			= [];
	$colors['main']	= '#ffc107c7';
	$colors['max']	= '#f375afc7';
	$colors['min']	= '#2df5abc7';
	
$styles = <<<'EOD'
    body {
        font-family: "Open Sans", "Trebuchet MS", "Helvetica CY", sans-serif; font-size: 10px;
    }
	.ruler {
		background-image: linear-gradient(270deg, rgb(0 0 0 / 22%) 1px, rgba(0,0,0,0) 1px),linear-gradient(270deg, rgb(255 0 0 / 59%) 1px, rgba(0,0,0,0) 1px);
    	background-size: 1%, 5%;
	}
EOD;
	
	echo '<html><head><style>'.PHP_EOL.$styles.'</style>'.PHP_EOL;

	echo '<body>'.PHP_EOL;
	echo '<div style="width: 100%" class="ruler" data-maxseconds="'.$maxseconds.'" data-onepercent="'.$one_percent.'">'.PHP_EOL;

	$data	= array_reverse($data);
	foreach ($data as $entry) {
		$w_percent	= $entry['diff']/$one_percent;
		if ( $entry['diff'] == $maxseconds ) {
			$color 	= $colors['max'];
		} elseif ( $entry['diff'] == $minseconds ) {
			$color 	= $colors['min'];
		} else {
			$color 	= $colors['main'];
		}
		if ( $entry['diff_h'] < $exclude_more_hours ) {
			echo '<div style="width: 100%; margin-bottom: 1px;" data-diff="'.$entry['diff'].'" data-diffh="'.$entry['diff_h'].'">'.PHP_EOL;
			echo '<div style="width: '.$w_percent.'%; padding: 4px; background-color: '.$color.';">'.$entry['end'].' = '.$entry['diff_h'].':'.$entry['diff_m'].'</div>'.PHP_EOL.'</div>'.PHP_EOL;
		}
	}
	echo '</div>'.PHP_EOL.'</body>'.PHP_EOL.'</html>';

	exit;
	

	/* get data from nanopool  */
	function getErgoPaymentsNanoPool( string $wallet ):array {
        if ( isset($_REQUEST['begindate']) && $_REQUEST['begindate'] != '' ) {
            // begin date  timestamp from request date
            $begin = make_tsfromdate(trim($_REQUEST['begindate']));
        } else {
            // begin timestamp is begin year 2021
            $begin = make_tsfromdate('01.01.2021');
        }
		$end	= time();
		
		$link	= 'https://ergo.nanopool.org/api/v1/payments_interval/'.$wallet.'/'.$begin.'/'.$end;

        $dates	        = [];
		$filecontent	= file_get_contents($link);
		if ( $filecontent != '' && $json = json_decode($filecontent,true) ) {
			if ( count($json['data']) > 0 ) {
				foreach ($json['data'] as $payment) {
					$dt = new DateTime();
					$dt->setTimezone(new DateTimeZone('Asia/Yekaterinburg'));
					$dt->setTimestamp($payment['date']);
					$dates[]	= $dt->format('Y-m-d H:i:s');
				}
			}
		}

        $c  = count($dates);
        if ( $c > 0 ) {
            $dates      = array_reverse($dates);

            $current    = [];
            $next       = [];
            $data       = [];

            for ($i = 0; $i < $c; $i++) {
                $current[$i] = new DateTime($dates[$i]);
                if (isset($dates[$i + 1])) {
                    $next[$i]   = new DateTime($dates[$i + 1]);
                    $diff_s     = $next[$i]->getTimestamp() - $current[$i]->getTimestamp();
                    $diff_h     = intval($diff_s / 3600);
                    $diff_m     = intval(($diff_s - $diff_h * 3600) / 60);
                    $data[]     = ['begin' => $dates[$i], 'end' => $dates[$i + 1], 'diff_h' => $diff_h, 'diff_m' => $diff_m, 'diff' => $diff_s];
                }
            }
            return $data;
        } else {
            return [];
        }
	}

    /* get data from herominers  */
    function getErgoPaymentsHeroMiners( string $wallet ):array {
        $now	= time();
        $link	= 'https://ergo.herominers.com/api/get_payments?time='.$now.'&address='.$wallet;

        $filecontent	= file_get_contents($link);
        if ( $filecontent != '' && $json = json_decode($filecontent,true) ) {
            $counts = count($json);
            $dates	= [];
            for ($i=0;$i<$counts;$i=$i+2) {
                $data 	= $json[$i];
                $tms	= $json[$i+1];
                list($tr,$nanoerg,$fee) = explode(':', $data);
                $tr; $fee;
                $dt = new DateTime();
                $dt->setTimezone(new DateTimeZone('Asia/Yekaterinburg'));
                $dt->setTimestamp($tms);
                $entry	= [
                    'tms' 	=> $tms,
                    'date'	=> $dt->format('Y-m-d H:i:s'),
                    'ergo'	=> $nanoerg/1000000000,
                ];
                $dates[]	= $entry;
            }

            $dates	       = array_reverse($dates);
            $current	= [];
            $next		= [];
            $data		= [];

            $c 			= count($dates);
            for ($i = 0; $i < $c; $i++) {
                $current[$i] = $dates[$i]['tms'];
                if ( isset($dates[$i+1]) ) {
                    $next[$i]	= $dates[$i+1]['tms'];
                    $diff_s		= $next[$i] - $current[$i];
                    $diff_h		= intval($diff_s/3600);
                    $diff_m		= intval(($diff_s-$diff_h*3600)/60);
                    $data[]		= ['ergo'=>$dates[$i]['ergo'],'begin'=>$dates[$i]['date'],'end'=>$dates[$i+1]['date'],'diff_h'=>$diff_h,'diff_m'=>$diff_m,'diff'=>$diff_s];
                }
            }
            return $data;
        } else {
            return [];
        }
    }

    /* some stupid utilities */
    /* make timestamp from date dd.mm.yyyy  */
    function make_tsfromdate ( string $humandate='01.01.1970' ):int {
        list($d,$m,$y) = explode('.',$humandate);
        return mktime(0,0,0,intval($m),intval($d),intval($y));
    }

    /* get begin year timestamp by timestamp */
    function get_begin_year_ts ( int $ts=0):int {
        if ( $ts !== 0 ) {
            return mktime (0, 0, 0, 1,1, date("Y",$ts));
        } else {
            return mktime (0, 0, 0, 1,1, date("Y"));
        }
    }
