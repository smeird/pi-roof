
<script src="https://kit.fontawesome.com/55c3f37ab0.js" crossorigin="anonymous"></script>
<script src="https://code.highcharts.com/stock/highstock.js"></script>

<script src="https://code.highcharts.com/highcharts-more.js"></script>
<script src="https://code.highcharts.com/modules/boost.js"></script>
<script src="https://code.highcharts.com/modules/data.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
<script src="http://cdnjs.cloudflare.com/ajax/libs/paho-mqtt/1.0.1/mqttws31.js" type="text/javascript"></script>

<script type="text/javascript">
/*
by @bordignon on twitter
Feb 2014
Simple example of plotting live mqtt/websockets data using highcharts.
public broker and topic you can use for testing.
	var MQTTbroker = 'broker.mqttdashboard.com';
	var MQTTport = 8000;
	var MQTTsubTopic = 'dcsquare/cubes/#'; //works with wildcard # and + topics dynamically now
*/

//settings BEGIN
	var MQTTbroker = 'mqtt.smeird.com';
	var MQTTport = 8083;
	var MQTTsubTopic = 'Observatory/Graph/#'; //works with wildcard # and + topics dynamically now
//settings END
	var chart; // global variuable for chart
	var dataTopics = new Array();
//mqtt broker
	var clientA = new Paho.MQTT.Client(MQTTbroker, MQTTport, "myclientid_" + parseInt(Math.random() * 1000, 10));

	// Connect the client, with a Username and Password

	clientA.onMessageArrived = onMessageArrived;
	clientA.onConnectionLost = onConnectionLost;
	//connect to broker is at the bottom of the init() function !!!!

//mqtt connecton options including the mqtt broker subscriptions
	var options = {
		timeout: 3,
		useSSL: true,
		onSuccess: function () {
			console.log("Graph mqtt connected");
			// Connection succeeded; subscribe to our topics
			clientA.subscribe(MQTTsubTopic, {qos: 0});
		},
		onFailure: function (message) {
			console.log("Connection failed, ERROR: " + message.errorMessage);
			//window.setTimeout(location.reload(),20000); //wait 20seconds before trying to connect again.
		}
	};
//can be used to reconnect on connection lost
	function onConnectionLost(responseObject) {
		console.log("connection lost: " + responseObject.errorMessage);
		//window.setTimeout(location.reload(),20000); //wait 20seconds before trying to connect again.
	};
//what is done when a message arrives from the broker
	function onMessageArrived(message) {
		//console.log(message.destinationName, '',message.payloadString);
		//check if it is a new topic, if not add it to the array
		if (dataTopics.indexOf(message.destinationName) < 0){

		    dataTopics.push(message.destinationName); //add new topic to array
		    var y = dataTopics.indexOf(message.destinationName); //get the index no


				if (message.destinationName == "Observatory/Graph/clouds") {
 	       message.Real = "Clouds";
				 message.color = "red";
				 message.neg = "green";
				 message.threshold = -12;
				 message.axis = 0;
				 message.dash ='shortdot';
 	     }
 	     if (message.destinationName == "Observatory/Graph/light") {
 	       message.Real = "Light";
				 message.color = "green";
				 message.neg = "red";
				 message.threshold =10000;
				 message.axis = 1;
				 message.dash ='shortdash';
 	     }
			 if (message.destinationName == "Observatory/Graph/rain") {
 	       message.Real = "rain";
				 message.color = "green";
				 message.neg = "red";
				 message.threshold =4200;
				 message.axis = 2;
				 message.dash ='shortdashdot';
 	     }
			 if (message.destinationName == "Observatory/Graph/hum") {
 	       message.Real = "Humidty";
				 message.color = "red";
				 message.neg = "green";
				 message.threshold =95;
				 message.axis = 3;
				 message.dash ='longdash';
 	     }
			 if (message.destinationName == "Observatory/Graph/sqm") {
				 message.Real = "Darkness";
				 message.color = "green";
				 message.neg = "red";
				 message.threshold =15;
				 message.axis = 4;
				 message.dash ='longdash';
			 }
		    //create new data series for the chart
			var newseries = {
		            id: y,
		            name: message.Real,
								color: message.color,
								yAxis: message.axis,
								negativeColor: message.neg,
								threshold: message.threshold,
								dashStyle: message.dash,
		            data: []
		            };
			chart.addSeries(newseries); //add the series

		    };

		var y = dataTopics.indexOf(message.destinationName); //get the index no of the topic from the array
		var myEpoch = new Date().getTime(); //get current epoch time
		var thenum = message.payloadString; //remove any text spaces from the message
		//console.log('=============='+thenum)
		var plotMqtt = [myEpoch, Number(thenum)]; //create the array
		if (isNumber(thenum)) { //check if it is a real number and not text
			//console.log('is a propper number, will send to chart.')
			plot(plotMqtt, y);	//send it to the plot function
		};
	};
//check if a real number
	function isNumber(n) {
	  return !isNaN(parseFloat(n)) && isFinite(n);

	};
//function that is called once the document has loaded
	function init() {
		//i find i have to set this to false if i have trouble with timezones.
		Highcharts.setOptions({
			global: {
				useUTC: true
			}
		});
		// Connect to MQTT broker
		clientA.connect(options);
	};
//this adds the plots to the chart
    function plot(point, chartno) {
    	//console.log(point);

	        var series = chart.series[0],
	            shift = series.data.length > 20; // shift if the series is
	                                             // longer than 20
	        // add the point
	        chart.series[chartno].addPoint(point, true, shift);
	};
//settings for the chart
	$(document).ready(function() {
	    chart = new Highcharts.Chart({
	        chart: {
	            renderTo: 'containery',
	            defaultSeriesType: 'spline'
	        },
	        title: {
	            text: 'When all lines are green its safe to open Observatory'
	        },
	        xAxis: {
	            type: 'datetime',
	            tickPixelInterval: 150,
	            maxZoom: 20 * 1000
	        },
					plotOptions: {
							series: {
								//color: Highcharts.getOptions().colors[2],

									marker: {
											radius: 10,
											enabled: false
									},
									dataLabels: {
                enabled: true
            },
							}
					},
	        yAxis: [{
	            minPadding: 0.1,
	            maxPadding: 0.1,
	            title: {
	                text: 'Clouds',
	                margin: -10
	            }
	        },{
	            minPadding: 0.1,
	            maxPadding: 0.1,
							opposite: false,
	            title: {
	                text: 'Light',
	                margin: -10
	            }
	        },{
	            minPadding: 0.1,
	            maxPadding: 0.1,
							opposite: true,
	            title: {
	                text: 'Rain',
	                margin: -10
	            }
	        },{
	            minPadding: 0.1,
	            maxPadding: 0.1,
							opposite: true,
	            title: {
	                text: 'Humidity',
	                margin: -10
	            }
	        },{
	            minPadding: 0.1,
	            maxPadding: 0.1,
							opposite: true,
	            title: {
	                text: 'Darkness',
	                margin: -10
	            }
	        }],



	        series: []
	    });
	});
</script>


</head>
<body>
<body onload="init();"><!--Start the javascript ball rolling and connect to the mqtt broker-->



<div id="containery" style="height: 800px;" ></div><!-- this the placeholder for the chart-->

	</body>
</html>
