<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Eventbrite Orders</title>
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
        select {
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
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2196f3;
            margin-bottom: 20px;
        }
        .success {
            background-color: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #4caf50;
            margin-bottom: 20px;
            color: #2e7d32;
        }
        .error {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #d32f2f;
            margin-bottom: 20px;
        }
        .output {
            background-color: #263238;
            color: #aed581;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
            max-height: 500px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            font-size: 13px;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #f05537;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Sync Eventbrite Orders</h1>
        
        <div class="info">
            <strong>What this does:</strong> Fetches all orders from Eventbrite for the selected event and stores them in your local database. This is useful for importing historical orders that were placed before the webhook was set up.
        </div>

        @if(isset($success))
            <div class="success">
                ✓ {{ $success }}
            </div>
        @endif

        @if(isset($error))
            <div class="error">
                ✗ {{ $error }}
            </div>
        @endif

        <form id="syncForm">
            <div class="form-group">
                <label for="pregame_id">Select PreGame to Sync:</label>
                <select name="pregame_id" id="pregame_id" required>
                    <option value="">-- Select a PreGame --</option>
                    <option value="all">🌐 Sync All PreGames</option>
                    @foreach($pregames as $pregame)
                        <option value="{{ $pregame->id }}">
                            {{ $pregame->name }} (Event ID: {{ $pregame->eventbrite_event_id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" id="syncBtn">Sync Orders</button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Syncing orders... This may take a few minutes.</p>
        </div>

        <div id="outputContainer" style="display: none;">
            <div class="output" id="output"></div>
        </div>
    </div>

    <script>
        document.getElementById('syncForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const pregameId = document.getElementById('pregame_id').value;
            const syncBtn = document.getElementById('syncBtn');
            const loading = document.getElementById('loading');
            const outputContainer = document.getElementById('outputContainer');
            const output = document.getElementById('output');
            
            if (!pregameId) {
                alert('Please select a PreGame');
                return;
            }
            
            syncBtn.disabled = true;
            syncBtn.textContent = 'Syncing...';
            loading.style.display = 'block';
            outputContainer.style.display = 'none';
            
            fetch('{{ route("eventbrite.sync.run") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    pregame_id: pregameId
                })
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error('Server returned ' + response.status + ': ' + text.substring(0, 200));
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response was not JSON:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                loading.style.display = 'none';
                outputContainer.style.display = 'block';
                
                if (data.success) {
                    output.textContent = data.output || 'Sync completed successfully!';
                    syncBtn.textContent = 'Sync Orders';
                    syncBtn.disabled = false;
                } else {
                    output.textContent = 'Error: ' + data.message;
                    syncBtn.textContent = 'Try Again';
                    syncBtn.disabled = false;
                }
            })
            .catch(error => {
                loading.style.display = 'none';
                outputContainer.style.display = 'block';
                output.textContent = 'Error: ' + error.message;
                syncBtn.textContent = 'Try Again';
                syncBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
