<?php

include('connect.php');


// Get the selected year
$year = isset($_GET['year']) ? $_GET['year'] : 'all';
// Condition for SQL queries (if a specific year is selected, add WHERE clause)
$year_condition = ($year === 'all') ? "" : "YEAR(`dateCommitted`) = $year";


// PENDING CASES
$pending_query = "SELECT COUNT(*) AS count FROM crimemapping WHERE";
if (!empty($year_condition)) {
  $pending_query .= " $year_condition AND";
}
$pending_query .= " `caseStatus` = 'Under Investigation'";
$pending_result = $conn->query($pending_query);
$pending_cases = ($pending_result && $row = $pending_result->fetch_assoc()) ? (int) $row['count'] : 0;


// CLEARED CASES
$cleared_query = "SELECT COUNT(*) AS count FROM crimemapping WHERE";
if (!empty($year_condition)) {
  $cleared_query .= "  $year_condition AND";
}
$cleared_query .= " `caseStatus` = 'Cleared'";
$cleared_result = $conn->query($cleared_query);
$cleared_cases = ($cleared_result && $row = $cleared_result->fetch_assoc()) ? (int) $row['count'] : 0;


// SOLVED CASES
$solved_query = "SELECT COUNT(*) AS count FROM crimemapping WHERE";
if (!empty($year_condition)) {
  $solved_query .= " $year_condition AND";
}
$solved_query .= " `caseStatus` = 'Solved'";
$solved_result = $conn->query($solved_query);
$solved_cases = ($solved_result && $row = $solved_result->fetch_assoc()) ? (int) $row['count'] : 0;


// TOTAL CASES
$total_query = "SELECT COUNT(*) AS count FROM crimemapping";
if (!empty($year_condition)) {
  $total_query .= " WHERE $year_condition";
}
$total_result = $conn->query($total_query);
$total_cases = ($total_result && $row = $total_result->fetch_assoc()) ? (int) $row['count'] : 0;


// CRIME VOLUME = (INDEX + NI)/TOTAL
$volume_query = "SELECT (SUM(CASE WHEN `crimeClassification` = 'index' THEN 1 ELSE 0 END) + 
                  SUM(CASE WHEN `crimeClassification` = 'non-index' THEN 1 ELSE 0 END)) / 
                  COUNT(*) * 100 AS count FROM crimemapping";
if (!empty($year_condition)) {
  $volume_query .= " WHERE $year_condition";
}
$volume_result = $conn->query($volume_query);
$crime_volume = ($volume_result && $row = $volume_result->fetch_assoc()) ? number_format($row['count'], 2) : "0.00";


// CRIME RATE = (TOTAL/POPULATION)*100000
$population = 126347; // as of 2020  
$crime_rate = ($population > 0) ? ($total_cases / $population) * 100000 : 0;
$crime_rate = number_format($crime_rate, 2);


// CLEARANCE EFFICIENCY = (CLEARED/TOTAL)*100
$crime_clearance = ($total_cases > 0) ? ($cleared_cases / $total_cases) * 100 : 0;
$crime_clearance = number_format($crime_clearance, 2);


// SOLUTION EFFIENCY = (SOLVED/TOTAL)*100
$crime_solution = ($total_cases > 0) ? ($solved_cases / $total_cases) * 100 : 0;
$crime_solution = number_format($crime_solution, 2);


// DAILY TREND
$daily_query = "SELECT time, crime_count, rank FROM (
                SELECT `timeCommitted` AS time, 
                      COUNT(*) AS crime_count, 
                      RANK() OVER (ORDER BY COUNT(*) DESC) AS rank 
                FROM crimemapping";
if (!empty($year_condition)) {
  $daily_query .= " WHERE $year_condition";
}
$daily_query .= " GROUP BY `timeCommitted` ) ranked_time WHERE rank <= 10 ORDER BY crime_count DESC";
$daily_result = $conn->query($daily_query);
// Extract Data for Chart.js
$times = [];
$daily_crimes = [];
foreach ($daily_result as $row) {
  $times[] = $row['time'];
  $daily_crimes[] = $row['crime_count'];
}
// Convert Data to JSON for JavaScript
$time_json = json_encode($times);
$daily_crimes_json = json_encode($daily_crimes);



