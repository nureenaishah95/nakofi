<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nakofi Cafe</title>
    <link rel="icon" href="<?= BASE_URL ?><?= BASE_URL ?>asset/img/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Sofia">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: rgb(255, 255, 255);
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        .header {
            background-color: #212529;
            padding: 15px 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }
        .header h1 {
            font-size: 35px;
            font-style: italic;
            font-family: "Sofia", sans-serif;
            color: #ecf0f1;
            margin-left: 565px;
        }
        .logout {
            background-color: rgba(92, 92, 92, 0.54);
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
            width: 75px;
            height: 35px;
            font-weight: bold;
        }
        .logout:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }
        .container {
            width: 90%;
            max-width: 600px;
            margin: 105px auto 20px;
            border: 2px solid #000;
            border-radius: 10px;
            padding: 20px;
            background-color: rgb(255, 255, 255);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        .title {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
            color: #212529;
            font-family: "Sofia", sans-serif;
            font-style: italic;
            font-weight: 600;
        }
        .payment-options {
            margin: 20px 0;
            text-align: center;
            color: #000;
        }
        .payment-option {
            display: block;
            margin: 10px auto;
            padding: 15px;
            border: 1px solid #000;
            border-radius: 5px;
            cursor: pointer;
            width: 80%;
            background-color: hsl(0, 0%, 100%);
            color: #000;
            position: relative;
            transition: all 0.3s ease;
        }
        .payment-option:hover {
            background-color: #212529;
            color: #fff;
        }
        .payment-option.active {
            background-color: #212529;
            border-color: rgb(0, 0, 0);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            color: #fff;
        }
        .payment-option i {
            margin-right: 10px;
        }
        .card-details, .bank-options {
            display: none;
            margin-top: 10px;
            text-align: left;
            padding: 15px;
            background-color: #212529;
            border-radius: 5px;
            position: relative;
            z-index: 1;
            transition: all 0.3s ease;
            opacity: 0;
            transform: translateY(10px);
            color: #fff;
        }
        .card-details.active, .bank-options.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }
        .card-details input {
            width: 100%;
            padding: 8px;
            margin: 5px 0;
            border: 1px solid #ecf0f1;
            border-radius: 5px;
            background-color: #ecf0f1;
            color: rgb(51, 51, 51);
        }
        .bank-options .bank-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px 0;
            background-color: #212529;
            transition: background-color 0.3s;
        }
        .bank-options .bank-option:hover {
            background-color: #3d566e;
        }
        .bank-options .bank-option input {
            margin-right: 10px;
        }
        .bank-options .bank-option img {
            width: 50px;
            height: 30px;
            vertical-align: middle;
            margin: 0 10px;
        }
        .pay-button {
            display: block;
            width: 100px;
            margin: 20px auto;
            padding: 10px;
            background-color: #28a745;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-align: center;
            transition: background-color 0.3s;
        }
        .pay-button:hover {
            background-color: #218838;
        }
        .footer {
            background-color: #212529;
            padding: 10px 20px;
            text-align: center;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
            position: fixed;
            bottom: 0;
            width: 100%;
            color: #ecf0f1;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .container {
                width: 95%;
            }
            .header h1 {
                margin-left: 0;
                font-size: 28px;
            }
            .bank-options {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>nakofi cafe</h1>
        <button class="logout" onclick="window.location.href='payment.php'">Back</button>
    </div>
    <div class="container">
        <div class="title">Payment Method</div>
        <form id="payment_form" action="process_payment.php" method="post">
            <div class="payment-options">
                <div class="payment-option" data-method="credit_card">
                    <i class="fas fa-credit-card"></i> Credit Card
                    <div class="card-details" id="credit_card_details">
                        <input type="text" name="card_number" placeholder="Card Number" required>
                        <input type="text" name="expiry_date" placeholder="Expiry Date (MM/YY)" required>
                        <input type="text" name="cvv" placeholder="CVV" required>
                    </div>
                </div>
                <div class="payment-option" data-method="online_banking">
                    <i class="fas fa-university"></i> Online Banking
                    <div class="bank-options" id="bank_options">
                        <div class="bank-option">
                            <input type="radio" name="bank" value="maybank" required>
                            <img src="/nakofi/asset/img/maybank.png" alt="Maybank">
                            Maybank
                        </div>
                        <div class="bank-option">
                            <input type="radio" name="bank" value="bsn" required>
                            <img src="/nakofi/asset/img/bsn.jpg" alt="BSN">
                            BSN
                        </div>
                        <div class="bank-option">
                            <input type="radio" name="bank" value="rhb" required>
                            <img src="/nakofi/asset/img/rhb.png" alt="RHB">
                            RHB
                        </div>
                    </div>
                </div>
                <input type="hidden" name="payment_method" id="payment_method" value="">
            </div>
            <button type="submit" class="pay-button">Pay Now</button>
        </form>
    </div>
    <div class="footer">
        <p>© 2025 Nakofi Cafe. All rights reserved.</p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const paymentOptions = document.querySelectorAll('.payment-option');
            const creditCardDetails = document.getElementById('credit_card_details');
            const bankOptions = document.getElementById('bank_options');
            const paymentMethodInput = document.getElementById('payment_method');
            const paymentForm = document.getElementById('payment_form');

            if (!creditCardDetails || !bankOptions || !paymentMethodInput || !paymentForm) {
                console.error('Error: DOM elements not found');
                return;
            }

            // Prevent clicks inside card-details and bank-options from bubbling to payment-option
            [creditCardDetails, bankOptions].forEach(container => {
                container.addEventListener('click', (event) => {
                    event.stopPropagation();
                    console.log(`Click inside ${container.id} stopped propagation`);
                });
            });

            paymentOptions.forEach(option => {
                option.addEventListener('click', function(event) {
                    if (event.target === this) {
                        const method = this.getAttribute('data-method');
                        const isActive = this.classList.contains('active');
                        console.log(`Clicked payment method: ${method}, isActive: ${isActive}`);

                        if (isActive) {
                            this.classList.remove('active');
                            paymentMethodInput.value = '';
                            creditCardDetails.classList.remove('active');
                            bankOptions.classList.remove('active');
                            console.log(`Closed ${method} list`);
                        } else {
                            paymentOptions.forEach(opt => opt.classList.remove('active'));
                            creditCardDetails.classList.remove('active');
                            bankOptions.classList.remove('active');

                            this.classList.add('active');
                            paymentMethodInput.value = method;

                            if (method === 'credit_card') {
                                console.log('Showing credit card details');
                                creditCardDetails.classList.add('active');
                            } else if (method === 'online_banking') {
                                console.log('Showing bank options');
                                bankOptions.classList.add('active');
                            }
                        }
                    }
                });
            });

            paymentForm.addEventListener('submit', function(event) {
    event.preventDefault();
    const paymentMethod = paymentMethodInput.value;
    const selectedBank = document.querySelector('input[name="bank"]:checked');

    if (paymentMethod === 'online_banking' && selectedBank) {
        const bankCode = selectedBank.value;
        // Map bank values to FPX bank codes
        const fpxBankCodes = {
            'maybank': 'MBB0228', // Example FPX bank code for Maybank
            'bsn': 'BSN0220',     // Example FPX bank code for BSN
            'rhb': 'RHB0218'      // Example FPX bank code for RHB
        };

        const fpxBankCode = fpxBankCodes[bankCode];
        if (!fpxBankCode) {
            alert('Invalid bank selected');
            return;
        }

        // Prepare FPX payment form data
        const fpxForm = document.createElement('form');
        fpxForm.method = 'POST';
        fpxForm.action = 'https://your-fpx-gateway-url.com'; // Replace with real FPX gateway URL

        const fpxParams = {
            fpx_msgType: 'AR',
            fpx_msgToken: '01', // Retail customer
            fpx_sellerExId: 'YOUR_MERCHANT_ID', // Replace with your FPX merchant ID
            fpx_sellerExOrderNo: `ORDER_${Date.now()}`, // Unique order ID
            fpx_sellerTxnTime: new Date().toISOString().replace(/[-:T]/g, '').slice(0, 14), // Format: YYYYMMDDHHMMSS
            fpx_sellerOrderNo: `ORDER_${Date.now()}`, // Unique order ID
            fpx_sellerId: 'YOUR_SELLER_ID', // Replace with your FPX seller ID
            fpx_sellerBankCode: '01', // Typically '01' for FPX
            fpx_txnCurrency: 'MYR',
            fpx_txnAmount: '100.00', // Replace with actual amount
            fpx_buyerEmail: 'customer@example.com', // Replace with actual customer email
            fpx_buyerBankId: fpxBankCode,
            fpx_version: '7.0' // FPX version
            // fpx_checkSum must be generated server-side
        };

        // Append FPX parameters as hidden inputs
        for (const [key, value] of Object.entries(fpxParams)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            fpxForm.appendChild(input);
        }

        document.body.appendChild(fpxForm);
        console.log('Redirecting to FPX payment gateway for bank:', bankCode);
        fpxForm.submit(); // This triggers the redirect to the FPX gateway
    } else if (paymentMethod === 'credit_card') {
        console.log('Submitting credit card payment');
        paymentForm.submit();
    } else {
        alert('Please select a payment method and complete the required fields');
    }
});

            console.log('Page loaded with no default payment method selected');
        });
    </script>
</body>
</html>