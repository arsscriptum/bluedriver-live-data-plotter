<?php

function convertDisplayName($displayName) {
    // Replace spaces with underscores
    $newName = str_replace(' ', '_', $displayName);
    
    // Remove non-alphanumeric characters except underscore
    $newName = preg_replace('/[^a-zA-Z0-9_]/', '', $newName);
    
    // Replace multiple underscores with a single one
    $newName = preg_replace('/__+/', '_', $newName);
    
    // Convert to lowercase and trim underscores from start/end
    $newName = trim(strtolower($newName), '_');

    return $newName;
}



$statsList = json_decode(file_get_contents("stats_list.json"), true);

//foreach ($statsList as $stat) {
//    $test = $stat['Name'];
//    echo "$test<br>";
//}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BlueDriver CSV Plotter: version1 beta test pour Mickael</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js"></script>
  <link rel="stylesheet" media="print" href="https://assets8.freshdesk.com/assets/cdn/portal_print-6e04b27f27ab27faab81f917d275d593fa892ce13150854024baaf983b3f4326.css"/>
  <link rel="stylesheet" media="screen" href="https://assets10.freshdesk.com/assets/cdn/falcon_portal_utils-cdd6e3a8a0ccf60cbbc7c27271252bffae829db1973666f4544d2135b3e34468.css"/>
  <link href="theme.css?v=4&amp;d=1682098013" media="screen" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="theme.css">
  <style>
    body { font-family: Arial; margin: 2em; }
    .checkbox-section { margin-top: 2em; }
    canvas { margin-top: 1em; max-width: 100%; }
    button { margin: 0.5em; }
  </style>
</head>
<body>

<h1 style="text-align: center;">BlueDriver CSV Plotter: version1 beta test pour Mickael</h1>
<input type="file" id="csvFile" accept=".csv">

<div class="checkbox-section">
  <h3>Combined Graph Controls</h3>
  <div id="checkboxes-main"></div>
  <button onclick="selectAll('main')">Select All</button>
  <button onclick="unselectAll('main')">Unselect All</button>
  <button onclick="updateChart()">Update Graph</button>
  <button onclick="saveChart(chart, 'combined-graph')">Save Graph</button>
  <canvas id="chart"></canvas>
</div>

<div class="checkbox-section">
  <h3>Individual Graph Controls</h3>
  <div id="checkboxes-individual"></div>
  <button onclick="selectAll('individual')">Select All</button>
  <button onclick="unselectAll('individual')">Unselect All</button>
  <button onclick="updateIndividualCharts()">Plot Individual Graphs</button>
  <div id="individual-charts"></div>
</div>


<script>
function convertDisplayName(displayName) {
  let newName = displayName.replace(/\s+/g, '_');
  newName = newName.replace(/[^a-zA-Z0-9_]/g, '');
  newName = newName.replace(/__+/g, '_');
  newName = newName.toLowerCase().replace(/^_+|_+$/g, '').trim();
  return newName;
}
function sanitizeStatId(str) {
  if (str.toLowerCase().includes('oxygen_sensor')) {
    return 'oxygen_sensor_voltage';
  }

  return str
    .replace(/_(1|2|a|b|c|d|e|f|g)$/i, '')            // Remove trailing _1, _2, _A, _D
    .replace(/_kpm$/i, '')                  // Remove trailing _kpm
    .replace(/_kpa$/i, '')                  // Remove trailing _kpa
    .replace(/_kmh$/i, '')                  // Remove trailing _kmh
    .replace(/_bank$/i, '')                  // Remove trailing _bank
    .replace(/_trim$/i, '')                  // Remove trailing _trim
    .trim();
}
// Pass PHP array to JavaScript
const statsList = <?php echo json_encode($statsList, JSON_UNESCAPED_SLASHES); ?>;

// Create a map from converted name to HrefId
const nameToHrefMap = {};
statsList.forEach(stat => {
  const newname = sanitizeStatId(convertDisplayName(stat.Name));

  nameToHrefMap[newname] = stat.HrefId;
  console.log(`HrefId for ${newname}: ${stat.HrefId}`);
});


</script>



<script>
let csvData = [], labels = [], chart;

function selectAll(section) {
  document.querySelectorAll(`#checkboxes-${section} input[type=checkbox]`).forEach(cb => cb.checked = true);
}

function unselectAll(section) {
  document.querySelectorAll(`#checkboxes-${section} input[type=checkbox]`).forEach(cb => cb.checked = false);
}

document.getElementById('csvFile').addEventListener('change', function (e) {
  const file = e.target.files[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('csvFile', file);

  fetch('upload_csv.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(parsed => {
    if (parsed.headers && parsed.data) {
      csvData = parsed.data;
      labels = parsed.data.map(row => parseFloat(row["Time (s)"]));

      generateCheckboxes(parsed.headers);
    } else {
      console.error("Invalid response:", parsed);
    }
  })
  .catch(error => console.error("Upload failed:", error));
});

function getHrefIdByPartialName(nameToHrefMap, lookupName) {
  for (const key in nameToHrefMap) {
    if (key.includes(lookupName)) {
      return nameToHrefMap[key];
    }
  }
  return null; // Or return a fallback value if not found
}


function generateCheckboxes(headers) {
  document.getElementById('checkboxes-main').innerHTML = '';
  document.getElementById('checkboxes-individual').innerHTML = '';
  headers.forEach(header => {
    if (header !== "Time (s)") {
      const id = header.toLowerCase().replace(/[^a-z0-9]/g, '-');
      const lookupName = sanitizeStatId(convertDisplayName(header));

      const hrefId = getHrefIdByPartialName(nameToHrefMap, lookupName)
      console.log(`generateCheckboxes HrefId for ${lookupName}: ${hrefId}`);

      ["main", "individual"].forEach(section => {
        const label = document.createElement('label');
        label.innerHTML = `<input type="checkbox" value="${header}" onchange="${section === 'main' ? 'updateChart()' : ''}"> <a href="#${hrefId}" target="_self">${lookupName}</a><br>`;
        document.getElementById(`checkboxes-${section}`).appendChild(label);
      });
    }
  });
}

function updateChart() {
  const checked = Array.from(document.querySelectorAll('#checkboxes-main input:checked')).map(cb => cb.value);
  const datasets = checked.map(label => ({
    label,
    data: csvData.map(row => parseFloat(row[label]?.replace(",", "."))),
    borderWidth: 1,
    fill: false,
    borderColor: randomColor()
  }));

  if (chart) chart.destroy();
  chart = new Chart(document.getElementById('chart'), {
    type: 'line',
    data: { labels, datasets },
    options: {
      responsive: true,
      scales: {
        x: { title: { display: true, text: "Time (s)" }},
        y: { title: { display: true, text: "Value" }}
      }
    }
  });
}

function updateIndividualCharts() {
  const container = document.getElementById('individual-charts');
  container.innerHTML = '';
  const checked = Array.from(document.querySelectorAll('#checkboxes-individual input:checked')).map(cb => cb.value);
  checked.forEach(label => {
    const canvas = document.createElement('canvas');
    container.appendChild(canvas);
    new Chart(canvas, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label,
          data: csvData.map(row => parseFloat(row[label]?.replace(",", "."))),
          borderWidth: 1,
          fill: false,
          borderColor: randomColor()
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: { title: { display: true, text: "Time (s)" }},
          y: { title: { display: true, text: label } }
        }
      }
    });
  });
}

