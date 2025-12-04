<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets Sold by Day</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Cumulative Tickets Sold by Day</h1>
        <div class="chart-container">
            <canvas id="ticketsChart"></canvas>
        </div>
        <div style="margin-top:24px; display:flex; gap:16px; align-items:center;">
            <div style="padding:16px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;">
                <div style="font-size:14px; color:#6b7280;">People signed up for pregames (paid)</div>
                <div style="font-size:28px; font-weight:600; color:#111827;">{{ $totalSignedUp }}</div>
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
</body>
</html>
