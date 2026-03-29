document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#wpp-paypal-form');
    if (!form) return;

    const step1 = document.getElementById('wpp-step-1');
    const step2 = document.getElementById('wpp-step-2');
    const progressBar = document.getElementById('wpp-progress-bar');
    const stepCounter = document.getElementById('wpp-step-counter');
    
    const toStep2Btn = document.getElementById('wpp-to-step-2');
    const backToStep1Btn = document.getElementById('wpp-back-to-step-1');

    // Navigation Logic
    toStep2Btn.addEventListener('click', function() {
        if (validateStep1()) {
            step1.classList.remove('wpp-active');
            step2.classList.add('wpp-active');
            progressBar.style.width = '100%';
            stepCounter.innerText = 'STEP 2 OF 2';
            updateSummary();
        }
    });

    backToStep1Btn.addEventListener('click', function() {
        step2.classList.remove('wpp-active');
        step1.classList.add('wpp-active');
        progressBar.style.width = '50%';
        stepCounter.innerText = 'STEP 1 OF 2';
    });

    function validateStep1() {
        let valid = true;
        const inputs = step1.querySelectorAll('input[required]');
        inputs.forEach(input => {
            if (!input.value) {
                input.style.borderColor = '#ff512a';
                valid = false;
            } else {
                input.style.borderColor = 'rgba(255, 255, 255, 0.1)';
            }
        });
        return valid;
    }

    function updateSummary() {
        document.getElementById('wpp-summary-name').innerText = form.querySelector('[name="custom_name"]').value;
        document.getElementById('wpp-summary-email').innerText = form.querySelector('[name="custom_email"]').value;
        document.getElementById('wpp-summary-amount').innerText = parseFloat(form.querySelector('[name="amount"]').value).toFixed(2);
        
        initPayPalButton();
    }

    function initPayPalButton() {
        const container = document.getElementById('wpp-paypal-button-container');
        if (!container) return;
        
        container.innerHTML = ''; // Clear previous if any

        if (typeof paypal !== 'undefined') {
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: { value: form.querySelector('[name="amount"]').value }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        savePayment(details);
                    });
                }
            }).render('#wpp-paypal-button-container');
        }
    }

    function savePayment(details) {
        const formData = new FormData(form);
        formData.append('action', 'save_payment');
        formData.append('paypal_details', JSON.stringify(details));

        if (typeof wpp_ajax !== 'undefined') {
            fetch(wpp_ajax.ajax_url, {
                method: 'POST',
                body: formData
            }).then(() => {
                alert('Payment Successful! Thank you.');
                location.reload();
            }).catch(err => {
                console.error('Save payment error:', err);
                alert('Success, but failed to record transaction locally.');
            });
        }
    }
});
