<?php
//IMathAS:  "Quick Drill" player
//(c) 2009 David Lippman

//options:  	id=questionsetid
//		cid=courseid (not required)
//		sa=show answer options:  0 - show score and answer if wrong
//					 1 - show score, but don't reshow w answer
//					 2 - don't show score
//					 3 - don't show score; show answer button
//					 4 - show score, don't show answer, make students redo
//		n=#  do n questions then stop
//		nc=#  do until nc questions are correct then stop
//		t=#  do as many questions as possible in t seconds

if (isset($_GET['public'])) {
	require("../config.php");
	session_start();
	$_SESSION['publicquickdrill'] = true;
	function writesessiondata() {
		global $sessiondata;
		$_SESSION['data'] = base64_encode(serialize($sessiondata));
	}
	if (!isset($_SESSION['data'])) {
		$sessiondata = array();
	} else {
		$sessiondata = unserialize(base64_decode($_SESSION['data']));
	}
	$public = '?public=true';
	$publica = '&public=true';
	$sessiondata['graphdisp'] = 1;
	$sessiondata['mathdisp'] = 2;
} else {
	require("../validate.php");
	$public = '';
	$publica = '';
}
require("../assessment/displayq2.php");

$pagetitle = "Quick Drill";

if (isset($sessiondata['drill']) && empty($_GET['id'])) {
	//load from sessiondata
	$qsetid = $sessiondata['drill']['id'];
	$cid = $sessiondata['drill']['cid'];
	$sa = $sessiondata['drill']['sa'];
	$starttime = $sessiondata['drill']['starttime'];
	$mode = $sessiondata['drill']['mode'];
	if ($mode == 'cntdown') {
		$timelimit = $sessiondata['drill']['time'];
	}
	if (isset($sessiondata['drill']['n'])) {
		$n = $sessiondata['drill']['n'];
	}
	if (isset($sessiondata['drill']['nc'])) {
		$nc = $sessiondata['drill']['nc'];
	}
	$scores = $sessiondata['drill']['scores'];
	
	$showscore = ($sa==0 || $sa==1 || $sa==4);
	if (($mode=='cntup' || $mode=='cntdown') && $starttime==0)  {
		$sessiondata['drill']['starttime'] = time();
		$starttime = time();
	}
	
} else {
	//first access - load into sessiondata and refresh
	if (empty($_GET['id']) || $_GET['id']=='new') {
		if ($myrights>10) {
			linkgenerator();
		} else {
			echo "Error: Need to supply question ID in URL";
		}
		exit;
	} else {
		$sessiondata['drill'] = array();
		$sessiondata['drill']['id'] = $_GET['id'];
	}
	if (!empty($_GET['cid'])) {
		$sessiondata['drill']['cid'] = $_GET['cid'];
	}  else {
		$sessiondata['drill']['cid'] = 0;
	}
	if (!empty($_GET['sa'])) {
		$sessiondata['drill']['sa'] = $_GET['sa'];
	} else {
		$sessiondata['drill']['sa'] = 0;
	}
	$sessiondata['drill']['mode'] = 'std';
	$sessiondata['drill']['scores'] = array();
		
	if (!empty($_GET['t'])) {
		$sessiondata['drill']['time'] = $_GET['t'];
		$sessiondata['drill']['mode'] = 'cntdown';
	} 
	if (!empty($_GET['n'])) {
		$sessiondata['drill']['n'] = $_GET['n'];
		$sessiondata['drill']['mode'] = 'cntup';
	}
	if (!empty($_GET['nc'])) {
		$sessiondata['drill']['nc'] = $_GET['nc'];
		$sessiondata['drill']['mode'] = 'cntup';
	}
	if ($sessiondata['drill']['mode']=='cntup' || $sessiondata['drill']['mode']=='cntdown') {
		$sessiondata['drill']['starttime'] = 0;
	}
	$sessiondata['coursetheme'] = $coursetheme;
	writesessiondata();
	
	if ($sessiondata['drill']['mode']=='cntup' || $sessiondata['drill']['mode']=='cntdown') {
		echo '<html><body>';
		echo "<a href=\"http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/quickdrill.php$public\">Start</a>";
		echo '</body></html>';
	} else {
		header("Location: http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/quickdrill.php$public");
	}
	exit;
}

$page_formAction = "quickdrill.php$public";