function saveChart(chartInstance, name) {
  const link = document.createElement('a');
  link.href = chartInstance.toBase64Image();
  link.download = name + '.png';
  link.click();
}


function randomColor() {
  return 'hsl(' + Math.floor(Math.random() * 360) + ', 100%, 50%)';
}
</script>

<div data-identifyelement="453" style="text-align: center;"><br/><span style="color: rgb(44, 130, 201);"><a href="#cat-vehicle-operation">Vehicle Operation Parameters</a><br/><a href="#cat-air-fuel">Fuel &amp; Air Data</a><br/><a href="#cat-emissions-control">Emissions Control Equipment Information</a>  </span><br/>
    <table class="bd-table" id="cat-vehicle-operation">
        <thead>
            <tr>
                <th scope="col">Datapoint</th>
                <th scope="col">Description</th>
            </tr>
        </thead>
        <thead>
            <tr>
                <th colspan="2">Vehicle Operation</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td id="pid-engine-rpm">Engine RPM</td>
                <td id="pid-engine-rpm">Engine RPM</td>
            </tr>
            <tr>
                <td id="pid-vehicle-speed">Vehicle Speed</td>
                <td id="pid-vehicle-speed">Vehicle speed</td>
            </tr>
            <tr>
                <td id="pid-coolant-temp">Engine Coolant Temperature</td>
                <td>Coolant temperature - usually measured at the cylinder head or before the radiator.<br/><br/>Some vehicles may report a second coolant temperature sensor (ECT 2) - location may vary (for example it may be at the outlet of the thermostat) - the factory manual or a parts diagram for your vehicle should provide more information</td>
            </tr>
            <tr>
                <td id="pid-engine-oil-temperature">Engine Oil Temperature</td>
                <td id="pid-temperature-of-the-engine-oil---sensor-may-be-situated-near-the-oil-filter-but-this-location-will-vary-depending-on-the-vehicle">Temperature of the engine oil - sensor may be situated near the oil filter but this location will vary depending on the vehicle</td>
            </tr>
            <tr>
                <td id="pid-ambient-air-temperature">Ambient Air Temperature</td>
                <td id="pid-air-temperature-around-the-vehicle---typically-this-will-be-a-few-degrees-below-intake-temperature">Air temperature around the vehicle - typically this will be a few degrees below intake temperature</td>
            </tr>
            <tr>
                <td id="pid-ambient-pressure">Barometric Pressure</td>
                <td>Local ambient or atmoshperic pressure around the vehicle displayed as an <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">absolute</a> </span>value<br/><br/>Typically ambient pressure will read roughly 101.3 kPa or 14.7 psi, but this will vary depending on your altitude and local conditions</td>
            </tr>
            <tr>
                <td id="pid-accelerator-position">Accelerator Pedal Position</td>
                <td>Position of the driver's accelerator pedal - there may be up to three sensors:
                    <ol>
                        <li>Acceledator pedal position D (Sensor #1)</li>
                        <li>Acceledator pedal position E (Sensor #2)</li>
                        <li>Acceledator pedal position F (Sensor #3)</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-relative-accelerator-pedal-position">Relative Accelerator Pedal Position</td>
                <td>Accelerator pedal position adjusted for the learned behavior of the vehicle over time.<br/><br/>Due to scaling, the vehicle may not always report 100% when the pedal is placed to the floor.<br/><br/>Depending on the vehicle this value may also be the average of multiple position sensors (D, E, F)</td>
            </tr>
            <tr>
                <td id="pid-commanded-throttle-actuator">Commanded Throttle Actuator</td>
                <td>The<span style="color: rgb(44, 130, 201);"> <a href="#pid-abs-throttle">throttle position</a></span> requested by the ECM based on <span style="color: rgb(44, 130, 201);"><a href="#pid-accelerator-position">accelerator pedal position</a></span></td>
            </tr>
            <tr>
                <td id="pid-relative-throttle-position">Relative Throttle Position</td>
                <td>Throttle position relative to the "learned" or "adapted" closed position<br/><br/>Over time throttle behavior can change due to carbon buildup or other factors, some vehicles will monitor this behavior and make adjustments over time to compensate<br/><br/>For example: Over time carbon builds up in the throttle body and when "fully" closed, the throttle is actually open 5% - in this case the <span style="color: rgb(44, 130, 201);"><a href="#pid-abs-throttle">absolute throttle positon</a></span> will read 5% while the relative position will read 0%</td>
            </tr>
            <tr>
                <td id="pid-abs-throttle">Absolute Throttle Position</td>
                <td>How 'open' the throttle is - a value of 0% means completely closed while 100% is fully open<br/><br/>Depending on the vehicle there may be up to four throttle position sensors:
                    <ol>
                        <li>TPS A/1 (Labeled "Throttle Position Sensor")</li>
                        <li>TPS B/2</li>
                        <li>TPS C/3</li>
                        <li>TPS D/4</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-control-module-voltage">Control Module Voltage</td>
                <td>Input voltage at the Engine Control Module
                    <ul>
                        <li>Engine off/ignition on this value will show battery voltage</li>
                        <li>engine on it will show alternator voltage</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-hybrid-battery-pack-remaining-life">Hybrid Battery Pack Remaining Life</td>
                <td>AKA <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/State_of_charge">State of Charge</a></span><br/><br/>The total charge percent remaining in the hybrid battery pack (individual cell data is not available through standard OBDII data)</td>
            </tr>
            <tr>
                <td id="pid-hybrid-ev-vehicle-system-status">Hybrid/EV Vehicle System Status</td>
                <td>This parameter will report the following (as supported by the vehicle):
                    <ol>
                        <li>Hybrid/EV charging state: Either <strong>Charge Sustaining Mode</strong> (CSM - control system is attempting to maintain a constant State Of Charge) or <strong>Charge Depletion Mode</strong> (<span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Charge-depleting">CDM</a></span> - control system is targeting an SOC lower than the current value)<br/>Non-PHEVs will always display Charge Sustaining Mode</li>
                        <li>Hybrid/EV Battery Voltage: 0 to 1024V</li>
                        <li>Hybrid/EV Battery Current: -3300 to 3300 Amps, a negative value indicates that the battery is being charged</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-calculated-engine-load-value">Calculated Engine Load Value</td>
                <td>A calculated value representing the current percentage of <span style="color: rgb(44, 130, 201);"><a href="#pid-ref-torque">maximum available engine torque</a></span> being produced (100% at WOT, 0% at key on engine off)</td>
            </tr>
            <tr>
                <td id="pid-absolute-load-value">Absolute Load Value</td>
                <td>A normalized value representing the air mass intake per intake stroke as a percentage<br/><br/>Calculation: <em>(mass of air in grams per intake stroke) / (mass of air per intake stroke at 100% throttle assuming <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Standard_conditions_for_temperature_and_pressure">standard temperature and pressure</a></span>)</em><br/><br/><strong>Note:</strong> This datapoint has a reporting range from 0% to 25,700% but naturally aspirated engines will display roughly 0 to 95% while turbo/supercharged motors may show as high as 400%.</td>
            </tr>
            <tr>
                <td id="pid-driver-s-demand-engine---percent-torque">Driver's Demand Engine - Percent Torque</td>
                <td>The percentage of <span style="color: rgb(44, 130, 201);"><a href="#pid-ref-torque">maximum available engine torque</a> </span>requested by the ECM based on:
                    <ol>
                        <li>Accelerator pedal position</li>
                        <li>Cruise control</li>
                        <li>Transmission</li>
                    </ol>External factors such as traction control, abs, etc will not influenece this value</td>
            </tr>
            <tr>
                <td id="pid-actual-torque">Actual Engine - Percent Torque</td>
                <td>Also referred to as Indicated Torque<br/><br/>This parameter displays the current percentage of total available engine torque and includes the net brake torque produced as well as the <span style="color: rgb(44, 130, 201);"><a href="#pid-friction-torque">'friction' torque</a></span> required to run the engine at no load.</td>
            </tr>
            <tr>
                <td id="pid-engine-friction---percent-torque">Engine Friction - Percent Torque</td>
                <td id="pid-friction-torque">The percent of maximum engine torque required to run a 'fully equipped' engine at no load, this includes:
                    <ul>
                        <li>Internal engine components (crank, pistons, cams, valves, etc)</li>
                        <li>Fuel, oil</li>
                        <li>Water pump</li>
                        <li>Air intake</li>
                        <li>Exhaust</li>
                        <li>Alternator</li>
                        <li>Emissions control equipment</li>
                    </ul>This value does not account for:
                    <ul>
                        <li>Power steering</li>
                        <li>Vacuum pumps</li>
                        <li>AC Compressors</li>
                        <li>Braking systems</li>
                        <li>Acitve suspension systems</li>
                        <li>etc</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-ref-torque">Engine Reference Torque</td>
                <td>The torque rating of the engine - this is considered to be the 100% value for datapoints such as "Actual Engine Percent Torque" or other parameters that express torque output as a percentage.<br/><br/><strong>Note:</strong> This value is set in the factory and does not reflect changes over time due to wear/aging, aftermarket upgrades/tunes, etc</td>
            </tr>
            <tr>
                <td id="pid-engine-percent-torque-data">Engine Percent Torque Data</td>
                <td>This parameter is used in cases where changes in vehicle/environmental conditions can cause the <span style="color: rgb(44, 130, 201);"><a href="#pid-ref-torque">reference torque</a></span> to change - for example at high altitude a different fuel mapping may be employed which will decrease the total available torque by 80%.<br/><br/>Up to five different maximum torque ratings may be specified with this datapoint, each rating is numbered 1 through 5.<br/><br/>The datapoint does not report the reason for the change in maximum rating - a factory manual may be required to determine conditions related to each mapping.</td>
            </tr>
            <tr>
                <td id="pid-auxiliary-input-output">Auxiliary Input/Output</td>
                <td>This is a composite datapoint that is capable of reporting (if supported by the vehicle):
                    <ol>
                        <li>Power Take Off Status: <em>On</em> or <em>Off</em></li>
                        <li>Automatic Transmission Status: <em>Park/Netural</em> or <em>Drive/Reverse</em></li>
                        <li>Manual Transmission Neutral Status: <em>Neutral/Clutch</em> In or <em>In Gear</em></li>
                        <li>Glow Plug Lamp Status: Indicator <em>On</em> or <em>Off</em></li>
                        <li>Recommended Transmission Gear: <em>1</em> through <em>15</em></li>
                    </ol><strong>Note:</strong> Support for this datapoint is relatively rare, most vehicle report transmission status through non-standard enhanced live data</td>
            </tr>
            <tr>
                <td id="pid-exhaust-gas-temperature--egt-">Exhaust Gas Temperature (EGT)</td>
                <td>Depending on the vehicle the followng parameters may be reported for each exhaust bank:
                    <ol>
                        <li>Sensor #1 - Post-turbo</li>
                        <li>Sensor #2 - Post-cat</li>
                        <li>Sensor #3 - Post-DPF</li>
                        <li>Sensor #4 - No standard location specified, possibly after NOx control equipment</li>
                    </ol><strong>Note:</strong> the above are based on a generic sample vehicle and may not apply to your specific configuration, for exact measurement points refer to the vehicle's factory manual</td>
            </tr>
            <tr>
                <td id="pid-engine-exhaust-flow-rate">Engine Exhaust Flow Rate</td>
                <td id="pid-exhaust-flow-rate-in-kg-hr-or-lbs-hr-measured-upstream-of-the-aftertreatment-system--averaged-over-the-last-1000ms">Exhaust flow rate in kg/hr or lbs/hr measured upstream of the aftertreatment system, averaged over the last 1000ms</td>
            </tr>
            <tr>
                <td id="pid-exhaust-pressure">Exhaust Pressure</td>
                <td>Exhaust pressure, displayed as an absolute pressure value - engine off this paramater should display roughly ambient atmospheric values.<br/><br/>Depending on vehicle configuration this paramater may report data from one or two exhaust banks. For sensor/measurement location refer to your factory manual.</td>
            </tr>
            <tr>
                <td id="pid-manifold-surface-temperature">Manifold Surface Temperature</td>
                <td id="pid-temperature-at-the-outer-surface-of-the-exhaust-manifold">Temperature at the outer surface of the exhaust manifold</td>
            </tr>
            <tr>
                <td id="pid-timing-advance-for--1-cylinder">Timing Advance for #1 cylinder</td>
                <td dir="ltr">The angle (in degrees) of crankshaft rotation before top dead center (<span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Four-stroke_engine">BTDC</a></span>) at which the spark plug for #1 cylinder <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Ignition_timing">starts to fire</a></span>.<br/><br/>A negative value indicates that the spark plug fires after cylinder #1 reaches the top while a positive value indicates adplug firing</td>
            </tr>
            <tr>
                <td id="pid-engine-run-time">Engine Run Time</td>
                <td>This parameter reports the follow data (as supported by the vehicle):
                    <ol>
                        <li>Total engine run time in seconds</li>
                        <li>Total engine idle time in seconds, the vehicle is considered to be idling when there is:
                            <ul>
                                <li>No user throttle input</li>
                                <li>Engine RPM is less than 150 rpm below standard warmed-up idle</li>
                                <li>PTO (if equipped) is inactive</li>
                                <li>Vehicle speed less than 1 mph (1.6 kph) or Engine RPM less than 200 rpm above normal warmed-up idle</li>
                            </ul>
                        </li>
                        <li>Total run time with PTO engaged (if equipped)</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-run-time-since-engine-start">Run Time Since Engine Start</td>
                <td id="pid-run-time-in-seconds-since-the-engine-was-last-started">Run time in seconds since the engine was last started</td>
            </tr>
            <tr>
                <td id="pid-time-run-with-mil-on">Time Run with MIL On</td>
                <td>Engine run time since check engine light was activated after throwing a code<br/><br/><strong>Note:</strong> Engine run time is different from total elapsed time - for example if the check engine light came on six months ago and you drove an average of 30 minutes per day this value will show roughly 5,400 minutes or 90 hours (3.75 days)<br/><br/>This value will stop increasing when it reaches 65,535 minutes (roughly 45 engine-days)<br/><br/>On Hybrids or vehicles with an auto Stop/Start feature this timer will continue to increase as long as the ignition is on, whether the actual engine is running or not</td>
            </tr>
            <tr>
                <td id="pid-distance-traveled-while-mil-is-activated">Distance Traveled while MIL is Activated</td>
                <td id="pid-the-distance-driven-since-the-check-engine-light-last-illuminated--reset-when-codes-are-cleared-or-the-battery-is-disconnected-">The distance driven since the check engine light last illuminated (reset when codes are cleared or the battery is disconnected)</td>
            </tr>
            <tr>
                <td id="pid-time-since-trouble-codes-cleared">Time since Trouble Codes Cleared</td>
                <td>Engine run time since codes were last cleared (either by a scan tool or disconnecting the battery)<br/><br/><strong>Note:</strong> Engine run time is different from total elapsed time - for example if codes were cleared two weeks ago and you drive an average of 45 minutes per day this value will show roughly 630 minutes or 10.5 hours<br/><br/>This value will stop increasing when it reaches 65,535 minutes (roughly 45 engine-days)<br/><br/>On Hybrids or vehicles with Stop/Start this timer will continue to increase as long as the ignition is on, whether the actual engine is running or not</td>
            </tr>
            <tr>
                <td id="pid-distance-traveled-since-codes-cleared">Distance Traveled Since Codes Cleared</td>
                <td>Distance traveled since engine codes were cleared with a scan tool or the battery was disconnected<br/><br/><strong>Note:</strong> clearing non-engine codes (e.g. just clearing ABS) will not reset this value</td>
            </tr>
            <tr>
                <td id="pid-warm-ups-since-codes-cleared">Warm-ups Since Codes Cleared</td>
                <td>Number of engine warm-up cycles since codes were last cleared (or the battery was disconnected)<br/><br/>A warm-up cycle is defined as:
                    <ul>
                        <li><span style="color: rgb(44, 130, 201);"><a href="#pid-coolant-temp">Coolant temperature</a></span> increases at least 22 °C / 40 °F after startup</li>
                        <li>Coolant temp reaches at least 70 °C / 170 °F (or 60°C / 140 °F for diesel)</li>
                    </ul>Once the counter reaches 255 it stops increasing<br/><br/><strong>Note:</strong> clearing non-engine codes (e.g. just clearing SRS) will not reset this value</td>
            </tr>
        </tbody>
    </table>
    <table class="bd-table" id="cat-air-fuel">
        <thead>
            <tr>
                <th scope="col">Datapoint</th>
                <th scope="col">Description</th>
            </tr>
        </thead>
        <thead>
            <tr>
                <th colspan="2">Fuel &amp; Air</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td id="pid-fuel-system-status">Fuel System Status</td>
                <td>Whether your vehicle is running in <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Oxygen_sensor#Automotive_applications">'open' or 'closed' loop</a> </span>mode.
                    <ul>
                        <li>Open loop means the engine computer is using pre-programmed idea air:fuel ratios to decide how much fuel to inject.</li>
                        <li>Closed loop means the ECM is using feedback from the O2 sensors to adjust the air:fuel ratio to prevent an excessively lean (too much air)or rich (too much gas) condition</li>
                    </ul><br/><strong>Note:</strong> This datapoint reports the current status for two fuel systems (A &amp; B) - these represent two distinct systems (e.g. CNG &amp; diesel) on one vehicle as opposed to bank numbers. Most passenger vehicles will have one fuel system only and will report system B as open loop at all times.</td>
            </tr>
            <tr>
                <td id="pid-oxygen-sensor-voltage">Oxygen Sensor Voltage</td>
                <td>O2 sensor voltage (see <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551791-how-are-o2-sensors-displayed-">How are O2 Sensors Displayed?</a></span>)<br/><br/>For more information on O2 sensor operation and interpretation see <span style="color: rgb(44, 130, 201);"><a href="http://www.walkerproducts.com/o2-sensor-training-guide">Walker's O2 Sensor Training Guide</a></span></td>
            </tr>
            <tr>
                <td id="pid-oxygen-sensor-equivalence-ratio">Oxygen Sensor Equivalence Ratio</td>
                <td>O2 sensor equivalence ratio - aka Lambda (see <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551791-how-are-o2-sensors-displayed-">How are O2 Sensors Displayed?</a></span>)</td>
            </tr>
            <tr>
                <td id="pid-oxygen-sensor-current">Oxygen Sensor Current</td>
                <td>Similar to <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551791-how-are-o2-sensors-displayed-">O2 sensor voltage</a></span>:
                    <ul>
                        <li>A value of 0mA indicates a well balanced air:fuel ratio</li>
                        <li>Positive current indicates a lean mixture</li>
                        <li>Negative current indicates a rich mixture</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-short-term-fuel-trim">Short Term Fuel Trim</td>
                <td>Fuel injection rate adjustment based on rapdily changing data from the O2 sensors.
                    <ul>
                        <li>A negative trim indicates a rich condition (less fuel required) while a positive means the engine is running on the lean side.</li>
                        <li>Bank number refers to the 'side' of the engine (see <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551791-how-are-o2-sensors-displayed-">How are O2 Sensors Displayed?</a></span>)</li>
                        <li>Sensor 1 vs Sensor 2 indicates pre (#1) and post (#2) catalytic converter sensors (see <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551791-how-are-o2-sensors-displayed-">How are O2 Sensors Displayed?</a></span>)</li>
                    </ul>Short term fuel trim is combined with<span style="color: rgb(44, 130, 201);"> <a href="#pid-long-trim">long term fuel trim</a></span> for a net correction to be applied to the injection rate<br/><br/><strong>Note:</strong> Many vehicles will not use fuel trim from the post-cat sensors, in this case fuel trim will be displayed as <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551799-why-is-fuel-trim-99-2-" rel="noopener noreferrer" target="_blank">99.2%</a></span></td>
            </tr>
            <tr>
                <td id="pid-long-trim">Long Term Fuel Trim</td>
                <td>Similar to short term trim, long term fuel trim reacts less readily to sudden changes and represents the 'learned' behaviour of the vehicle over a longer period.
                    <ul>
                        <li>Bank 1 vs Bank 2 indicates the side of the engine</li>
                        <li>Sensor 1 vs Sensor 2 indicates pre (#1) and post (#2) catalytic converter sensors</li>
                    </ul><strong>Note:</strong> Many vehicles will not use fuel trim from the post-cat sensors, in this case fuel trim will be displayed as <span style="color: rgb(44, 130, 201);"><a href="http://support.bluedriver.com/support/solutions/articles/43000551799-why-is-fuel-trim-99-2-" rel="noopener noreferrer" target="_blank">99.2%</a></span><br/> <br/><br/><img alt="" class="fr-fic fr-dii lightbox-image" data-index="0" src="/customer/portal/attachments/923585" style="max-width: 400px;" /></td>
            </tr>
            <tr>
                <td id="pid-commanded-equivalence-ratio">Commanded Equivalence Ratio</td>
                <td>The fuel:air ratio requested by the ECM, displayed as a <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Air%E2%80%93fuel_ratio#Air%E2%80%93fuel_equivalence_ratio_(%CE%BB)">lambda</a></span> value <em>(&gt;1 lean, &lt;1 rich, ~1 ideal ratio)</em><br/><br/>Vehicles with wide range O2 sensors:
                    <ul>
                        <li>Commanded equivalence ratio is displayed in open &amp; closed loop mode</li>
                    </ul>Vehicles with conventional O2 sensors:
                    <ul>
                        <li>Commanded equivalance ratio displayed in open loop mode</li>
                        <li>In closed loop mode displayed as 1.0</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-mass-air-flow-rate">Mass Air Flow Rate</td>
                <td>The flow rate of air traveling through the intake in g/s or lb/min<br/><br/>On turbocharged vehicles the MAF will be upstream of the turbo</td>
            </tr>
            <tr>
                <td id="pid-intake-air-temperature">Intake Air Temperature</td>
                <td>Temperature of the air traveling through the intake.<br/><br/>Turbocharged vehicles may have two IAT sensors - sensor #1 before the turbocharger and sensor #2 downstream of the turbo.<br/>Depending on vehicle configuration there may also be two intake tracts in which case sensor data may be reported for banks 1 and 2.<br/><br/>In normal operation the intake temperature should be slightly above the ambient air temperature</td>
            </tr>
            <tr>
                <td id="pid-manifold-pressure">Intake Manifold Absolute Pressure</td>
                <td>Pressure measurement inside the intake manifold.<br/><br/>For turbocharged applications this represents the pressure at the manifold, after the turbo/intercooler/etc<br/><br/><strong>Note:</strong> This is an <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">absolute pressure value</a></span>:
                    <ul>
                        <li>At engine idle it will show slightly lower than <span style="color: rgb(44, 130, 201);"><a href="#pid-ambient-pressure">ambient pressure</a></span> (14.7 psi / 101.35 kPa) indicating a vacuum</li>
                        <li>At key on/engine off it will show ambient/atmospheric pressure</li>
                        <li>When running MAP will show total pressure, to obtain a gauge value subtract the current atmoshperic value</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-fuel-pressure--gauge-">Fuel Pressure (Gauge)</td>
                <td>Fuel pressure value.<br/><br/><strong>Note:</strong> This is a <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">gauge value</a> </span>- a value of 0 indicates atmospheric/ambient pressure</td>
            </tr>
            <tr>
                <td id="pid-fuel-rail-pressure">Fuel Rail Pressure</td>
                <td>Pressure in the fuel rail displayed as a <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">gauge value</a></span> (0 psi/kPa means an atmoshperic/ambient pressure reading)</td>
            </tr>
            <tr>
                <td id="pid-fuel-rail-pressure--absolute-">Fuel Rail Pressure (Absolute)</td>
                <td>Pressure in the fuel rail displayed as an <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">absolute pressure</a></span> value - when the fuel rail is not presurized this datapoint will display ambient pressure - roughly 14.7 psi or 101.3 kPa</td>
            </tr>
            <tr>
                <td id="pid-fuel-rail-pressure--relative-to-manifold-vacuum-">Fuel Rail Pressure (relative to manifold vacuum)</td>
                <td id="pid-fuel-pressure-value-relative-to-the-intake-manifold">Fuel pressure value relative to the intake manifold</td>
            </tr>
            <tr>
                <td id="pid-alcohol-fuel--">Alcohol Fuel %</td>
                <td>The ethanol/alcohol content as measured by the engine computer in percentage.<br/><br/>For example an E85 blend would show 85% for alcohol fuel percentage</td>
            </tr>
            <tr>
                <td id="pid-fuel-level-input">Fuel Level Input</td>
                <td id="pid-percent-of-maximum-fuel-tank-capacity">Percent of maximum fuel tank capacity</td>
            </tr>
            <tr>
                <td id="pid-engine-fuel-rate">Engine Fuel Rate</td>
                <td>Near-instantaneous fuel consumption rate, expressed in Liters or Gallons per hour<br/><br/>Engine fuel rate is calculated by the ECM using the volume of fuel used during the last 1000 ms<br/><br/><strong>Note:</strong> engine fuel rate does not include fuel consumed by diesel aftertreatment systems</td>
            </tr>
            <tr>
                <td id="pid-cylinder-fuel-rate">Cylinder Fuel Rate</td>
                <td id="pid-the-calculated-amount-of-fuel-injected-per-cylinder-during-the-most-recent-intake-stroke---displayed-in-mg-stroke">The calculated amount of fuel injected per cylinder during the most recent intake stroke - displayed in mg/stroke</td>
            </tr>
            <tr>
                <td id="pid-fuel-system-percentage-use">Fuel System Percentage Use</td>
                <td>This parameter displays the % of total fuel usage for each cylinder bank - up to a maximum of four banks.<br/><br/>This datapoint will display data for two separate fuel systems (e.g. diesel &amp; CNG) if supported by the vehicle.</td>
            </tr>
            <tr>
                <td id="pid-fuel-injection-timing">Fuel Injection Timing</td>
                <td>The angle (in degrees) of crankshaft rotation before top dead center (<span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Four-stroke_engine">BTDC</a></span>) at which the fuel injector begins to operate.<br/><br/>A positive angle indicates injector operation before top dead center, while a negative angle indicates operation on the downstroke after TDC</td>
            </tr>
            <tr>
                <td id="pid-fuel-system-control">Fuel System Control</td>
                <td>This parameter reports the following status information for the fuel system on diesel vehicles (for fuel systems 1 &amp; 2 as supported by the vehicle):
                    <ul>
                        <li>Fuel pressure control: <em>Closed</em> or <em>open loop</em> control</li>
                        <li>Fuel injection quantity: <em>Closed</em> or <em>open loop</em> control</li>
                        <li>Fuel injection timining: <em>Closed</em> or <em>open loop</em> control</li>
                        <li>Idle fuel balance/contribution: <em>Closed</em> or <em>open loop</em> control</li>
                    </ul>Closed loop indicates the system is using sensor feedback for fine tuning.<br/><br/><strong>Note:</strong> Systems 1 &amp; 2 refer to two separate fuel systems - system 2 may not be in use on most vehicles</td>
            </tr>
            <tr>
                <td id="pid-fuel-pressure-control-system">Fuel Pressure Control System</td>
                <td>This parameter displays the following data for up to two fuel rails - for sensor location refer to your factory manual:
                    <ol>
                        <li>Commanded rail pressure</li>
                        <li>Actual rail pressure</li>
                        <li>Temperature</li>
                    </ol>Pressure is reported as a <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">gauge pressure</a></span> where 0 indicates rail pressure equal to the outside atmosphere.</td>
            </tr>
            <tr>
                <td id="pid-injection-pressure-control-system">Injection Pressure Control System</td>
                <td>Some diesels use a pump to <span style="color: rgb(44, 130, 201);"><a href="http://www.trucktrend.com/how-to/expert-advice/1304dp-heui-how-high-pressure-oil-injection-systems-work/">pressurize an oil rail</a></span> which then transfers and multiplies this pressure via a piston to provide finer control over fuel injection pressures.<br/><br/>The ICP sensor monitors the pressure on the oil side of the fuel system, depending on the vehicle this parameter will display:
                    <ol>
                        <li>Commanded Control Pressure Rail A</li>
                        <li>Actual Pressure Rail A</li>
                        <li>Commanded Control Pressure Rail B</li>
                        <li>Actual Pressure Rail B</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-boost-pressure-control">Boost Pressure Control</td>
                <td>Depending on the vehicle this parameter will show the following for one or two turbochargers:
                    <ol>
                        <li>ECM commanded boost pressure</li>
                        <li>Actual boost pressure</li>
                    </ol><strong>Note:</strong> All data in this parameter is reported in<span style="color: rgb(44, 130, 201);"> <a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">absolute pressure</a></span> - typically when discussing boost people will refer to gauge pressure. For example a value of 24.7 psi for actual boost pressure would be 10 psi gauge, or "10 lbs of boost". At idle before the turbo spools up this value will read at or slightly below <span style="color: rgb(44, 130, 201);"><a href="#pid-ambient-pressure">ambient pressure</a></span> which should not be confused with producing 14 lbs of boost.<br/><br/>This parameter will also provide feedback on the operating mode of the boost control system, possible states are:
                    <ol>
                        <li>Open Loop - <em>No sensor feedback used, no faults present</em></li>
                        <li>Closed Loop - <em>Using sensor feedback, no faults present</em></li>
                        <li>Fault Present - <em>Boost data unreliable</em></li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-turbocharger-rpm">Turbocharger RPM</td>
                <td>Measured turbine RPM of one or both turbos depending on vehicle configuration.<br/><br/><strong>Note:</strong> This datapoint has a maximum value of 655,350 rpm so you may need to adjust your graph range settings when monitoring data in-app or it may appear as a straight line</td>
            </tr>
            <tr>
                <td id="pid-turbocharger-temperature">Turbocharger Temperature</td>
                <td>This parameter reports the following data for one or both turbochargers as supported by the vehicle:
                    <ol>
                        <li>Compressor inlet temperature - Air charge temperature before the turbo</li>
                        <li>Compressor outlet temperature - Air charge temperature at the turbo outlet - this value should be much higher</li>
                        <li>Turbine inlet temperature - Exhaust temperature pre-turbo</li>
                        <li>Turbine outlet temperature - Exhaust temperature post-turbo</li>
                    </ol>Charge air temperatures have a range from -40 to 215 degC while the exhaust temperature reporting range is -40 to 6513.5 degC</td>
            </tr>
            <tr>
                <td id="pid-turbocharger-compressor-inlet-pressure-sensor">Turbocharger Compressor Inlet Pressure Sensor</td>
                <td>Pressure measured at the turbocharger inlet, for either one or two turbos depending on vehicle configuration<br/><br/>This is an <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">absolute pressure</a> </span>value, a value of roughly 14.7 psi / 101.3 kPa indicates atmoshperic pressure</td>
            </tr>
            <tr>
                <td id="pid-variable-geometry-turbo--vgt--control">Variable Geometry Turbo (VGT) Control</td>
                <td>Vehicles with <span style="color: rgb(44, 130, 201);"><a href="https://paultan.org/2006/08/16/how-does-variable-turbine-geometry-work/">variable geometry turbos</a></span> use motors or another method of actuation to change the orientation of vanes which will either direct the exhaust gasses around, or through the turbine blades.<br/><br/>The VGT parameter displays data related to the position/orientation of these vanes in the turbhocharger. A value of 0% indicates that the vanes are in the maximum bypass position while at 100% the vanes redirect as much exhaust gas as possible to build boost.<br/><br/>VGT Control displays the following information for one or both turbos depending on vehicle configuration:
                    <ol>
                        <li>Commanded VGT Position - Vane position requested by the vehicle</li>
                        <li>Actual VGT Vane Position</li>
                        <li>VGT Control Status: Closed or Open Loop (using sensor feedback or not) without system faults or in a Fault State (VGT position data is unreliable)</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-wastegate-control">Wastegate Control</td>
                <td>The <span style="color: rgb(44, 130, 201);"><a href="https://www.aet-turbos.co.uk/turbo-tech-101-what-is-a-turbo-wastegate-and-how-does-it-work/">wastegate</a></span> allows exhaust gas to bypass the turbo as boost builds to prevent excessive pressure.<br/><br/>This parameter reports the following information for electronic wastegate systems (one or two depending on the vehicle configuration):
                    <ol>
                        <li>Commanded wastegate position as requested by the controller - 0% represents fully closed (all exhaust routed through the turbo) and 100% indicates maximum diversion around the turbine section.</li>
                        <li>Actual wastegate position - 0% to 100%</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-charge-air-cooler-temperature--cact-">Charge Air Cooler Temperature (CACT)</td>
                <td>This parameter reports the temperature of the <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Intercooler#Internal_combustion_engines">intercooler</a></span>air charge on turbocharged vehicles with up to four sensors:
                    <ol>
                        <li>Bank 1 Sensor 1</li>
                        <li>Bank 1 Sensor 2</li>
                        <li>Bank 2 Sensor 1</li>
                        <li>Bank 2 Sensor 2</li>
                    </ol>The SAE/OBDII standard does not specify a default mapping for these datapoints so you may need to refer to the factory manual for your vehicle to determine sensor/measurement locations.</td>
            </tr>
        </tbody>
    </table>
    <table class="bd-table" id="cat-emissions-control">
        <thead>
            <tr>
                <th scope="col">Datapoint</th>
                <th scope="col">Description</th>
            </tr>
        </thead>
        <thead>
            <tr>
                <th colspan="2">Emissions Control</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td id="pid-command-egr">Commanded EGR</td>
                <td>How open the <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Exhaust_gas_recirculation">EGR</a></span> valve should be as requsted by the engine computer (0% fully closed, 100% fully open)</td>
            </tr>
            <tr>
                <td id="pid-egr-error">EGR Error</td>
                <td>The percent difference between the <span style="color: rgb(44, 130, 201);"><a href="#pid-command-egr">commanded EGR</a></span> opening and the actual opening of the<span style="color: rgb(44, 130, 201);"> <a href="https://en.wikipedia.org/wiki/Exhaust_gas_recirculation">EGR valve</a></span>.<br/><br/><strong>Special Note:</strong>If commanded EGR is 0%, EGR error will read:
                    <ul>
                        <li>0% if actual EGR is also 0%</li>
                        <li>99.2% if actual EGR is anything other than 0% - this indicates "undefined" or not applicable<br/><br/><em>EGR error is caluclated as (actual - commanded)/commanded<br/> A commanded value of 0% gives (0-0)/0 = 0%<br/> With any other 'commanded' value the calculation becomes (actual-0)/0 which is undefined</em></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-commanded-diesel-intake-air-flow-control">Commanded Diesel Intake Air Flow Control</td>
                <td>Also referred to as <span style="color: rgb(44, 130, 201);"><a href="https://www.dieselnet.com/tech/engine_egr_sys.php">EGR Throttle</a></span>.<br/><br/>Some newer diesels may employee a throttle plate to generate an intake vacuum under some conditions for the purpose of introducing EGR gasses to reduce emissions.<br/><br/>This datapoint displays (if supported by the vehicle):
                    <ol>
                        <li>The commanded (closed to 100% open) position of the intake air flow throttle plate</li>
                        <li>The actual position of the EGR throttle</li>
                        <li>Commanded position of a second EGR throttle if fitted</li>
                        <li>Actual position of secondary EGR throttle</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-exhaust-gas-recirculation-temperature">Exhaust Gas Recirculation Temperature</td>
                <td>This parameter reports up to four EGR temperature values:
                    <ol>
                        <li>EGRTA - Bank 1 Pre-Cooler</li>
                        <li>EGRTB - Bank 1 Post-Cooler</li>
                        <li>EGRTC - Bank 2 Pre-Cooler</li>
                        <li>EGRTD - Bank 2 Post-Cooler</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-evap-system-vapor-pressure">EVAP System Vapor Pressure</td>
                <td><span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">Gauge pressure</a></span> of the <span style="color: rgb(44, 130, 201);"><a href="https://auto.howstuffworks.com/evaporative-emission-control-system.htm">EVAP</a></span> system measured from either a sensor in the fuel tank or evap system line<br/><br/>See your factory manual or a parts diagram for sensor location.</td>
            </tr>
            <tr>
                <td id="pid-absolute-evap-system-vapor-pressure">Absolute Evap System Vapor Pressure</td>
                <td>Absolute pressure of the <span style="color: rgb(44, 130, 201);"><a href="https://auto.howstuffworks.com/evaporative-emission-control-system.htm">EVAP</a></span> system measured from either a sensor in the fuel tank or evap system line (see your factory manual for vehicle specific measurement point)<br/><br/>This is an <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Pressure_measurement#Absolute,_gauge_and_differential_pressures_%E2%80%94_zero_reference">absolute pressure</a></span> measurement, a value of roughly 14.7 psi or 101.3 kPa indicates 0 gauge pressure relative to outside ambient conditions</td>
            </tr>
            <tr>
                <td id="pid-commanded-evaporative-purge">Commanded Evaporative Purge</td>
                <td><span style="color: rgb(44, 130, 201);"><a href="https://auto.howstuffworks.com/evaporative-emission-control-system.htm">EVAP</a></span>purge flow rate requested by the engine computer
                    <ul>
                        <li>0% fully closed</li>
                        <li>100% maximum</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-catalyst-temperature">Catalyst Temperature</td>
                <td>Temperature of the <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Catalytic_converter">catalytic converter</a></span>.
                    <ul>
                        <li>Bank # indicates the "side" of the engine (typically bank 1 will be on the same side as cylinder #1)</li>
                        <li>Sensor # indicates whether the sensor is pre (#1) or post (#2) cat</li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-diesel-aftertreatment-status">Diesel Aftertreatment Status</td>
                <td>The <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Diesel_particulate_filter">Diesel Particulate Filter</a></span> is used for trapping soot and reducing exhaust emissions on diesel vehicles. As soot accumulates, the filter will become "clogged" and the pressure drop across the filter will increase (see <a href="#pid-part-filter">'<span style="color: rgb(44, 130, 201);">Diesel Particulate Filter</span>'</a>). When the filter reaches a set criteria it must be 'regenerated' - the soot is burned off through various methods so that the filter can be used again.<br/><br/>DPF Regeneration can be:
                    <ol>
                        <li>Passive - using standard exhaust tempreature while driving</li>
                        <li>Active - using fuel injection to increase the exhaust temperature</li>
                        <li>Forced - triggered using a factory scan tool before the regen criteria of the vehicle is met</li>
                    </ol><span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/NOx_adsorber">NOx adsorbtion</a></span> involves the use of various substances in the exhaust to 'trap' Nitrous Oxide - unlike with<span style="color: rgb(44, 130, 201);"> <a href="https://en.wikipedia.org/wiki/Selective_catalytic_reduction">SCR</a></span> there is no consumable fluid that needs to be topped up, but as the NOx 'trap' reaches capacity it must be regenerated. NOx adsorber regeneration involves exposing the 'trap' to a reductant such as fuel or hydrogen which reacts with the NOx to produce N2 and water. Over time SOx will also build up in the NOx adsorbtion system which requires a high temperature 'desulferization' process to restore the system to operating conditions.<br/><br/>This is a hybrid datapoint capable of display the following (if supported by your vehicle):
                    <ol>
                        <li>Current DPF Regeneration Status: <em>Active/Not Active</em></li>
                        <li>Current DPF Regeneration Type: <em>Passive/Active</em></li>
                        <li>NOx Adsorber Regen Status: <em>Active/Not Active</em></li>
                        <li>NOx Adsorber Desulferization Status: <em>Active/Not Active</em></li>
                        <li>Normalized Trigger for DPF Regen: The percentage until the next regen event - 0% means a regen has just completed while 100% indicates one is about to start</li>
                        <li>Average Time Between DPF Regens: The exponential weighted moving average time between regen events, indidicating a representative value over the last 6 events</li>
                        <li>Average Distance Between DPF Regens: The exponential weighted moving average distance driven between regen events, indidicating a representative value over the last 6 events</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-diesel-exhaust-fluid-sensor-data">Diesel Exhaust Fluid Sensor Data</td>
                <td>This parameter will display the followng information (as supported by the vehicle):
                    <ol>
                        <li>DEF Type: Urea too high, Urea too low, Straight diesel, Proper DEF, Sensor fault</li>
                        <li>DEF Concentration: Urea concentration - should display roughly 32.5% for proper DEF</li>
                        <li>DEF Tank Temperature</li>
                        <li>DEF Tank Level - Important note: tank level may not change progressively, see "NOx Control System" above for more information</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-part-filter">Diesel Particulate Filter (DPF)</td>
                <td>This parameter reports up to three separate datapoints:
                    <ol>
                        <li>Inlet pressure</li>
                        <li>Outlet pressure</li>
                        <li>Differential pressure across the particulate filter</li>
                    </ol>An increase in differential pressure indicates that soot is accumulating in the filter, possibly indicative of an upcoming regeneration event<br/><br/>Bank 1 vs 2 indicate the 'side' of the engine - bank #1 will be on the same 'side' of the engine as cylinder #1</td>
            </tr>
            <tr>
                <td id="pid-diesel-particulate-filter--dpf--temperature">Diesel Particulate Filter (DPF) Temperature</td>
                <td>This parameter reports up to two datapoints for the particulate filter on each exhaust bank:
                    <ol>
                        <li>Inlet temperature</li>
                        <li>Outlet temperature</li>
                    </ol>Bank 1 vs 2 indicate the 'side' of the engine - bank #1 will be on the same 'side' of the engine as cylinder #1</td>
            </tr>
            <tr>
                <td id="pid-nox-sensor">NOx Sensor</td>
                <td>This hybrid parameter reports the NOx concentration levels in ppm of the following sensors (if supported):
                    <ol>
                        <li>Bank 1 Sensor 1</li>
                        <li>Bank 1 Sensor 2</li>
                        <li>Bank 2 Sensor 1</li>
                        <li>Bank 2 Sensor 2</li>
                    </ol>Bank # indicates the 'side' of the engine for this exhaust - bank #1 is on the same side of the engine as cylinder #1<br/>Sensor number indicates whether the sensor is situated before (#1) or after (#2) the NOx adsorbtion system</td>
            </tr>
            <tr>
                <td id="pid-nox-control-system">NOx Control System</td>
                <td>This hybrid parameter reports the following data on the <span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Selective_catalytic_reduction">NOx adsorption</a></span>system (as supported by the vehicle):
                    <ol>
                        <li>Average Reagent Consumption Rate - Calculated either over the previous 48 hours of engine run time or the last 15L consumed (whichever is a longer period). This value will be reported as 0 when the key is on with the engine off</li>
                        <li>Average Demanded Consumption Rate - As commanded by the ECM, calculated either over the previous 48 hours of engine run time or the last 15L consumed (whichever is a longer period). This value will be reported as 0 when the key is on with the engine off</li>
                        <li>Reagent Tank Level - 0 to 100%<br/><strong>Note:</strong> Depending on the vehicle, tank level might not display a progressive value between 100% and 0% as fluid is consumed - tank level may only display values at specific measurement points.<br/>If a vehicle is not capable of reporting true tank level at all times, it will show the average between each discrete step when not measuring that exact level.<br/>For example, consider a vehicle that is only capable of directly measuring three tank levels: full at 100%, low at 20%, empty at 0% As fluid is consumed to depletion over time this datapoint will report:
                            <ul>
                                <li>100% when full</li>
                                <li>60% while the actual fluid level is between 20% and 100%</li>
                                <li>20%</li>
                                <li>10% while actual level is between 0% and 20%</li>
                                <li>0% at empty</li>
                            </ul>
                        </li>
                        <li>NOx Warning Indicator Time - Total engine run time in seconds since the NOx/SCR (DEF etc) warning light has activated on the dash. This datapoint will start at 0 when the NOx warning light comes on, and count up for every second of engine run time that the light is on - to a maximum of 136 years (seriously).<br/>Once the NOx light goes out the counter will stop increasing, and it will reset if the light comes back on or 9600 engine-hours pass without the light activating again.</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-nox-sensor-corrected-data">NOx Sensor Corrected Data</td>
                <td id="pid-nox-concentration-in-ppm-including-learned-adjustments-and-offsets-">NOx concentration in PPM including learned adjustments and offsets.</td>
            </tr>
            <tr>
                <td id="pid-nox-nte-control-area-status">NOx NTE Control Area Status</td>
                <td>The NOx "not to exceed control area" is a range of engine operation (speed and load) in which emissions are sampled and tested vs governmental NOx limits.<br/><br/>In addition, automakers may petition the governing body for special vehicle specific exemptions for engine operation envelopes that may normally fall within the NTE test range, but that they believe should not apply. If this exception is granted a 'carve-out area' of the engine operating envelope may be defined, in which NTE limits do not apply for this specific vehicle.<br/><br/>This parameter displays (as supported by the vehicle):
                    <ol>
                        <li>Whether vehicle is operating inside or outside the NOx control area</li>
                        <li>Whether the vehicle is operating inside the manufacturer exceptio/"carve-out" region</li>
                        <li>Whether the vehicle is experiencing an NTE related deficiency within the NOx operating control area</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-pm-sensor-bank-1---2">PM Sensor Bank 1 &amp; 2</td>
                <td>This parameter reports the following data (as supported by the vehicle) for banks 1 &amp; 2:
                    <ul>
                        <li>Particulate matter sensor active: <em>yes/no</em></li>
                        <li>Particulate matter sensor regenerating: <em>yes/no</em></li>
                        <li>Particulate matter sensor value: <em>0% (clean)</em> to <em>100% (regen required)</em></li>
                    </ul>
                </td>
            </tr>
            <tr>
                <td id="pid-particulate-matter--pm--sensor">Particulate Matter (PM) Sensor</td>
                <td id="pid-the-soot-concentration-as-measured-by-the-particulate-matter-sensors-on-banks-1---2---displayed-in-mg-m3">The soot concentration as measured by the particulate matter sensors on banks 1 &amp; 2 - displayed in mg/m3</td>
            </tr>
            <tr>
                <td id="pid-pm-nte-control-area-status">PM NTE Control Area Status</td>
                <td>The PM "not to exceed control area" is a range of engine operation (speed and load) in which emissions are sampled and tested vs governmental particulate matter emission limits.<br/><br/>In addition, automakers may petition the governing body for special vehicle specific exemptions for engine operation envelopes that may normally fall within the NTE test range, but that they believe should not apply. If this exception is granted a 'carve-out area' of the engine operating envelope may be defined, in which NTE limits do not apply for this specific vehicle.<br/><br/>This parameter displays (as supported by the vehicle):
                    <ol>
                        <li>Whether vehicle is operating inside or outside the PM control area</li>
                        <li>Whether the vehicle is operating inside the manufacturer exceptio/"carve-out" region</li>
                        <li>Whether the vehicle is experiencing an NTE related deficiency within the PM operating control area</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-scr-inducement">SCR Inducement System</td>
                <td><span style="color: rgb(44, 130, 201);"><a href="https://en.wikipedia.org/wiki/Selective_catalytic_reduction">Selective Catalytic Reduction</a></span> is used on diesel engines to reduce the amount of NOx in the exhaust using a catalyst and reductant/reagent (often urea or ammonia)<br/><br/>Inducement refers to strategies employed by the vehicle to alert drivers that there is an issue with the SCR system requiring their attention - depending on the vehicle this may be a dash light, cluster message, or functional restriction (torque reduction/limp mode, speed limiter, etc)<br/><br/>SCR inducement will be triggered by one or more of the following:
                    <ol>
                        <li>Low reagent level</li>
                        <li>Incorrect reagent used (e.g. water instead of DEF)</li>
                        <li>Abnormal reagent consumption rates</li>
                        <li>Excessive NOx emissions</li>
                    </ol>This paramter will report current SCR inducement status (on or off) as well as the reasons for activation. Additionally it will show whether any of the above have occurred during the the last:
                    <ol>
                        <li>0 - 10,000 km</li>
                        <li>10,000 - 20,000 km</li>
                        <li>20,000 - 30,000 km</li>
                        <li>30,000 - 40,000 km</li>
                    </ol>Depending on the vehicle it may also report the total distance traveled during each 10,000 km block above with the inducement system active</td>
            </tr>
            <tr>
                <td id="pid-nox-warning-and-inducement-system">NOx Warning And Inducement System</td>
                <td>This parameter displays information on warning/inducement levels - for more information on inducements see <span style="color: rgb(44, 130, 201);"><a href="#pid-scr-inducement">SCR Induce System</a></span>.<br/><br/>Warning/inducement levels are broken down in to three levels:
                    <ul>
                        <li>Level 1 - Low severity, e.g. minor power/torque reduction</li>
                        <li>Level 2 - Medium severity, e.g. significant power/torque reduction (limp mode)</li>
                        <li>Level 3 - Severe, e.g. complete engine shut down, extreme operational limits</li>
                    </ul>Each level will report one of the four following statuses:
                    <ol>
                        <li>Inactive</li>
                        <li>Enabled, but not active (triggered - but not taking effect yet)</li>
                        <li>Active</li>
                        <li>Not supported by vehicle</li>
                    </ol>This parameter will also report (as supported):
                    <ol>
                        <li>Total engine hours using incorrect reagent</li>
                        <li>Total engine hours with incorrect reagent consumption rate</li>
                        <li>Total engine hours during which reagent dosing was interrupted (e.g. AECD)</li>
                        <li>Total engine hours during which there was an active DTC for incorrect EGR operation</li>
                        <li>Total engine hours during which there was an active DTC for incorrect NOx control equipment operation</li>
                    </ol>
                </td>
            </tr>
            <tr>
                <td id="pid-engine-run-time-for-aecd">Engine Run Time for AECD</td>
                <td>An "Emissions Increasing Auxiliary Emissions Control Device" (AECD) is a vehicle system that has the ability to disable certain components of the vehicle's emissions control equipment. As opposed to a "defeat device", stock AECDs are permitted under regulation, but their operation and justification for use must be demonstrated to the governing body (e.g. EPA).<br/><br/>Example of applications for AECDs include:
                    <ul>
                        <li>Mitigation of engine damage during abnormal operation</li>
                        <li>Providing maximum power/torque for emergency situations</li>
                        <li>Ensuring continuous operation of emergency equipment</li>
                    </ul>This datapoint displays the total time (in seconds) during which each AECD was active. This parameter does not provide information regarding the purpose or operation of each AECD - only the device # is listed, a factory manual may be required for more AECD specific information.<br/><br/>Each listed AECD may display one or two timers:
                    <ul>
                        <li>If only one timer is used:
                            <ul>
                                <li>TIME1 will display the total engine run time during which this AECD was active</li>
                                <li>TIME2 will display a maximum value (136 years) to indicate "not used"</li>
                            </ul>
                        </li>
                        <li>If two timers are used:
                            <ul>
                                <li>TIME1 will display engine run time during which an AECD was inhibiting up to 75% of emissions control performance</li>
                                <li>TIME2 displays engine run time during which emissions control was inhibited beyond 75%</li>
                            </ul>
                        </li>
                    </ul>These timers can not be reset by a scan tool or by disconnecting the battery</td>
            </tr>
        </tbody>
    </table>
    <table class="bd-table"></table>
</div>
</body>
</html>
