<center>
<img src="img/title.png" alt="table" />
</center>
<br>

I use this to plot my live data

1) Loads the CSV from [BlueDriver Scanner App](https://www.bluedriver.com/products/bluedriver-scan-tool)
2) Dynamically creates checkboxes based on stats recorded in the file.
3) Lazy attempt at linking stats to definition table (buggy, your a big boy, use ctrl-f or write it :)

## How ?

it uses HTML and JavaScript, just load the HTML file in your browser, then load you file. (see test file in the [test directory](test/example.csv))

## Tips

1) Remove the last few seconds if you were recording while the car was off, it will mess up the scaling

## Screen Shots

![screenshot1](img/screenshot1.png)


![screenshot2](img/screenshot2.png)


## Live Data Definitions

Take From [BlueDriver Support Site](https://support.bluedriver.com/support/solutions/articles/43000551789-live-data-guide)

![screenshot3](img/screenshot3.png)

## How are O2 Sensors Displayed?

### Sensor Type
Depending on your vehicle you may have standard or wide range O2 sensors. 

 

#### Standard or "Narrow" Band
Narrow band O2 sensors are typically found on earlier OBDII compatible vehicles as well as post-cat on newer models. Typically these sensors have an output range of 0-1 volts.
 

#### Wideband Sensors 
Wideband O2 sensors are commonly used on newer vehicles upstream of the catalytic converter and will have a wider range - typically 0-5V. Wideband sensors may often be displayed using the prefix "WR" (e.g. "WR02B1S1") and will generally be displayed using one of the following units:

 - Voltage
 - Equivalence Ratio: Lambda, or λ is used to display the current Air:Fuel ratio compared to the ideal stoichiometric AFR. For a lambda value greater than one this means the current AFR is higher than the ideal AFR which indicates a lean condition.
     - λ < 1.0 rich
     - λ ~ 1.0 ideal
     - λ > 1.0 lean


*Note: this is the inverse of the fuel:air equivalence ratio ϕ*


 - Current: Similar to equivalence ratio but displayed in milliamps
     - Positive Current Lean
     - 0 mA ~ ideal
     - Negative Current Rich 


### Bank 
The bank number refers to which 'side' of the engine the sensor is associated with and usually corresponds to the location of cylinders #1 & #2.

Typical layouts (note your vehicle may be numbered differently):

1) Inline 4 Cylinder (transverse & longitudinal)
   Generally I4 engines will have a single exhaust manifold so you will only see sensors on bank #1
 
2) Inline 6+ Cylinder
   Some engines will have a single exhaust manifold/bank while others (e.g. BMW) will have two banks where bank #2 corresponds to cylinders 4 through 6.
 
3) Transverse V6+ (most FWD, some AWD vehicles)
   Cylinder #1 is generally the closest one to the 'front' of the engine, depending on the orientation of the engine your vehicle this could be the front or rear facing cylinder bank.
 
4) Inline V6+ & Boxer (most RWD, some AWD vehicles)
   Generally cylinder #1 / bank #1 are on the driver's side of the vehicle, although on some Audi/Ford V8s as well as Land Rovers and Subarus this may be reversed.

 
### Sensor Number

1) Sensor 1

Sensor #1 is upstream of the catalytic converter on the exhaust manifold and used for monitoring and adjusting the AFR.
 
2) Sensor 2

Sensor #2 is downstream of the catalytic converter and used to monitor its operation and efficiency. Typically this sensor is not used for adjusting fuel trim, if your vehicle reports S2 fuel trims they may appear as -99.2% which indicates "Not Used".
 
### Example

On a 2014 Chevrolet Silverado 1500 with the 5.3 V8, B1S1 would refer to the standard pre-cat O2 sensor on the exhaust manifold on the driver's side of the engine.

### In-Depth Training

For more information on interpreting O2 sensor data [Walker Products has a great training guide](http://www.walkerproducts.com/o2-sensor-training-guide/introduction/) which takes roughly 30-60 minutes to complete.


## Understanding LOAD VALUES and the Two "Load" Percentages:

####  **Calculated Engine Load Value (%)**

* Defined by SAE J1979 (PID 04)
* Formula:

  $$
  \text{Load} = \frac{\text{Air Mass}}{\text{Max Theoretical Air Mass at that RPM}} \times 100
  $$
* Reflects **driver demand, intake restriction, turbo boost**, and RPM
* BMWs often idle at 30–40% (normal)

####  **Absolute Load Value (%)**

* Defined by SAE J1979 (PID 43)
* Formula:

  $$
  \text{Load} = \frac{\text{Current Air Mass}}{\text{Air Mass at WOT at current RPM}} \times 100
  $$
* **Ignores driver input and throttle position**
* Often **higher at idle**, especially in turbo engines


###  Why Absolute Load Is > 70% at Idle:

* Turbocharged engines can reach WOT airflow **very easily** due to low compression ratio and low manifold pressure at idle
* Thus, **"idle airflow" / "max WOT airflow at same RPM"** results in **high percentages**
* This is expected — not a fault


###  Why They Both Increase With Electrical Load:

* Additional alternator drag and torque demand increase:

  * Airflow
  * Injector pulse width
  * Throttle opening
* Both PIDs react accordingly, which is **a sign of good ECU behavior**


###  Summary:

| Load Type           | % at Idle | % at Load                       | Affected by Throttle? | Affected by Electrical Load? |
| ------------------- | --------- | ------------------------------- | --------------------- | ---------------------------- |
| **Calculated Load** | 30–34%    | Up to \~90%                     | ✅ Yes                 | ✅ Yes                        |
| **Absolute Load**   | 70–74%    | Up to \~400% (buggy or clipped) | ❌ Not directly        | ✅ Yes                        |

