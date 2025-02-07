<?php
include('connect.php');

// Get the selected year
$year = isset($_GET['year']) ? $_GET['year'] : 'all';
// Condition for SQL queries
$year_condition = ($year === 'all') ? "" : "YEAR(`dateCommitted`) = $year";

// PENDING CASES
$pending_query = "SELECT COUNT(*) AS count FROM crimemapping WHERE"
  . (!empty($year_condition) ? " $year_condition AND" : "")
  . " `caseStatus` = 'Under Inve'";
$pending_result = $conn->query($pending_query);
$pending_cases = ($pending_result && $row = $pending_result->fetch_assoc()) ? (int) $row['count'] : 0;

// CLEARED CASES
$cleared_query = "SELECT COUNT(*) AS count FROM crimemapping WHERE"
  . (!empty($year_condition) ? "  $year_condition AND" : "")
  . " `caseStatus` = 'Cleared'";
$cleared_result = $conn->query($cleared_query);
$cleared_cases = ($cleared_result && $row = $cleared_result->fetch_assoc()) ? (int) $row['count'] : 0;

// SOLVED CASES
$solved_query = "SELECT COUNT(*) AS count FROM crimemapping WHERE"
  . (!empty($year_condition) ? " $year_condition AND" : "")
  . " `caseStatus` = 'Solved'";
$solved_result = $conn->query($solved_query);
$solved_cases = ($solved_result && $row = $solved_result->fetch_assoc()) ? (int) $row['count'] : 0;

// TOTAL CASES
$total_query = "SELECT COUNT(*) AS count FROM crimemapping"
  . (!empty($year_condition) ? " WHERE $year_condition" : "");
$total_result = $conn->query($total_query);
$total_cases = ($total_result && $row = $total_result->fetch_assoc()) ? (int) $row['count'] : 0;

// CRIME VOLUME = (INDEX + NI)/TOTAL
$volume_query = "SELECT (SUM(CASE WHEN `crimeClassification` = 'index' THEN 1 ELSE 0 END) + 
                  SUM(CASE WHEN `crimeClassification` = 'non-index' THEN 1 ELSE 0 END)) / 
                  COUNT(*) * 100 AS count FROM crimemapping"
  . (!empty($year_condition) ? " WHERE $year_condition" : "");
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
                FROM crimemapping"
  . (!empty($year_condition) ? " WHERE $year_condition" : "");
$daily_query .= " GROUP BY `timeCommitted`) ranked_time WHERE rank <= 10 ORDER BY crime_count DESC";
$daily_result = $conn->query($daily_query);
$peakTimes = [];
$dailyTrends = [];
foreach ($daily_result as $row) {
  $peakTimes[] = $row['time'];
  $dailyTrends[] = $row['crime_count'];
}
$peakTimes_json = json_encode($peakTimes);
$dailyTrends_json = json_encode($dailyTrends);

// WEEKLY TREND
$weekly_query = "SELECT day, crime_count, rank FROM (
                SELECT DAYNAME(`dateCommitted`) AS day, 
                COUNT(*) AS crime_count, 
                RANK() OVER (ORDER BY COUNT(*) DESC) 
                AS rank FROM crimemapping"
  . (!empty($year_condition) ? " WHERE $year_condition" : "")
  . " GROUP BY DAYNAME(`dateCommitted`) ) ranked_days WHERE rank <= 7 ORDER BY crime_count DESC";
$weekly_result = $conn->query($weekly_query);
$weekly_trend = $weekly_result->fetch_assoc();
$peakDays = [];
$weeklyTrends = [];
foreach ($weekly_result as $row) {
  $peakDays[] = $row['day'];
  $weeklyTrends[] = $row['crime_count'];
}
$peakDays_json = json_encode($peakDays);
$weeklyTrends_json = json_encode($weeklyTrends);

// MONTHLY TREND
$monthly_query = "SELECT month, crime_count, rank FROM (
                  SELECT MONTHNAME(`dateCommitted`) AS month, 
                  COUNT(*) AS crime_count, 
                  RANK() OVER (ORDER BY COUNT(*) DESC) 
                  AS rank FROM crimemapping"
  . (!empty($year_condition) ? " WHERE $year_condition" : "")
  . " GROUP BY MONTHNAME(`dateCommitted`) ) ranked_months 
                    WHERE rank <= 12 ORDER BY crime_count DESC";