$showans = false;
if (isset($_POST['seed'])) {
	$score = scoreq(0,$qsetid,$_POST['seed'],$_POST['qn0']);
	$lastanswers[0] = stripslashes($lastanswers[0]);
	$page_scoreMsg =  printscore($score,$qsetid,$_POST['seed']);
	if (getpts($score)<1 && $sa==0) {
		$showans = true;
		$seed = $_POST['seed'];
	} else if (getpts($score)<1 && $sa==4) {
		$seed = $_POST['seed'];
		unset($lastanswers);
	} else {
		unset($lastanswers);
		$seed = rand(1,9999);
	}
	$scores[] = $score;
	$sessiondata['drill']['scores'] = $scores;
	writesessiondata();
	$curscore = 0;
	foreach ($scores as $score) {
		$curscore += getpts($score);
	}
} else {
	$page_scoreMsg = '';
	$curscore = 0;
	$seed = rand(1,9999);
}

//$sessiondata['coursetheme'] = $coursetheme;
$flexwidth = true; //tells header to use non _fw stylesheet
$placeinhead = '<style type="text/css">div.question {width: auto;} div.review {width: auto; margin-top: 5px;}</style>';
$useeditor = 1;
require("../assessment/header.php");
if ($cid!=0) {
	echo "<div class=breadcrumb>$breadcrumbbase <a href=\"course.php?cid=$cid\">$coursename</a> ";
	echo "&gt; Drill</div>";
}

$timesup = false;
if ($mode=='cntup' || $mode=='cntdown') {
	$now = time();
	if ($mode=='cntup') {
		$cur = $now - $starttime;
	} else if ($mode=='cntdown') {
		$cur = $timelimit - ($now - $starttime);
	}
	if ($mode=='cntdown' && ($cur <=0 || isset($_GET['superdone']))) {
		$timesup = true;	
	}
	if ($cur > 3600) {
		$hours = floor($cur/3600);
		$cur = $cur - 3600*$hours;
	} else { $hours = 0;}
	if ($cur > 60) {
		$minutes = floor($cur/60);
		$cur = $cur - 60*$minutes;
	} else {$minutes=0;}
	$seconds = $cur;
}

if (isset($n) && count($scores)==$n && !$showans) {  //if student has completed their n questions
	//print end-of-drill message for student
	//show time taken	
	echo "<p>$n questions completed in ";
	if ($hours>0) { echo "$hours hours ";}
	if ($minutes>0) { echo "$minutes minutes ";}
	echo "$seconds seconds</p>";
	echo "<p>Score:  $curscore out of ".count($scores)." possible</p>";
	$addr = "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/quickdrill.php?id=$qsetid&cid=$cid&sa=$sa&n=$n$publica";
	echo "<p><a href=\"$addr\">Again</a></p>";
	require("../footer.php");
	exit;
}

if (isset($nc) && $curscore==$nc) {  //if student has completed their nc questions correctly
	//print end-of-drill message for student
	//show time taken	
	echo "<p>$nc questions completed correctly in ";
	if ($hours>0) { echo "$hours hours ";}
	if ($minutes>0) { echo "$minutes minutes ";}
	echo "$seconds seconds</p>";
	
	echo "<p>".count($scores)." tries used</p>";
	$addr = "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/quickdrill.php?id=$qsetid&cid=$cid&sa=$sa&nc=$nc$publica";
	echo "<p><a href=\"$addr\">Again</a></p>";
	require("../footer.php");
	exit;
}
if ($timesup == true) { //if time has expired
	//print end-of-drill success message for student
	//show total q's correct
	$cur = $timelimit;
	if ($cur > 3600) {
		$hours = floor($cur/3600);
		$cur = $cur - 3600*$hours;
	} else { $hours = 0;}
	if ($cur > 60) {
		$minutes = floor($cur/60);
		$cur = $cur - 60*$minutes;
	} else {$minutes=0;}
	$seconds = $cur;
	echo "<p>Score:  $curscore out of ".count($scores)." possible</p>";
	echo "<p>In ";
	if ($hours>0) { echo "$hours hours ";}
	if ($minutes>0) { echo "$minutes minutes ";}
	echo "$seconds seconds</p>";
	$addr = "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/quickdrill.php?id=$qsetid&cid=$cid&sa=$sa&t=$timelimit$publica";
	echo "<p><a href=\"$addr\">Again</a></p>";
	require("../footer.php");
	exit;
}

