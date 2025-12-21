@php
    // Set to true to enable the "Night of Operations" tab
    $enableNightOfOpsTab = false;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 30px;
        }
        /* Tab styles */
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 24px;
            border-bottom: 2px solid #e5e7eb;
        }
        .tab-btn {
            padding: 12px 24px;
            border: none;
            background: none;
            font-size: 16px;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            color: #374151;
            background: #f9fafb;
        }
        .tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        /* Stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            padding: 20px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fafafa;
        }
        .stat-card.highlight {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-color: #93c5fd;
        }
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #111827;
        }
        .stat-card.highlight .stat-value {
            color: #1d4ed8;
        }
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 24px 0;
        }
        /* Progress circles */
        .progress-circle {
            position: relative;
            width: 100px;
            height: 100px;
        }
        .progress-circle svg {
            transform: rotate(-90deg);
            width: 100px;
            height: 100px;
        }
        .progress-circle .bg {
            fill: none;
            stroke: #e5e7eb;
            stroke-width: 8;
        }
        .progress-circle .progress {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 0.5s ease;
        }
        .progress-circle .percent-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }
        .stat-card-with-circle {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stat-card-with-circle .stat-info {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        
        @if($enableNightOfOpsTab)
        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('dashboard')">Dashboard</button>
            <button class="tab-btn" onclick="switchTab('operations')">Night of Operations</button>
        </div>
        @endif

        <!-- Dashboard Tab -->
        <div id="tab-dashboard" class="tab-content{{ $enableNightOfOpsTab ? '' : ' active' }}" style="{{ $enableNightOfOpsTab ? '' : 'display:block;' }}">
            <h2 style="margin-top:0; color:#333;">Cumulative Tickets Sold by Day</h2>
            <div class="chart-container">
            <canvas id="ticketsChart"></canvas>
        </div>
        <div style="margin-top:24px; display:flex; gap:16px; align-items:flex-start;">
            <div style="padding:16px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;">
                <div style="font-size:14px; color:#6b7280;">People signed up for pregames (paid)</div>
                <div style="font-size:28px; font-weight:600; color:#111827;">{{ $totalSignedUp }}</div>
            </div>
            <div style="width:260px; height:200px; padding:12px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa; display:flex; flex-direction:column; align-items:center; justify-content:flex-start;">
                <div style="font-size:14px; color:#6b7280; margin-bottom:8px;">Attendee Gender</div>
                <div style="width:160px; height:160px; display:flex; align-items:center; justify-content:center;">
                    <canvas id="genderPie" style="max-width:100%; max-height:100%;"></canvas>
                </div>
                <div style="font-size:12px; color:#6b7280; margin-top:8px; display:flex; gap:8px; justify-content:space-between; width:100%;">
                    <div>Male: <strong>{{ $genderCounts['male'] }}</strong></div>
                    <div>Female: <strong>{{ $genderCounts['female'] }}</strong></div>
                    <div>Unknown: <strong>{{ $genderCounts['unknown'] }}</strong></div>
                </div>
            </div>
            <div style="flex:1; padding:16px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;">
                <div style="font-size:14px; color:#6b7280; margin-bottom:8px;">Breakdown per pregame (paid)</div>
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:8px; border-bottom:1px solid #e5e7eb; color:#6b7280; font-weight:500;">Pregame</th>
                            <th style="text-align:right; padding:8px; border-bottom:1px solid #e5e7eb; color:#6b7280; font-weight:500;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pregameBreakdown as $row)
                            <tr>
                                <td style="padding:8px; border-bottom:1px solid #f3f4f6;">{{ $row['name'] }}</td>
                                <td style="padding:8px; border-bottom:1px solid #f3f4f6; text-align:right; font-weight:600;">{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" style="padding:12px; text-align:center; color:#6b7280;">No paid signups yet</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        </div><!-- end tab-dashboard -->

        @if($enableNightOfOpsTab)
        <!-- Night of Operations Tab -->
        <div id="tab-operations" class="tab-content">
            <h2 style="margin-top:0; color:#333;">Night of Operations</h2>
            
            <!-- Raw Numbers with Progress Circles -->
            @php
                $admittedPct = $ticketsSold > 0 ? round(($ticketsAdmitted / $ticketsSold) * 100) : 0;
                $outstandingPct = $ticketsSold > 0 ? round(($ticketsOutstanding / $ticketsSold) * 100) : 0;
                $before1005Pct = $ticketsAdmitted > 0 ? round(($scansBefore1005 / $ticketsAdmitted) * 100) : 0;
                $after1005Pct = $ticketsAdmitted > 0 ? round(($scansAfter1005 / $ticketsAdmitted) * 100) : 0;
            @endphp

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label"># of Tickets Sold</div>
                    <div class="stat-value">{{ $ticketsSold }}</div>
                </div>
                <div class="stat-card highlight stat-card-with-circle">
                    <div class="progress-circle">
                        <svg viewBox="0 0 100 100">
                            <circle class="bg" cx="50" cy="50" r="42"></circle>
                            <circle class="progress" cx="50" cy="50" r="42" 
                                stroke="#2563eb" 
                                stroke-dasharray="264" 
                                stroke-dashoffset="{{ 264 - (264 * $admittedPct / 100) }}"></circle>
                        </svg>
                        <div class="percent-text" style="color:#1d4ed8;">{{ $admittedPct }}%</div>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label"># of Tickets Admitted</div>
                        <div class="stat-value">{{ $ticketsAdmitted }}</div>
                    </div>
                </div>
                <div class="stat-card stat-card-with-circle">
                    <div class="progress-circle">
                        <svg viewBox="0 0 100 100">
                            <circle class="bg" cx="50" cy="50" r="42"></circle>
                            <circle class="progress" cx="50" cy="50" r="42" 
                                stroke="#f59e0b" 
                                stroke-dasharray="264" 
                                stroke-dashoffset="{{ 264 - (264 * $outstandingPct / 100) }}"></circle>
                        </svg>
                        <div class="percent-text" style="color:#d97706;">{{ $outstandingPct }}%</div>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label"># of Tickets Outstanding</div>
                        <div class="stat-value">{{ $ticketsOutstanding }}</div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
                <div class="stat-card stat-card-with-circle">
                    <div class="progress-circle">
                        <svg viewBox="0 0 100 100">
                            <circle class="bg" cx="50" cy="50" r="42"></circle>
                            <circle class="progress" cx="50" cy="50" r="42" 
                                stroke="#10b981" 
                                stroke-dasharray="264" 
                                stroke-dashoffset="{{ 264 - (264 * $before1005Pct / 100) }}"></circle>
                        </svg>
                        <div class="percent-text" style="color:#059669;">{{ $before1005Pct }}%</div>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label"># Scanned Before 10:05 PM</div>
                        <div class="stat-value">{{ $scansBefore1005 }}</div>
                    </div>
                </div>
                <div class="stat-card stat-card-with-circle">
                    <div class="progress-circle">
                        <svg viewBox="0 0 100 100">
                            <circle class="bg" cx="50" cy="50" r="42"></circle>
                            <circle class="progress" cx="50" cy="50" r="42" 
                                stroke="#8b5cf6" 
                                stroke-dasharray="264" 
                                stroke-dashoffset="{{ 264 - (264 * $after1005Pct / 100) }}"></circle>
                        </svg>
                        <div class="percent-text" style="color:#7c3aed;">{{ $after1005Pct }}%</div>
                    </div>
                    <div class="stat-info">
                        <div class="stat-label"># Scanned After 10:05 PM</div>
                        <div class="stat-value">{{ $scansAfter1005 }}</div>
                    </div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Bar Chart: Scans by 15-min increment -->
            <h3 style="color:#374151; margin-bottom:16px;">Tickets Scanned by 15-Minute Interval</h3>
            <div class="chart-container" style="height:350px;">
                <canvas id="scansChart"></canvas>
            </div>

            <div class="divider"></div>

            <!-- Gender Breakdown of Scanned Tickets -->
            <div style="display:flex; gap:24px; align-items:flex-start;">
                <div style="width:280px; padding:16px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa; display:flex; flex-direction:column; align-items:center;">
                    <div style="font-size:14px; color:#6b7280; margin-bottom:12px;">Gender of Admitted Attendees</div>
                    <div style="width:180px; height:180px; display:flex; align-items:center; justify-content:center;">
                        <canvas id="scannedGenderPie" style="max-width:100%; max-height:100%;"></canvas>
                    </div>
                    <div style="font-size:12px; color:#6b7280; margin-top:12px; display:flex; gap:12px; justify-content:center; width:100%;">
                        <div><span style="display:inline-block;width:10px;height:10px;background:#3b82f6;border-radius:2px;margin-right:4px;"></span>Male: <strong>{{ $scannedGenderCounts['male'] }}</strong></div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#ec4899;border-radius:2px;margin-right:4px;"></span>Female: <strong>{{ $scannedGenderCounts['female'] }}</strong></div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#9ca3af;border-radius:2px;margin-right:4px;"></span>Unknown: <strong>{{ $scannedGenderCounts['unknown'] }}</strong></div>
                    </div>
                </div>
            </div>
        </div><!-- end tab-operations -->
        @endif
    </div>

    <script>
        const ctx = document.getElementById('ticketsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($labels),
                datasets: [
                    {
                        label: 'Current Year (cumulative)',
                        data: @json($currentCounts),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: 'rgb(75, 192, 192)',
                    },
                    {
                        label: '2024 (cumulative)',
                        data: @json($historicalCounts),
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: 'rgb(255, 99, 132)',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    title: {
                        display: false,
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Cumulative Tickets Sold'
                        },
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
        <script>
            // Gender pie chart
            const genderCtx = document.getElementById('genderPie').getContext('2d');
            new Chart(genderCtx, {
                type: 'pie',
                data: {
                    labels: ['Male', 'Female', 'Unknown'],
                    datasets: [{
                        data: [{{ $genderCounts['male'] }}, {{ $genderCounts['female'] }}, {{ $genderCounts['unknown'] }}],
                        backgroundColor: ['#3b82f6', '#ec4899', '#9ca3af'],
                        borderColor: '#ffffff',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        </script>

        @if($enableNightOfOpsTab)
        <!-- Tab switching and Scans chart -->
        <script>
            // Tab switching
            function switchTab(tabName) {
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
                
                // Show selected tab
                document.getElementById('tab-' + tabName).classList.add('active');
                event.target.classList.add('active');

                // Initialize scans chart when operations tab is shown (lazy load)
                if (tabName === 'operations' && !window.scansChartInitialized) {
                    initScansChart();
                    initScannedGenderPie();
                    window.scansChartInitialized = true;
                }
            }

            // Scans by 15-min interval bar chart
            function initScansChart() {
                const scansCtx = document.getElementById('scansChart').getContext('2d');
                new Chart(scansCtx, {
                    type: 'bar',
                    data: {
                        labels: @json($intervalLabels),
                        datasets: [{
                            label: 'Tickets Scanned',
                            data: @json($intervalCounts),
                            backgroundColor: 'rgba(37, 99, 235, 0.7)',
                            borderColor: 'rgb(37, 99, 235)',
                            borderWidth: 1,
                            borderRadius: 4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function(context) {
                                        return 'Time: ' + context[0].label;
                                    },
                                    label: function(context) {
                                        return context.raw + ' ticket' + (context.raw !== 1 ? 's' : '') + ' scanned';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Time (15-min intervals)'
                                },
                                grid: { display: false }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Tickets Scanned'
                                },
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1,
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            }

            // Scanned gender pie chart
            function initScannedGenderPie() {
                const scannedGenderCtx = document.getElementById('scannedGenderPie').getContext('2d');
                new Chart(scannedGenderCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Male', 'Female', 'Unknown'],
                        datasets: [{
                            data: [{{ $scannedGenderCounts['male'] }}, {{ $scannedGenderCounts['female'] }}, {{ $scannedGenderCounts['unknown'] }}],
                            backgroundColor: ['#3b82f6', '#ec4899', '#9ca3af'],
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const pct = total > 0 ? Math.round((context.raw / total) * 100) : 0;
                                        return context.label + ': ' + context.raw + ' (' + pct + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        </script>
        @endif
</body>
</html>
