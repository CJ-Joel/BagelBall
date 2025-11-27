@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 text-white py-8">
    <div class="max-w-md mx-auto">
        <h1 class="text-2xl font-bold mb-4">Sign Up for {{ $pregame->name }}</h1>
        
        @if(session('error'))
            <div class="bg-red-600 text-white p-4 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('pregames.signup.submit', $pregame, false) }}" class="space-y-4" id="signupForm">
            @csrf
            <!-- DEBUGGING: Show Session ID and CSRF Token -->
<div class="bg-yellow-500 text-black p-2 rounded">
    <p><strong>DEBUG SESSION ID:</strong> {{ session()->getId() }}</p>
    <p><strong>DEBUG CSRF TOKEN:</strong> {{ csrf_token() }}</p>
</div>
            <!-- Step 1: Eventbrite Order ID -->
            <div class="bg-gray-800 p-4 rounded">
                <label class="block mb-2 font-semibold">Eventbrite Order ID</label>
                <input 
                    type="text" 
                    name="eventbrite_order_id" 
                    id="orderIdInput"
                    class="w-full px-3 py-2 rounded bg-gray-700 text-white border border-gray-600" 
                    placeholder="e.g., 13813580483"
                    required>
                <button 
                    type="button" 
                    id="validateBtn"
                    class="mt-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold w-full">
                    Validate Order
                </button>
                <div id="validationMessage" class="mt-2 text-sm"></div>
            </div>

            <!-- Step 2: User Information (hidden until validated) -->
            <div id="userInfoSection" style="display: none;">
                <div class="bg-gray-800 p-4 rounded space-y-4">
                    <h3 class="font-semibold text-lg mb-2">Your Information</h3>
                    <div>
                        <label class="block mb-1">First Name</label>
                        <input type="text" name="first_name" id="firstName" class="w-full px-3 py-2 rounded bg-gray-700 text-white" required>
                    </div>
                    <div>
                        <label class="block mb-1">Last Name</label>
                        <input type="text" name="last_name" id="lastName" class="w-full px-3 py-2 rounded bg-gray-700 text-white" required>
                    </div>
                    <div>
                        <label class="block mb-1">Email</label>
                        <input type="email" name="email" id="email" class="w-full px-3 py-2 rounded bg-gray-700 text-white" required>
                    </div>
                </div>

                <!-- Add Friend Option -->
                <div class="bg-gray-800 p-4 rounded">
                    <button 
                        type="button" 
                        id="addFriendBtn"
                        class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded text-white font-semibold">
                        + Add a Friend
                    </button>
                </div>

                <!-- Friend Section (hidden until Add Friend is clicked) -->
                <div id="friendSection" style="display: none;" class="bg-gray-800 p-4 rounded space-y-4">
                    <h3 class="font-semibold text-lg mb-2">Friend's Information</h3>
                    <input type="hidden" name="has_friend" id="hasFriendInput" value="0">
                    <div>
                        <label class="block mb-1">Friend's Name</label>
                        <input type="text" name="friend_name" id="friendName" class="w-full px-3 py-2 rounded bg-gray-700 text-white">
                    </div>
                    <div>
                        <label class="block mb-1">Friend's Email</label>
                        <input type="email" name="friend_email" id="friendEmail" class="w-full px-3 py-2 rounded bg-gray-700 text-white">
                    </div>
                    <button 
                        type="button" 
                        id="removeFriendBtn"
                        class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded text-white text-sm">
                        Remove Friend
                    </button>
                </div>

                <!-- Price Display -->
                <div class="bg-blue-900 p-4 rounded">
                    <div class="flex justify-between items-center">
                        <span class="font-semibold">Total Price:</span>
                        <span class="text-2xl font-bold" id="totalPrice">${{ number_format($pregame->price, 2) }}</span>
                    </div>
                    <div class="text-sm text-gray-300 mt-1">
                        <span id="priceBreakdown">1 person × ${{ number_format($pregame->price, 2) }}</span>
                    </div>
                </div>

                <button 
                    type="submit" 
                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded text-white font-semibold text-lg">
                    Continue to Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    const pregamePrice = {{ $pregame->price }};
    const pregameId = {{ $pregame->id }};
    let orderValidated = false;
    let hasFriend = false;

    // Validate Order ID
    document.getElementById('validateBtn').addEventListener('click', function() {
        const orderId = document.getElementById('orderIdInput').value.trim();
        const btn = this;
        const messageDiv = document.getElementById('validationMessage');
        
        if (!orderId) {
            messageDiv.innerHTML = '<span class="text-red-500">Please enter an order ID</span>';
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Validating...';
        messageDiv.innerHTML = '<span class="text-gray-400">Checking order...</span>';

        fetch('{{ route("pregames.validate.order", [], false) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                order_id: orderId,
                pregame_id: pregameId
            })
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.message || 'Validation failed');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.valid) {
                if (data.incomplete) {
                    messageDiv.innerHTML = '<span class="text-yellow-500">⚠ ' + data.message + '</span>';
                } else {
                    messageDiv.innerHTML = '<span class="text-green-500">✓ Order validated!</span>';
                }
                
                document.getElementById('firstName').value = data.data.first_name || '';
                document.getElementById('lastName').value = data.data.last_name || '';
                document.getElementById('email').value = data.data.email || '';
                
                // If friend info is provided, auto-populate and show friend section
                if (data.data.friend_name && data.data.friend_email) {
                    document.getElementById('friendName').value = data.data.friend_name;
                    document.getElementById('friendEmail').value = data.data.friend_email;
                    document.getElementById('friendSection').style.display = 'block';
                    document.getElementById('addFriendBtn').style.display = 'none';
                    hasFriend = true;
                    updatePrice();
                }
                
                document.getElementById('userInfoSection').style.display = 'block';
                document.getElementById('orderIdInput').readOnly = true;
                btn.style.display = 'none';
                orderValidated = true;
            } else {
                messageDiv.innerHTML = '<span class="text-red-500">✗ ' + (data.message || 'Order validation failed') + '</span>';
                btn.disabled = false;
                btn.textContent = 'Validate Order';
            }
        })
        .catch(error => {
            console.error('Validation error:', error);
            messageDiv.innerHTML = '<span class="text-red-500">✗ ' + (error.message || 'Error validating order. Please try again.') + '</span>';
            btn.disabled = false;
            btn.textContent = 'Validate Order';
        });
    });

    // Add Friend
    document.getElementById('addFriendBtn').addEventListener('click', function() {
        document.getElementById('friendSection').style.display = 'block';
        this.style.display = 'none';
        hasFriend = true;
        document.getElementById('hasFriendInput').value = '1';
        updatePrice();
    });

    // Remove Friend
    document.getElementById('removeFriendBtn').addEventListener('click', function() {
        document.getElementById('friendSection').style.display = 'none';
        document.getElementById('addFriendBtn').style.display = 'block';
        document.getElementById('friendName').value = '';
        document.getElementById('friendEmail').value = '';
        hasFriend = false;
        document.getElementById('hasFriendInput').value = '0';
        updatePrice();
    });

    // Update Price Display
    function updatePrice() {
        const quantity = hasFriend ? 2 : 1;
        const total = pregamePrice * quantity;
        document.getElementById('totalPrice').textContent = '$' + total.toFixed(2);
        document.getElementById('priceBreakdown').textContent = quantity + ' person' + (quantity > 1 ? 's' : '') + ' × $' + pregamePrice.toFixed(2);
    }

    // Prevent form submission if not validated
    document.getElementById('signupForm').addEventListener('submit', function(e) {
        if (!orderValidated) {
            e.preventDefault();
            document.getElementById('validationMessage').innerHTML = '<span class="text-red-500">Please validate your order ID first</span>';
        }
    });
</script>
@endsection
