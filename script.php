<?php
$vstatus="OK";
$i=$_POST["REQUEST"];
if ($i=="open"){
echo "\nRunning Open Script\n";
$vstatus = shell_exec ("sudo /usr/local/scripts/open2.py");
}
if ($i=="close"){
echo "\nRunning Close Script\n";  
$vstatus =  shell_exec ("sudo /usr/local/scripts/close2.py");
}
if ($i=="abort"){

$vstatus = shell_exec ("sudo killall close2.py");
echo "\nStopping Close Script\n";
$vstatus = shell_exec ("sudo killall open2.py");
echo "Stopping Open Script\n";
$vstatus = shell_exec ("sudo /usr/local/scripts/abort.py");
echo "Full Abort\n";
}
echo $vstatus;

?>