$monthly_result = $conn->query($monthly_query);
$monthly_trend = $monthly_result->fetch_assoc();
$peakMonths = [];
$monthlyTrends = [];
foreach ($monthly_result as $row) {
  $peakMonths[] = $row['month'];
  $monthlyTrends[] = $row['crime_count'];
}
$peakMonths_json = json_encode($peakMonths);
$monthlyTrends_json = json_encode($monthlyTrends);

// PREVALENT INCIDENT TYPE
$incident_query = "SELECT incidentType, COUNT(*) AS crime_count FROM crimemapping"
  . (!empty($year_condition) ? " WHERE $year_condition" : "")
  . " GROUP BY incidentType ORDER BY crime_count DESC";
$incident_result = $conn->query($incident_query);
$incidentType = [];
$incidentCount = [];
while ($row = $incident_result->fetch_assoc()) {
  $incidentType[] = $row['incidentType'];
  $incidentCount[] = $row['crime_count'];
}
$incidentType_json = json_encode($incidentType);
$incidentCount_json = json_encode($incidentCount);

// CRIMES AGAINST CLASSIFICATION
$against_query = "SELECT `crimeAgainst`, COUNT(*) AS crime_count FROM crimemapping WHERE"
                . (!empty($year_condition) ? " $year_condition AND" : "")
                . " `crimeAgainst` IN ('crimes against person', 'crimes against property', 'special laws')
                  GROUP BY `crimeAgainst`
                  ORDER BY crime_count DESC";
$against_result = $conn->query($against_query);
$crimeAgainst = [];
$againstCounts = [];
while ($row = $against_result->fetch_assoc()) {
  $crimeAgainst[] = $row['crimeAgainst'];
  $againstCounts[] = $row['crime_count'];
}
$crimeAgainst_json = json_encode($crimeAgainst);
$againstCounts_json = json_encode($againstCounts);

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

// OFFENSES PER BRGY
$barangays_query = "SELECT DISTINCT BARANGAY FROM crimemapping";
$barangays_result = $conn->query($barangays_query);
$barangays = [];
while ($row = $barangays_result->fetch_assoc()) {
  $barangays[] = $row['BARANGAY'];
}

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

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>San Juan Analytics</title>
  <link rel="icon" type="image/png" href="images/logo-square.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins&family=Inter&display=swap" rel="stylesheet" />
  
  <style>
    :root {
      --background: #0f172a;
      --card-bg: #1e293b;
      --primary-text: #e2e8f0;
      --secondary-text: #94a3b8;
      --accent: #38bdf8;
      --chart-1: #38bdf8;
      --chart-2: #f59e0b;
      --chart-3: #ef4444;
    }

    body {
      background-color: var(--background);
      color: var(--primary-text);
      font-family: 'Inter', sans-serif;
      margin: 0;
      padding: 0;
    }

    .navbar {
      background-color: var(--background);
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      padding: 0.5rem 0;
    }

    .navbar-logo img {
      height: 50px;
    }

    .nav-link {
      color: var(--secondary-text);
      font-size: 0.9rem;
      padding: 0.5rem 1rem;
      transition: color 0.3s;
    }

    .nav-link:hover,
    .nav-link.active {
      color: var(--accent);
    }

    .card {
      background-color: var(--card-bg);
      border: none;
      border-radius: 0.5rem;
      margin-bottom: 1rem;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .card-body {
      padding: 1.25rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .card-title {
      color: var(--secondary-text);
      font-size: 0.875rem;
      font-weight: 500;
      margin-bottom: 0.5rem;
    }

    .card-text {
      color: var(--accent);
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0;
    }

    select {
      background-color: #ffffff;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 0.375rem;
      color: #000000;
      font-size: 0.875rem;
      padding: 0.5rem;
    }

    select option {
      background-color: #ffffff;
      color: #000000;
    }

    .btn-primary {
      background-color: var(--accent);
      border: none;
      border-radius: 0.375rem;
      font-size: 0.875rem;
      padding: 0.5rem 1rem;
    }

    .btn-primary:hover {
      background-color: #0ea5e9;
    }

    .chart-container {
      position: relative;
      height: 300px !important;
      width: 100%;
      margin: 0 auto;
      padding: 1rem;
    }

    canvas {
      max-width: 100%;
    }

    #brgyList {
      color: var(--primary-text);
      font-size: 0.875rem;
      list-style: none;
      padding: 1rem;
      margin: 0;
      height: 300px;
      overflow-y: auto;
    }

    #brgyList li {
      padding: 0.25rem 0;
    }

    footer {
      background-color: var(--card-bg);
      color: var(--secondary-text);
      font-size: 0.75rem;
      padding: 1rem 0;
      text-align: center;
      position: relative;
      bottom: 0;
      width: 100%;
      margin-top: 2rem;
    }

    #crimeAgainst {
      height: 300px !important;
      width: 100% !important;
      margin: 0 auto;
    }

    .row {
      margin-bottom: 1.5rem;
      display: flex;
      align-items: stretch;
    }

    .col-lg-5, .col-lg-7 {
      display: flex;
      flex-direction: column;
    }

    .container {
      padding-top: 2rem;
      padding-bottom: Here's the continuation of the text stream from the cut-off point:

column;
    }

    .container {
      padding-top: 2rem;
      padding-bottom: 2rem;
    }

    .card-title {
      margin-bottom: 1.5rem;
      font-size: 1rem;
      font-weight: 500;
      color: var(--secondary-text);
    }

    @media print {
      .navbar, select, .btn-primary {
        display: none !important;
      }

      body {
        background-color: white !important;
      }

      .card {
        break-inside: avoid;
      }
    }
  </style>
