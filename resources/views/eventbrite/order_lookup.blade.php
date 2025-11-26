<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventbrite Order Lookup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background-color: #f05537;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #d64426;
        }
        .result {
            margin-top: 30px;
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 600px;
            overflow-y: auto;
        }
        .error {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #d32f2f;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background-color: #263238;
            color: #aed581;
            padding: 15px;
            border-radius: 4px;
            font-size: 13px;
            line-height: 1.5;
        }
        .info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }
        th {
            background-color: #f05537;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f0f0f0;
        }
        .key {
            font-weight: bold;
            color: #555;
            width: 30%;
        }
        .value {
            font-family: 'Courier New', monospace;
            color: #333;
            word-break: break-word;
        }
        .nested-table {
            margin: 5px 0;
            font-size: 0.9em;
        }
        .nested-table td {
            padding: 5px 8px;
        }
        .section-header {
            background-color: #e3f2fd !important;
            font-weight: bold;
            color: #1976d2;
        }
        .toggle-json {
            background-color: #2196f3;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
        }
        .toggle-json:hover {
            background-color: #1976d2;
        }
        .json-view {
            display: none;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎟️ Eventbrite Order Lookup</h1>
        
        <div class="info">
            <strong>Tip:</strong> Enter an Eventbrite Order ID to fetch the full JSON data from the Eventbrite API.
        </div>

        <form method="GET" action="{{ route('eventbrite.order.lookup') }}">
            <div class="form-group">
                <label for="order_id">Order ID:</label>
                <input 
                    type="text" 
                    id="order_id" 
                    name="order_id" 
                    value="{{ request('order_id') }}" 
                    placeholder="e.g., 1234567890"
                    required
                >
            </div>
            <button type="submit">Fetch Order Data</button>
        </form>

        @if(isset($error))
            <div class="result">
                <div class="error">
                    <strong>Error:</strong> {{ $error }}
                </div>
            </div>
        @endif

        @if(isset($orderData))
            <div class="result">
                <h3>Order Details:</h3>
                
                <table>
                    <tr class="section-header">
                        <td colspan="2">Basic Information</td>
                    </tr>
                    <tr>
                        <td class="key">Order ID</td>
                        <td class="value">{{ $orderData['id'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Event ID</td>
                        <td class="value">{{ $orderData['event_id'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Created</td>
                        <td class="value">{{ $orderData['created'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Changed</td>
                        <td class="value">{{ $orderData['changed'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Name</td>
                        <td class="value">{{ $orderData['name'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">First Name</td>
                        <td class="value">{{ $orderData['first_name'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Last Name</td>
                        <td class="value">{{ $orderData['last_name'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Email</td>
                        <td class="value">{{ $orderData['email'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Status</td>
                        <td class="value">{{ $orderData['status'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Time Remaining</td>
                        <td class="value">{{ $orderData['time_remaining'] ?? 'N/A' }}</td>
                    </tr>
                    
                    @if(isset($orderData['costs']))
                    <tr class="section-header">
                        <td colspan="2">Costs</td>
                    </tr>
                    <tr>
                        <td class="key">Base Price</td>
                        <td class="value">{{ $orderData['costs']['base_price']['display'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Eventbrite Fee</td>
                        <td class="value">{{ $orderData['costs']['eventbrite_fee']['display'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Gross</td>
                        <td class="value">{{ $orderData['costs']['gross']['display'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Payment Fee</td>
                        <td class="value">{{ $orderData['costs']['payment_fee']['display'] ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="key">Tax</td>
                        <td class="value">{{ $orderData['costs']['tax']['display'] ?? 'N/A' }}</td>
                    </tr>
                    @endif

                    @if(isset($orderData['attendees']) && count($orderData['attendees']) > 0)
                    <tr class="section-header">
                        <td colspan="2">Attendees ({{ count($orderData['attendees']) }})</td>
                    </tr>
                    @foreach($orderData['attendees'] as $index => $attendee)
                    <tr>
                        <td class="key">Attendee #{{ $index + 1 }}</td>
                        <td class="value">
                            <table class="nested-table">
                                <tr>
                                    <td class="key">ID:</td>
                                    <td class="value">{{ $attendee['id'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Name:</td>
                                    <td class="value">{{ $attendee['profile']['name'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Email:</td>
                                    <td class="value">{{ $attendee['profile']['email'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Ticket Class ID:</td>
                                    <td class="value">{{ $attendee['ticket_class_id'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Ticket Class Name:</td>
                                    <td class="value">{{ $attendee['ticket_class_name'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Status:</td>
                                    <td class="value">{{ $attendee['status'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Checked In:</td>
                                    <td class="value">{{ isset($attendee['checked_in']) ? ($attendee['checked_in'] ? 'Yes' : 'No') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Cancelled:</td>
                                    <td class="value">{{ isset($attendee['cancelled']) ? ($attendee['cancelled'] ? 'Yes' : 'No') : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td class="key">Refunded:</td>
                                    <td class="value">{{ isset($attendee['refunded']) ? ($attendee['refunded'] ? 'Yes' : 'No') : 'N/A' }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </table>

                <button class="toggle-json" onclick="document.getElementById('jsonView').style.display = document.getElementById('jsonView').style.display === 'none' ? 'block' : 'none'">
                    Toggle Raw JSON
                </button>
                
                <div id="jsonView" class="json-view">
                    <h3>Raw JSON Response:</h3>
                    <pre>{{ json_encode($orderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
