<?php
$i=$_POST["GPIOPORT"];



if (isset($i)){

if ($i==23){
exec ( "gpio -g read 7", $vstatusa );
if ($vstatusa[0]==0) {
system ( "gpio -g mode 7 out" );
system ( "gpio -g write 7 1" );
} else {
system ( "gpio -g mode 7 out" );
system ( "gpio -g write 7 0" );
}
}

if ($i==18){
exec ( "gpio -g read 12", $vstatusb );
if ($vstatusb[0]==0) {
system ( "gpio -g mode 12 out" );
system ( "gpio -g write 12 1" );
} else {
system ( "gpio -g mode 12 out" );
system ( "gpio -g write 12 0" );
}
}

if ($i==17){
exec ( "gpio -g read 13", $vstatusc );
if ($vstatusc[0]==0) {
system ( "gpio -g mode 13 out" );
system ( "gpio -g write 13 1" );
} else {
system ( "gpio -g mode 13 out" );
system ( "gpio -g write 13 0" );
}
}



exec ( "gpio -g read $i", $vstatus );
if ($vstatus[0]==0) {
system ( "gpio -g mode ".$i." out" );
system ( "gpio -g write ".$i." 1" );
} else {
system ( "gpio -g mode ".$i." out" );
system ( "gpio -g write ".$i." 0" );
}
}



?>
