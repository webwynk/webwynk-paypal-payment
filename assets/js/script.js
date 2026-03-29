document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("wpp-paypal-form");
    if (!form) return;

    const step1 = document.getElementById("wpp-step-1");
    const step2 = document.getElementById("wpp-step-2");
    const dot1 = document.getElementById("wpp-dot-1");
    const dot2 = document.getElementById("wpp-dot-2");

    const toStep2Btn = document.getElementById("wpp-to-step-2");
    const backStep1Btn = document.getElementById("wpp-back-to-step-1");

    // Summary elements
    const summaryName = document.getElementById("wpp-summary-name");
    const summaryEmail = document.getElementById("wpp-summary-email");
    const summaryAmount = document.getElementById("wpp-summary-amount");

    // Step 1 to Step 2
    toStep2Btn.addEventListener("click", function () {
        if (validateStep1()) {
            updateSummary();
            showStep(2);
        }
    });

    // Step 2 to Step 1
    backStep1Btn.addEventListener("click", function () {
        showStep(1);
    });

    function validateStep1() {
        const inputs = step1.querySelectorAll("input[required]");
        let valid = true;
        inputs.forEach(input => {
            if (!input.value) {
                input.style.borderColor = "red";
                valid = false;
            } else {
                input.style.borderColor = "";
            }
        });
        return valid;
    }

    function updateSummary() {
        summaryName.textContent = form.querySelector('[name="custom_name"]').value;
        summaryEmail.textContent = form.querySelector('[name="custom_email"]').value;
        summaryAmount.textContent = parseFloat(form.querySelector('[name="amount"]').value).toFixed(2);
    }

    const progressBar = document.getElementById("wpp-progress-bar");

    function showStep(step) {
        if (step === 1) {
            step1.classList.add("wpp-active");
            step2.classList.remove("wpp-active");
            dot1.classList.add("wpp-active");
            dot2.classList.remove("wpp-active");
            progressBar.style.width = "50%";
        } else {
            step1.classList.remove("wpp-active");
            step2.classList.add("wpp-active");
            dot1.classList.remove("wpp-active");
            dot2.classList.add("wpp-active");
            progressBar.style.width = "100%";
        }
    }

    // PayPal Integration
    if (typeof paypal !== "undefined") {
        paypal.Buttons({
            createOrder: function (data, actions) {
                const amount = form.querySelector('[name="amount"]').value;
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: amount
                        }
                    }]
                });
            },
            onApprove: function (data, actions) {
                return actions.order.capture().then(function (details) {
                    // Show loading state
                    step2.innerHTML = '<div style="text-align:center;padding:40px;"><h3>Processing...</h3><p>Please wait while we save your payment.</p></div>';
                    
                    fetch(ajax_obj.ajaxurl, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({
                            action: "save_payment",
                            name: form.querySelector('[name="custom_name"]').value,
                            email: form.querySelector('[name="custom_email"]').value,
                            phone: form.querySelector('[name="custom_phone"]').value,
                            amount: form.querySelector('[name="amount"]').value
                        })
                    }).then(() => {
                        window.location.reload(); // Or show a success message
                        alert("Payment successful! Thank you.");
                    });
                });
            },
            onError: function (err) {
                console.error(err);
                alert("An error occurred during the transaction.");
            }
        }).render('#wpp-paypal-button-container');
    }
});