// WEEKLY TREND
$weekly_query = "SELECT day, crime_count, rank FROM (
                SELECT DAYNAME(`dateCommitted`) AS day, 
                COUNT(*) AS crime_count, 
                RANK() OVER (ORDER BY COUNT(*) DESC) 
                AS rank FROM crimemapping";
if (!empty($year_condition)) {
  $weekly_query .= " WHERE $year_condition";
}
$weekly_query .= " GROUP BY DAYNAME(`dateCommitted`) ) ranked_days WHERE rank <= 7 ORDER BY crime_count DESC";
$weekly_result = $conn->query($weekly_query);
$weekly_trend = $weekly_result->fetch_assoc();
// Extract data for Chart.js
$days = [];
$weekly_crimes = [];
foreach ($weekly_result as $row) {
  $days[] = $row['day'];
  $weekly_crimes[] = $row['crime_count'];
}
// Convert data to JSON for JavaScript
$days_json = json_encode($days);
$weekly_crimes_json = json_encode($weekly_crimes);



// MONTHLY TREND
$monthly_query = "SELECT month, crime_count, rank FROM (
                  SELECT MONTHNAME(`dateCommitted`) AS month, 
                  COUNT(*) AS crime_count, 
                  RANK() OVER (ORDER BY COUNT(*) DESC) 
                  AS rank FROM crimemapping";
if (!empty($year_condition)) {
  $monthly_query .= " WHERE $year_condition";
}
$monthly_query .= " GROUP BY MONTHNAME(`dateCommitted`) ) ranked_months 
                    WHERE rank <= 12 ORDER BY crime_count DESC";
$monthly_result = $conn->query($monthly_query);
$monthly_trend = $monthly_result->fetch_assoc();
// Extract data for Chart.js
$months = [];
$monthly_crimes = [];
foreach ($monthly_result as $row) {
  $months[] = $row['month'];
  $monthly_crimes[] = $row['crime_count'];
}
// Convert data to JSON for JavaScript
$months_json = json_encode($months);
$monthly_crimes_json = json_encode($monthly_crimes);



// CRIMES AGAINST CLASSIFICATION
$against_query = "SELECT `crimeAgainst`, COUNT(*) AS crime_count FROM crimemapping WHERE";
if (!empty($year_condition)) {
  $against_query .= " $year_condition AND";
}
$against_query .= " `crimeAgainst` IN ('crimes against person', 'crimes against property', 'special laws')
                  GROUP BY `crimeAgainst`
                  ORDER BY crime_count DESC";
$against_result = $conn->query($against_query);
$crime_against = [];
$against_counts = [];
while ($row = $against_result->fetch_assoc()) {
  $crime_against[] = $row['crimeAgainst'];
  $against_counts[] = $row['crime_count'];
}
// Convert to JSON for JavaScript
$crime_against_json = json_encode($crime_against);
$against_counts_json = json_encode($against_counts);



// HIGH RISK BRGY
$highRisk_query = "SELECT Barangay, COUNT(*) AS Count, 
                  DENSE_RANK() OVER (ORDER BY COUNT(*) DESC) AS Rank
                  FROM crimemapping WHERE 1
                  " . (!empty($year_condition) ? " AND $year_condition" : "") . "
                  GROUP BY Barangay
                  ORDER BY Count DESC
                  LIMIT 10;";
$highRisk_result = $conn->query($highRisk_query);
$brgy_list = [];
while ($row = $highRisk_result->fetch_assoc()) {
  $brgy_list[] = [
    'rank' => $row['Rank'],
    'barangay' => $row['Barangay']
  ];
}
$brgy_json = json_encode($brgy_list);