if ($showscore) {
	echo '<div class="review">Current score: '.$curscore." out of ".count($scores);
	echo '</div>';
}
if ($mode=='cntup' || $mode=='cntdown') {
	echo "<script type=\"text/javascript\">\n";
	echo " hours = $hours; minutes = $minutes; seconds = $seconds; done=false;\n";	
	echo " function updatetime() {\n";
	if ($mode=='cntdown') {
		echo "	  seconds--;\n";
	} else if ($mode=='cntup') {
		echo "	  seconds++;\n";
	}
	echo "    if (seconds==0 && minutes==0 && hours==0) {done=true; ";
	echo "		var theform = document.getElementById(\"qform\");";
	echo "		var action = theform.getAttribute(\"action\");";
	echo "		theform.setAttribute(\"action\",action+'&superdone=true');";
	echo "		if (doonsubmit(theform,true,true)) { theform.submit(); } \n"; 
	//setTimeout('document.getElementById(\"qform\").submit()',1000);} \n";
	echo "		return 0;";
	echo "    }";
	echo "    if (seconds < 0) { seconds=59; minutes--; }\n";
	echo "    if (minutes < 0) { minutes=59; hours--;}\n";
	echo "    if (seconds > 59) { seconds=0; minutes++; }\n";
	echo "    if (minutes > 59) { minutes=0; hours++;}\n";
	echo "	  str = '';\n";
	echo "	  if (hours > 0) { str += hours + ':';}\n";
	echo "    if (hours > 0 && minutes <10) { str += '0';}\n";
	echo "	  if (minutes >0) {str += minutes + ':';}\n";
	echo "	    else if (hours>0) {str += '0:';}\n";
	echo "      else {str += ':';}\n";
	echo "    if (seconds<10) { str += '0';}\n";
	echo "	  str += seconds + '';\n";
	echo "	  document.getElementById('timer').innerHTML = str;\n";
	echo "    if (!done) {setTimeout(\"updatetime()\",1000);}\n";
	echo " }\n";
	//echo " //updatetime();\n";
	echo " initstack.push(updatetime);";
	echo "</script>\n";
	echo "<div class=right id=timelimitholder>Time: <span id=\"timer\" style=\"font-size: 120%; color: red;\" ";
	echo ">$hours:$minutes:$seconds</span></div>\n";
}
?>
<script type="text/javascript">
function focusfirst() {
   var el = document.getElementById("qn0");
   if (el != null) {el.focus();}
}
initstack.push(focusfirst);
</script>


<?php

if ($page_scoreMsg != '' && $showscore) {
	echo '<div class="review">Score on last question: '.$page_scoreMsg;
	echo '</div>';
}

if ($showans) {
	echo "<form id=\"qform\" method=\"post\" enctype=\"multipart/form-data\" action=\"$page_formAction\" onsubmit=\"doonsubmit()\">\n";
	echo "<p>Displaying last question with solution <input type=submit name=\"next\" value=\"New Question\"/></p>\n";
	displayq(0,$qsetid,$seed,2,true,0);
	echo "</form>\n";
} else {
	if ($sa==3) {
		$doshowans = 1;
	} else {
		$doshowans = 0;
	}
	echo "<form id=\"qform\" method=\"post\" enctype=\"multipart/form-data\" action=\"$page_formAction\" onsubmit=\"doonsubmit()\">\n";
	echo "<input type=\"hidden\" name=\"seed\" value=\"$seed\" />";
	displayq(0,$qsetid,$seed,$doshowans,true,0);
	if ($sa==3) {
		echo "<input type=submit name=\"next\" value=\"Next Question\">\n";
	} else {
		echo "<input type=submit name=\"check\" value=\"Check Answer\">\n";
	}
	echo "</form>\n";
}

require("../footer.php");


function getansweights($code,$seed) {
	$foundweights = false;
	if (($p = strpos($code,'answeights'))!==false) {
		$p = strpos($code,"\n",$p);
		$weights = sandboxgetweights($code,$seed);
		if (is_array($weights)) {
			return $weights;
		}
		
	} 
	if (!$foundweights) {
		preg_match('/anstypes\s*=(.*)/',$line['control'],$match);
		$n = substr_count($match[1],',')+1;
		if ($n>1) {
			$weights = array_fill(0,$n-1,round(1/$n,3));
			$weights[] = 1-array_sum($line['answeights']);
			return $weights;
		} else {
			return array(1);
		}
	}
}

function sandboxgetweights($code,$seed) {
	srand($seed);
	eval(interpret('control','multipart',$code));
	if (!isset($answeights)) {
		return false;
	} else if (is_array($answeights)) {
		return $answeights;
	} else {
		return explode(',',$answeights);
	}
}

