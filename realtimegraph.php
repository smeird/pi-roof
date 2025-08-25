<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Observatory Real-Time Graph</title>
  <script src="https://kit.fontawesome.com/55c3f37ab0.js" crossorigin="anonymous"></script>
  <script src="https://code.highcharts.com/stock/highstock.js"></script>
  <script src="https://code.highcharts.com/highcharts-more.js"></script>
  <script src="https://code.highcharts.com/modules/boost.js"></script>
  <script src="https://code.highcharts.com/modules/data.js"></script>
  <script src="https://code.highcharts.com/modules/exporting.js"></script>
  <script src="https://code.highcharts.com/modules/solid-gauge.js"></script>
  <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
  <script src="js/mqttClient.js"></script>
</head>
<body>
  <div id="containery" style="height: 800px;"></div>
  <script type="module">
import { brokerUrl, port, graphTopic } from './js/mqttConfig.js';
//settings BEGIN
const MQTTbroker = `${brokerUrl}:${port}`;
const MQTTsubTopic = graphTopic; //works with wildcard # and + topics dynamically now
//settings END
var chart; // global variable for chart
var dataTopics = [];

const mqttClient = createClient({ brokerUrl: MQTTbroker });

mqttClient.on('connect', function () {
  console.log("Graph mqtt connected");
  mqttClient.subscribe(MQTTsubTopic);
});

mqttClient.on('error', function (err) {
  console.log("Connection failed, ERROR: " + err.message);
});

mqttClient.on('message', function (topic, payload) {
  const message = { destinationName: topic, payloadString: payload.toString() };
  onMessageArrived(message);
});

//what is done when a message arrives from the broker
function onMessageArrived(message) {
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
  }

  var y = dataTopics.indexOf(message.destinationName); //get the index no of the topic from the array
  var myEpoch = new Date().getTime(); //get current epoch time
  var thenum = message.payloadString; //remove any text spaces from the message
  var plotMqtt = [myEpoch, Number(thenum)]; //create the array
  if (isNumber(thenum)) { //check if it is a real number and not text
    plot(plotMqtt, y);      //send it to the plot function
  }
}
//check if a real number
function isNumber(n) {
  return !isNaN(parseFloat(n)) && isFinite(n);
}
//function that sets up Highcharts and connects to MQTT
function init() {
  //i find i have to set this to false if i have trouble with timezones.
  Highcharts.setOptions({
    global: {
      useUTC: true
    }
  });
}
//this adds the plots to the chart
function plot(point, chartno) {
  var series = chart.series[0],
      shift = series.data.length > 20; // shift if the series is longer than 20
  chart.series[chartno].addPoint(point, true, shift);
}
//settings for the chart
function buildChart() {
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
        marker: {
          radius: 10,
          enabled: false
        },
        dataLabels: {
          enabled: true
        }
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
}

document.addEventListener('DOMContentLoaded', function() {
  buildChart();
  init();
});
  </script>
</body>
</html>