// PREVALENT OFFENSES
$offenses_query = "SELECT OFFENSE, COUNT(*) AS crime_count FROM crimemapping";
if (!empty($year_condition)) {
  $offenses_query .= " WHERE $year_condition";
}
$offenses_query .= " GROUP BY OFFENSE ORDER BY crime_count DESC";
$offenses_result = $conn->query($offenses_query);
// Extract data for Chart.js
$offenses = [];
$crime_counts = [];
while ($row = $offenses_result->fetch_assoc()) {
  $offenses[] = $row['OFFENSE'];
  $crime_counts[] = $row['crime_count'];
}
// Convert to JSON
$offense_json = json_encode($offenses);
$offense_counts_json = json_encode($crime_counts);



// OFFENSES PER BRGY
// Fetch all barangays
$barangays_query = "SELECT DISTINCT BARANGAY FROM crimemapping";
$barangays_result = $conn->query($barangays_query);
$barangays = [];
while ($row = $barangays_result->fetch_assoc()) {
  $barangays[] = $row['BARANGAY'];
}

// Fetch all offense data grouped by barangay
$offense_query = "SELECT BARANGAY, OFFENSE, COUNT(*) AS crime_count 
                  FROM crimemapping WHERE 1
                  " . (!empty($year_condition) ? " AND $year_condition" : "") . "
                  GROUP BY BARANGAY, OFFENSE ORDER BY BARANGAY, crime_count DESC";
$offense_result = $conn->query($offense_query);

$offense_data = [];
while ($row = $offense_result->fetch_assoc()) {
  $barangay = $row['BARANGAY'];
  if (!isset($offense_data[$barangay])) {
    $offense_data[$barangay] = [];
  }
  $offense_data[$barangay][] = [
    "offense" => $row['OFFENSE'],
    "crime_count" => $row['crime_count']
  ];
}

// Close connection
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>San Juan Analytics</title>
  <link rel="icon" type="image/png" href="images/logo-square.png" />
  <!-- BOOTSTRAP -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <!-- STYLESHEET -->
  <link rel="stylesheet" href="stylesheet.css" />
  <!-- FONT -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter" rel="stylesheet" />

  <style>
    @media print {

      /* Hide specific sections */
      header,
      .navbar,
      footer {
        display: none !important;
      }

      /* Ensure Bootstrap grid is retained */
      .container {
        width: 100% !important;
        max-width: 100% !important;
      }

      .row {
        display: flex !important;
        flex-wrap: wrap !important;
      }

      /* Ensure cards are properly positioned */
      .col-lg-3,
      .col-lg-4,
      .col-lg-5,
      .col-lg-7 {
        flex: 0 0 auto !important;
        width: 25% !important;
        /* Adjust width for a perfect 4-column layout */
      }

      .col-lg-4 {
        width: 33.33% !important;
        /* Ensures 3-column layout remains */
      }

      .col-lg-5 {
        width: 41.66% !important;
        /* For 5-column layout */
      }

      .col-lg-7 {
        width: 58.33% !important;
        /* For 7-column layout */
      }

      /* Hide elements not needed in print */
      button,
      select {
        display: none !important;
      }

      /* Ensure charts are properly printed */
      canvas {
        max-width: 100% !important;
        height: auto !important;
      }
    }


    .card-body {
      background-color: #1d232c;
      color: #e8e3e1;
    }

    .card-title {
      font-weight: bold;
      display: inline;
    }
  </style>

</head>