function printscore($sc,$qsetid,$seed) {
	$poss = 1;
	if (strpos($sc,'~')===false) {
		$sc = str_replace('-1','N/A',$sc);
		$out =  "$sc out of $poss";
		$pts = $sc;
		if (!is_numeric($pts)) { $pts = 0;}
	} else {
		$query = "SELECT control FROM imas_questionset WHERE id='$qsetid'";
		$result = mysql_query($query) or die("Query failed: $query: " . mysql_error());
		$control = mysql_result($result,0,0);
		$ptposs = getansweights($control,$seed);
		
		for ($i=0; $i<count($ptposs)-1; $i++) {
			$ptposs[$i] = round($ptposs[$i]*$poss,2);
		}
		//adjust for rounding
		$diff = $poss - array_sum($ptposs);
		$ptposs[count($ptposs)-1] += $diff;
		
		
		$pts = getpts($sc);
		$sc = str_replace('-1','N/A',$sc);
		//$sc = str_replace('~',', ',$sc);
		$scarr = explode('~',$sc);
		foreach ($scarr as $k=>$v) {
			if ($ptposs[$k]==0) {
				$pm = 'gchk';
			} else if (!is_numeric($v) || $v==0) { 
				$pm = 'redx';
			} else if (abs($v-$ptposs[$k])<.011) {
				$pm = 'gchk';
			} else {
				$pm = 'ychk';
			}
			$bar = "<img src=\"$imasroot/img/$pm.gif\" />";
			$scarr[$k] = "$bar $v/{$ptposs[$k]}";
		}
		$sc = implode(', ',$scarr);
		//$ptposs = implode(', ',$ptposs); 
		$out =  "$pts out of $poss (parts: $sc)";
	}	
	$bar = '<span class="scorebarholder">';
	if ($poss==0) {
		$w = 30;
	} else {
		$w = round(30*$pts/$poss);
	}
	if ($w==0) {$w=1;}
	if ($w < 15) { 
	     $color = "#f".dechex(floor(16*($w)/15))."0";
	} else if ($w==15) {
	     $color = '#ff0';
	} else { 
	     $color = "#". dechex(floor(16*(2-$w/15))) . "f0";
	}
	
	$bar .= '<span class="scorebarinner" style="background-color:'.$color.';width:'.$w.'px;">&nbsp;</span></span> ';
	return $bar . $out;	
}

function getpts($sc) {
	if (strpos($sc,'~')===false) {
		return $sc;
	} else {
		$sc = explode('~',$sc);
		$tot = 0;
		foreach ($sc as $s) {
			if ($s>0) { 
				$tot+=$s;
			}
		}
		return round($tot,1);
	}
}

function linkgenerator() {
	$addr = "http://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/quickdrill.php";
	?>
<html>
<head>
 <title>Quick Drill Link Generator</title>
 <script type="text/javascript">
 var baseaddr = "<?php echo $addr;?>";
 function makelink() {
	 id = document.getElementById("qid").value;
	 if (id=='') {alert("Question ID is required"); return false;}
	 cid = document.getElementById("cid").value;
	 sa = document.getElementById("sa").value;
	 mode = document.getElementById("type").value;
	 val = document.getElementById("val").value;
	 if (mode!='none' && val=='') { alert("need to specify N"); return false;}
	 var url = baseaddr + '?id=' + id + '&sa='+sa;
	 if (cid != '') {
		url += '&cid='+cid;	 
	 }
	 if (mode != 'none') {
		 url += '&'+mode+'='+val;
	 }
	 document.getElementById("output").innerHTML = "<p>URL to use: "+url+"</p><p><a href=\""+url+"\" target=\"_blank\">Try it</a></p>"; 
 }
 </script>
 </head>
 <body>
 <h2>Quick Drill Link Generator</h2>
 <table border=0>
 <tr><td>Question ID to use:</td><td><input type="text" size="5" id="qid" /></td></tr>
 <tr><td>Course ID (optional):</td><td><input type="text" size="5" id="cid" /></td></tr>
 <tr><td>Show answer option:</td><td><select id="sa">
 	<option value="0">Show score - reshow question with answer if wrong</option>
	<option value="1">Show score - don't reshow question w answer if wrong</option>
	<option value="4">Show score - don't show answer - make student redo same version if missed</option>
	<option value="2">Don't show score at all</option>
	<option value="3">Flash Cards Style: don't show score, but use Show Answer button</option>
	</select></td></tr>
 <tr><td>Behavior:</td><td><select id="type">
 	<option value="none">Just keep asking questions forever</option>
	<option value="n">Do N questions, then stop</option>
	<option value="nc">Do until N questions are correct, then stop</option>
	<option value="t">Do as many questions as possible in N seconds</option>
	</select><br/>
	Where N = <input type="text" size="4" id="val"/></td></tr>
</table>

<input type="button" value="Generate Link" onclick="makelink()"/>

<div id="output"></div>
</body>
</html>
 

	<?php
	
}

?>