/*
 * The public hotel website's interactivity: the mobile nav toggle
 * (every page) and the booking-wizard's Razorpay flow (site/book.blade.php
 * only — SiteBooking.init() is a no-op until that page calls it).
 *
 * Plain vanilla JS, no build step, no dependency beyond Razorpay's own
 * checkout.js (loaded only on the booking page, only when the hotel has
 * payment configured — see the @push('scripts') block in site/book.blade.php).
 * Deployed as an identical copy at public/js/site.js — see that file's own
 * header comment for why.
 */

(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    /* ---------------------------------------------------------------------
     * Mobile nav toggle — present on every site page.
     * ------------------------------------------------------------------ */
    function initNav() {
        var toggle = document.getElementById('siteNavToggle');
        var links = document.getElementById('siteNavLinks');

        if (!toggle || !links) return;

        toggle.addEventListener('click', function () {
            var isOpen = links.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        // A link inside the menu was used — close it so it doesn't cover
        // the page the visitor just navigated to for a moment before load.
        links.addEventListener('click', function (event) {
            if (event.target.tagName === 'A') {
                links.classList.remove('is-open');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* ---------------------------------------------------------------------
     * Booking wizard — order, then Razorpay Checkout, then verify.
     * ------------------------------------------------------------------ */
    var SiteBooking = {
        init: function (options) {
            var form = document.getElementById('bookingForm');
            var payBtn = document.getElementById('payBtn');
            var alertBox = document.getElementById('bookAlert');

            if (!form || !payBtn || !options || !options.orderUrl || !options.verifyUrl) return;

            var payBtnDefaultText = payBtn.innerHTML;

            function showAlert(message, tone) {
                if (!alertBox) return;
                alertBox.className = 'site-alert ' + (tone === 'accent' ? 'site-alert-accent' : 'site-alert-danger');
                alertBox.textContent = message;
                alertBox.style.display = 'block';
                alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            function hideAlert() {
                if (alertBox) alertBox.style.display = 'none';
            }

            function setBusy(busy) {
                payBtn.disabled = busy;
                payBtn.innerHTML = busy
                    ? '<span class="site-spinner"></span> Processing&hellip;'
                    : payBtnDefaultText;
            }

            function fail(message) {
                setBusy(false);
                showAlert(message || 'Something went wrong. Please try again.');
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                hideAlert();
                setBusy(true);

                var formData = new FormData(form);

                fetch(options.orderUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                    body: formData,
                })
                    .then(function (response) {
                        return response.json().then(function (data) {
                            return { ok: response.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            fail(result.data && result.data.message);
                            return;
                        }

                        openCheckout(result.data);
                    })
                    .catch(function () {
                        fail('We could not reach the server. Please check your connection and try again.');
                    });
            });

            function openCheckout(order) {
                if (typeof Razorpay === 'undefined') {
                    fail('Payment could not load just now. Please refresh the page and try again.');
                    return;
                }

                var rzp = new Razorpay({
                    key: order.key,
                    amount: order.amount,
                    currency: order.currency,
                    name: order.name,
                    description: order.description,
                    order_id: order.order_id,
                    prefill: order.prefill,
                    notes: order.notes,
                    theme: { color: '#8d2f6b' },
                    modal: {
                        ondismiss: function () {
                            setBusy(false);
                            showAlert('Payment was cancelled — you can try again whenever you’re ready.', 'accent');
                        },
                    },
                    handler: function (response) {
                        verifyPayment(response);
                    },
                });

                rzp.on('payment.failed', function (response) {
                    var reason = response && response.error && response.error.description
                        ? response.error.description
                        : 'Your payment did not go through.';
                    fail(reason);
                });

                rzp.open();
            }

            function verifyPayment(payload) {
                fetch(options.verifyUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(payload),
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                            return;
                        }

                        // Payment cleared but the room could not be confirmed
                        // automatically (see BookingController::respondToFinalize) —
                        // the guest has genuinely paid, so this is shown as
                        // reassurance, not an error, and the form is retired
                        // rather than left open to a second charge.
                        setBusy(false);
                        payBtn.style.display = 'none';
                        showAlert(data.message || 'Your payment was received. Our team will confirm your room shortly.', 'accent');
                    })
                    .catch(function () {
                        setBusy(false);
                        showAlert(
                            'Your payment may have gone through, but we could not confirm it from here. ' +
                            'Please contact the hotel with your payment details before booking again.'
                        );
                    });
            }
        },
    };

    window.SiteBooking = SiteBooking;

    document.addEventListener('DOMContentLoaded', initNav);
})();