<body>
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="container">
      <a class="navbar-logo" href="#">
        <img src="images/logo-with-text.png" height="90" style="margin: 3%" />
      </a>
      <span class="navbar-text" href="#" style="text-decoration: underline">Log out</span>
    </div>
  </nav>

  <!-- TABS -->
  <nav class="navbar navbar-expand-lg" style="background-color: #1d232c">
    <div class="container">
      <div class="collapse navbar-collapse">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link" href="#">Overview</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="mapview.html">Map View</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="#">Analytics</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="forecast.html">Forecasts</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Activity Logs</a>
          </li>
        </ul>
        <span class="navbar-text" id="datetime"></span>
      </div>
    </div>
  </nav>

  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center">
      <!-- CONTROLS -->
      <form method="GET">
        <label for="year">Select Year:</label>
        <select name="year" id="year" onchange="this.form.submit()">
          <option value="all" <?= (!isset($_GET['year']) || $_GET['year'] == 'all') ? 'selected' : '' ?>>All Time</option>
          <option value="2019" <?= (isset($_GET['year']) && $_GET['year'] == '2019') ? 'selected' : '' ?>>2019</option>
          <option value="2020" <?= (isset($_GET['year']) && $_GET['year'] == '2020') ? 'selected' : '' ?>>2020</option>
          <option value="2021" <?= (isset($_GET['year']) && $_GET['year'] == '2021') ? 'selected' : '' ?>>2021</option>
          <option value="2022" <?= (isset($_GET['year']) && $_GET['year'] == '2022') ? 'selected' : '' ?>>2022</option>
          <option value="2023" <?= (isset($_GET['year']) && $_GET['year'] == '2023') ? 'selected' : '' ?>>2023</option>
        </select>
      </form>
      <button class="btn btn-primary" onclick="printReport()">Generate Report</button>
    </div>
  </div>

  </div>

  <!-- CONTAINERS -->

  <!-- 1ST ROW OF CARDS -->
  <div class="container mt-4">
    <div class="row">
      <!-- Card 1 / Pending -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Pending Cases</p>
            <?php
            if ($pending_cases > 0) {
              echo '<p class="card-text">' . $pending_cases . '</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
      <!-- Card 2 / Cleared -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Cleared Cases</p>
            <?php
            if ($cleared_cases > 0) {
              echo '<p class="card-text">' . $cleared_cases . '</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
      <!-- Card 3 / Solved -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Solved Cases</p>
            <?php
            if ($solved_cases > 0) {
              echo '<p class="card-text">' . $solved_cases . '</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
      <!-- Card 4 / Total -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Total Cases</p>
            <?php
            if ($total_cases > 0) {
              echo '<p class="card-text">' . $total_cases . '</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 2ND ROW -->
  <div class="container mt-4">
    <div class="row">
      <!-- Card 1 / Volume-->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Crime Volume</p>
            <?php
            // (INDEX + NI)/TOTAL
            if ($crime_volume > 0) {
              echo '<p class="card-text">' . $crime_volume . '%</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
      <!-- Card 2 / Rate -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Crime Rate</p><span> per 100,000</span>
            <?php
            // (TOTAL/POPULATION)*100000
            if ($crime_rate > 0) {
              echo '<p class="card-text">' . $crime_rate . '%</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
      <!-- Card 3 / Clearance -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Crime Clearance Efficiency</p>
            <?php
            // (CLEARED/TOTAL)*100
            if ($crime_clearance > 0) {
              echo '<p class="card-text">' . $crime_clearance . '%</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
      <!-- Card 4 / Solution -->
      <div class="col-lg-3 col-md-6 mb-1">
        <div class="card">
          <div class="card-body">
            <p class="card-title">Crime Solution Efficiency</p>
            <?php
            // (SOLVED/TOTAL)*100
            if ($crime_solution > 0) {
              echo '<p class="card-text">' . $crime_solution . '%</p>';
            } else {
              echo '<p class="card-text">No Data</p>';
            }
            ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 3RD ROW -->
  <div class="container mt-4">
    <div class="row">
      <!-- Card 1 / PEAK TIME -->
      <div class="col-lg-4 col-md-6">
        <div class="card" style="height: 300px;"> <!-- Adjust height for square shape -->
          <div class="card-body">
            <p class="card-title">Peak Time of the Day</p>
            <canvas id="dailyTrend"></canvas>
          </div>
        </div>
      </div>
      <!-- Card 2 / PEAK DAYS -->
      <div class="col-lg-4 col-md-6">
        <div class="card" style="height: 300px;">
          <div class="card-body">
            <p class="card-title">Peak Days of the Week</p>
            <canvas id="weeklyTrend"></canvas>
          </div>
        </div>
      </div>
      <!-- Card 3 / PEAK MONTHS-->
      <div class="col-lg-4 col-md-6">
        <div class="card" style="height: 300px;">
          <div class="card-body">
            <p class="card-title">Peak Months of the Year</p>
            <canvas id="monthlyTrend"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 4TH ROW -->
  <div class="container mt-4">
    <div class="row">
      <!-- PREVALENT OFFENSES -->
      <div class="col-lg-7">
        <div class="card" style="height: 300px;">
          <div class="card-body">
            <p class="card-title">Prevalent Offenses in San Juan</p>
            <canvas id="offensesChart"></canvas>
          </div>
        </div>
      </div>
      <!-- CRIMES AGAINST -->
      <div class="col-lg-5">
        <div class="card" style="height: 300px;">
          <div class="card-body">
            <p class="card-title">Crime Classification</p>
            <canvas id="crimeAgainst"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 5TH ROW -->
  <div class="container mt-4">
    <div class="row">
      <!-- HIGH RISK BRGY -->
      <div class="col-lg-5">
        <div class="card" style="height: 300px;">
          <div class="card-body">
            <p class="card-title">High Risk Barangays</p>
            <ul id="brgyList" style="list-style-type: none; padding-left: 0;"></ul>
          </div>
        </div>
      </div>
      <!-- OFFENSES PER BRGY-->
      <div class="col-lg-7">
        <div class="card" style="height: 300px;">
          <div class="card-body">
            <p class="card-title">Number of Offenses per Barangay</p>
            <!-- CONTROLS -->
            <select id="barangayDropdown">
              <option value="">-- Select Barangay --</option>
              <?php foreach ($barangays as $barangay): ?>
                <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
              <?php endforeach; ?>
            </select>
            <canvas id="offensePerBrgyChart"></canvas>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="footer">
    <p>Disclaimer: Lorem ipsum odor amet, consectetuer adipiscing elit.</p>
  </footer>

  <script>
    // DATE AND TIME
    var dt = new Date();
    var options = {
      timeZone: "Asia/Manila",
      year: "numeric",
      month: "long",
      day: "numeric",
      hour: "2-digit",
      minute: "2-digit",
      hour12: true, // For AM/PM format
    };
    document.getElementById("datetime").innerHTML =
      dt.toLocaleString("en-US", options) + " PHT";

    const offenseData = <?= json_encode($offense_data) ?>;
    let chartInstance;
    // Function to update chart
    function updateChart(barangay) {
      if (!offenseData[barangay]) {
        alert("No data available for this barangay.");
        return;
      }

      let labels = offenseData[barangay].map(entry => entry.offense);
      let counts = offenseData[barangay].map(entry => entry.crime_count);

      if (chartInstance) {
        chartInstance.destroy();
      }

      let ctx = document.getElementById("offensePerBrgyChart").getContext("2d");
      chartInstance = new Chart(ctx, {
        type: "bar",
        data: {
          labels: labels,
          datasets: [{
            label: "Number of Offenses",
            data: counts,
            backgroundColor: "rgba(75, 192, 192, 0.6)",
            borderColor: "rgba(75, 192, 192, 1)",
            borderWidth: 1
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          scales: {
            x: { beginAtZero: true }
          }
        }
      });
    }

    // Handle dropdown change
    document.getElementById("barangayDropdown").addEventListener("change", function () {
      let selectedBarangay = this.value;
      if (selectedBarangay) {
        updateChart(selectedBarangay);
      }
    });


    // HIGH RISK BRGY
    const brgyData = <?php echo $brgy_json; ?>;
    const brgyList = document.getElementById('brgyList');

    brgyData.forEach(item => {
      let listItem = document.createElement("li");
      listItem.textContent = `${item.rank}. ${item.barangay}`;
      brgyList.appendChild(listItem);
    });


    document.addEventListener("DOMContentLoaded", function () {

      // DAILY TREND
      const time = <?php echo $time_json; ?>;
      const dailyCounts = <?php echo $daily_crimes_json; ?>;

      const ctxDaily = document.getElementById('dailyTrend').getContext('2d');
      new Chart(ctxDaily, {
        type: 'line',
        data: {
          labels: time,
          datasets: [{
            label: 'Crime Trends per Day',
            data: dailyCounts,
            borderColor: '#00CFFF',
            backgroundColor: 'rgba(0, 207, 255, 0.3)',
            borderWidth: 3,
            pointBackgroundColor: '#FFB400',
            pointRadius: 5,
            fill: true
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });



      // WEEKLY TREND
      const days = <?php echo $days_json; ?>;
      const weeklyCounts = <?php echo $weekly_crimes_json; ?>;

      const ctxWeekly = document.getElementById('weeklyTrend').getContext('2d');
      new Chart(ctxWeekly, {
        type: 'line',
        data: {
          labels: days,
          datasets: [{
            label: 'Crime Trends per Week',
            data: weeklyCounts,
            borderColor: '#00CFFF',
            backgroundColor: 'rgba(0, 207, 255, 0.3)',
            borderWidth: 3,
            pointBackgroundColor: '#FFB400',
            pointRadius: 5,
            fill: true
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });



      // MONTHLY TREND
      const months = <?php echo $months_json; ?>;
      const monthlyCounts = <?php echo $monthly_crimes_json; ?>;

      const ctxMonthly = document.getElementById('monthlyTrend').getContext('2d');
      new Chart(ctxMonthly, {
        type: 'line',
        data: {
          labels: months,
          datasets: [{
            label: 'Crime Trends per Month',
            data: monthlyCounts,
            borderColor: '#00CFFF',
            backgroundColor: 'rgba(0, 207, 255, 0.3)',
            borderWidth: 3,
            pointBackgroundColor: '#FFB400',
            pointRadius: 5,
            fill: true
          }]
        },
        options: {
          responsive: true,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });



      // CRIME AGAINST PIE CHART
      const crimeAgainst = <?php echo $crime_against_json; ?>;
      const againstCounts = <?php echo $against_counts_json; ?>;

      const ctxAgainst = document.getElementById('crimeAgainst').getContext('2d');
      new Chart(ctxAgainst, {
        type: 'pie',
        data: {
          labels: crimeAgainst,
          datasets: [{
            label: 'Number of Crimes',
            data: againstCounts,
            backgroundColor: [
              'rgba(255, 99, 132, 0.6)',
              'rgba(54, 162, 235, 0.6)',
              'rgba(255, 206, 86, 0.6)'
            ],
            borderColor: [
              'rgba(255, 99, 132, 1)',
              'rgba(54, 162, 235, 1)',
              'rgba(255, 206, 86, 1)'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'top'
            },
            tooltip: {
              callbacks: {
                label: function (tooltipItem) {
                  return crimeAgainst[tooltipItem.dataIndex] + ': ' + againstCounts[tooltipItem.dataIndex];
                }
              }
            },
            datalabels: { // Enable datalabels plugin
              color: '#fff', // Text color
              anchor: 'center', // Position inside the pie slice
              align: 'center',
              font: {
                weight: 'bold',
                size: 14
              },
              formatter: (value) => value // Show the count inside the chart
            }
          }

        },
        plugins: [ChartDataLabels] // Register the datalabels plugin
      });



      // PREVALENT OFFENSES
      const offenses = <?php echo $offense_json; ?>;
      const offenseCounts = <?php echo $offense_counts_json; ?>;

      const ctxOffenses = document.getElementById('offensesChart').getContext('2d');
      new Chart(ctxOffenses, {
        type: 'bar',
        data: {
          labels: offenses,
          datasets: [{
            label: 'Number of Crimes',
            data: offenseCounts,
            backgroundColor: 'rgba(0, 123, 255, 0.6)',
            borderColor: 'rgba(0, 123, 255, 1)',
            borderWidth: 2
          }]
        },
        options: {
          responsive: true,
          indexAxis: 'y',
          scales: {
            x: {
              beginAtZero: true,
              title: {
                display: true,
                text: 'Number of Crimes'
              }
            },
            y: {
              title: {
                display: true,
                text: 'Offense Type'
              }
            }
          }
        }
      });

    });

    function printReport() {
      window.print();
    }

  </script>
</body>

</html>