</head>

<body>
  <nav class="navbar">
    <div class="container">
      <a class="navbar-logo" href="#">
        <img src="images/logo-with-text.png" alt="Bantay Alisto Logo" />
      </a>
      <span class="navbar-text" style="color: var(--accent)">Log out</span>
    </div>
  </nav>

  <nav class="navbar navbar-expand-lg">
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
            <a class="nav-link active" href="#" style="color: var(--accent);">Analytics</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="forecast.html">Forecasts</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#">Activity Logs</a>
          </li>
        </ul>
        <span class="navbar-text" id="datetime" style="color: var(--secondary-text)"></span>
      </div>
    </div>
  </nav>

  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <form method="GET" class="d-flex align-items-center gap-2">
        <label for="year" style="color: var(--secondary-text)">Select Year:</label>
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

    <div class="row">
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Pending Cases</h6>
            <p class="card-text"><?= $pending_cases ?: 'No Data' ?></p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Cleared Cases</h6>
            <p class="card-text"><?= $cleared_cases ?: 'No Data' ?></p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Solved Cases</h6>
            <p class="card-text"><?= $solved_cases ?: 'No Data' ?></p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Total Cases</h6>
            <p class="card-text"><?= $total_cases ?: 'No Data' ?></p>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Crime Volume</h6>
            <p class="card-text"><?= $crime_volume ?>%</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Crime Rate <span style="font-size: 0.75rem">(per 100,000)</span></h6>
            <p class="card-text"><?= $crime_rate ?>%</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Crime Clearance Efficiency</h6>
            <p class="card-text"><?= $crime_clearance ?>%</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-md-6">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Crime Solution Efficiency</h6>
            <p class="card-text"><?= $crime_solution ?>%</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Peak Hours of the Day</h6>
            <div class="chart-container">
              <canvas id="dailyTrend"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Peak Days of the Week</h6>
            <div class="chart-container">
              <canvas id="weeklyTrend"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Peak Months of the Year</h6>
            <div class="chart-container">
              <canvas id="monthlyTrend"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-lg-7">
        <div class="card" style="height: 400px;">
          <div class="card-body">
            <h6 class="card-title">Prevalent Offenses in San Juan</h6>
            <div class="chart-container">
              <canvas id="incidentChart"></canvas>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Crime Classification</h6>
            <canvas id="crimeAgainst"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="row mt-4">
      <div class="col-lg-5">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">High Risk Barangays</h6>
            <ul id="brgyList"></ul>
          </div>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Number of Offenses per Barangay</h6>
            <select id="barangayDropdown" class="mb-3">
              <option value="">-- Select Barangay --</option>
              <?php foreach ($barangays as $barangay): ?>
                <option value="<?= htmlspecialchars($barangay) ?>"><?= htmlspecialchars($barangay) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="chart-container">
              <canvas id="offensePerBrgyChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <footer>
    <p>©Bantay Alisto Crime Mapping 2025. All Rights Reserved.</p>
  </footer>

  <script>
    // DateTime
    function updateDateTime() {
      const dt = new Date();
      const options = {
        timeZone: "Asia/Manila",
        year: "numeric",
        month: "long",
        day: "numeric",
        hour: "2-digit",
        minute: "2-digit",
        hour12: true
      };
      document.getElementById("datetime").innerHTML = dt.toLocaleString("en-US", options) + " PHT";
    }
    updateDateTime();
    setInterval(updateDateTime, 60000);

    // Chart Configuration
    Chart.defaults.color = '#94a3b8';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
    Chart.defaults.font.family = 'Inter, sans-serif';

    const chartOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }
      },
      scales: {
        y: { 
          beginAtZero: true,
          grid: {
            color: 'rgba(255, 255, 255, 0.1)'
          }
        },
        x: {
          grid: {
            color: 'rgba(255, 255, 255, 0.1)'
          }
        }
      }
    };

    // Daily Trend
    new Chart(document.getElementById('dailyTrend').getContext('2d'), {
      type: 'line',
      data: {
        labels: <?= $peakTimes_json ?>,
        datasets: [{
          label: 'Crimes',
          data: <?= $dailyTrends_json ?>,
          borderColor: '#38bdf8',
          backgroundColor: 'rgba(56, 189, 248, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: chartOptions
    });

    // Weekly Trend
    new Chart(document.getElementById('weeklyTrend').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?= $peakDays_json ?>,
        datasets: [{
          label: 'Crimes',
          data: <?= $weeklyTrends_json ?>,
          backgroundColor: '#38bdf8',
          borderRadius: 4
        }]
      },
      options: chartOptions
    });

    // Monthly Trend
    new Chart(document.getElementById('monthlyTrend').getContext('2d'), {
      type: 'line',
      data: {
        labels: <?= $peakMonths_json ?>,
        datasets: [{
          label: 'Crimes',
          data: <?= $monthlyTrends_json ?>,
          borderColor: '#38bdf8',
          backgroundColor: 'rgba(56, 189, 248, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: chartOptions
    });

    // Incident Chart
    new Chart(document.getElementById('incidentChart').getContext('2d'), {
      type: 'bar',
      data: {
        labels: <?= $incidentType_json ?>,
        datasets: [{
          label: 'Number of Crimes',
          data: <?= $incidentCount_json ?>,
          backgroundColor: '#38bdf8',
          borderRadius: 4
        }]
      },
      options: {
        ...chartOptions,
        indexAxis: 'y',
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: {
              color: 'rgba(255, 255, 255, 0.1)'
            }
          },
          y: {
            grid: {
              display: false
            }
          }
        },
        layout: {
          padding: {
            left: 10,
            right: 10
          }
        }
      }
    });

    // Crime Against Chart
    new Chart(document.getElementById('crimeAgainst').getContext('2d'), {
      type: 'doughnut',
      data: {
        labels: <?= $crimeAgainst_json ?>,
        datasets: [{
          data: <?= $againstCounts_json ?>,
          backgroundColor: ['#38bdf8', '#f59e0b', '#ef4444']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20
            }
          }
        },
        layout: {
          padding: {
            top: 20,
            bottom: 20
          }
        }
      }
    });

    // High Risk Barangays
    const brgyData = <?= $brgy_json ?>;
    const brgyList = document.getElementById('brgyList');
    brgyData.forEach(item => {
      const li = document.createElement('li');
      li.textContent = `${item.rank}. ${item.barangay}`;
      brgyList.appendChild(li);
    });

    // Offenses per Barangay
    const offenseData = <?= json_encode($offense_data) ?>;
    let offenseChart;

    document.getElementById('barangayDropdown').addEventListener('change', function() {
      const barangay = this.value;
      if (!barangay) return;

      const data = offenseData[barangay];
      if (!data) {
        alert('No data available for this barangay');
        return;
      }

      if (offenseChart) {
        offenseChart.destroy();
      }

      offenseChart = new Chart(document.getElementById('offensePerBrgyChart').getContext('2d'), {
        type: 'bar',
        data: {
          labels: data.map(d => d.offense),
          datasets: [{
            label: 'Number of Crimes',
            data: data.map(d => d.crime_count),
            backgroundColor: '#38bdf8',
            borderRadius: 4
          }]
        },
        options: {
          ...chartOptions,
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: {
              beginAtZero: true,
              grid: {
                color: 'rgba(255, 255, 255, 0.1)'
              }
            },
            y: {
              grid: {
                display: false
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
