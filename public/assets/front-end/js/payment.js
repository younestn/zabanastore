"use strict";

setTimeout(function () {
    $(".stripe-button-el").hide();
    $(".razorpay-payment-button").hide();
}, 10);

$(function () {
    $(".proceed_to_next_button").addClass("disabled");
});

const radioButtons = document.querySelectorAll('input[type="radio"]');

radioButtons.forEach((radioButton) => {
    radioButton.addEventListener("change", function () {
        radioButtons.forEach((otherRadioButton) => {
            if (otherRadioButton !== this) {
                otherRadioButton.checked = false;
            }
        });

        if (this.checked) {
            this.setAttribute("checked", true);
        } else {
            this.setAttribute("checked", false);
        }

        updateProceedButtonState();
    });
});

function updateProceedButtonState() {
    let paymentInputCheckbox = $('.payment-input-checkbox').length === $('.payment-input-checkbox:checked').length;

    let radioStatus = false;
    let payOfflineSelected = false;

    radioButtons.forEach((radio) => {
        if (radio.checked) {
            if (radio.id === 'pay_offline') {
                payOfflineSelected = true;
            } else {
                radioStatus = true;
            }
        }
    });

    if (paymentInputCheckbox && radioStatus) {
        $(".proceed_to_next_button").removeClass("disabled");
    } else {
        $(".proceed_to_next_button").addClass("disabled");
    }

    if (payOfflineSelected) {
        $(".pay_offline_card").removeClass("d-none");
        $(".proceed_to_next_button").addClass("disabled");
    } else {
        $(".pay_offline_card").addClass("d-none");
    }
}

$('.payment-input-checkbox').on('click', function () {
    if (this.checked) {
        $('.payment-input-checkbox').prop('checked', true);
    }
    updateProceedButtonState();
});

updateProceedButtonState();

function checkoutFromPayment() {
    let checked_button_id = $('input[type="radio"]:checked').attr("id");

    if (!checked_button_id) {
        toastr.error('Please select a payment method', {
            CloseButton: true,
            ProgressBar: true
        });
        return;
    }

    $(".action-checkout-function").attr("disabled", true).addClass("disabled");

    if (checked_button_id === 'cash_on_delivery') {
        document.getElementById("cash_on_delivery_form").submit();
        return;
    }

    $("#" + checked_button_id + "_form").submit();
}

const buttons = document.querySelectorAll(".offline_payment_button");
const selectElement = document.getElementById("pay_offline_method");

if (buttons.length > 0 && selectElement) {
    buttons.forEach((button) => {
        button.addEventListener("click", function () {
            const buttonId = this.id;
            pay_offline_method_field(buttonId);
            selectElement.value = buttonId;
        });
    });
}

$("#pay_offline_method").on("change", function () {
    pay_offline_method_field(this.value);
});

function pay_offline_method_field(method_id) {
    $.ajaxSetup({
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
    });

    $.ajax({
        url: $("#route-pay-offline-method-list").data("url") + "?method_id=" + method_id,
        data: {},
        processData: false,
        contentType: false,
        type: "get",
        success: function (response) {
            $("#payment_method_field").html(response.methodHtml);
            $("#selectPaymentMethod").modal().show();
        },
        error: function () {}
    });
}

$("#bring_change_amount").on("shown.bs.collapse", function () {
    $("#bring_change_amount_btn").text($(this).data("less"));
});

$("#bring_change_amount").on("hidden.bs.collapse", function () {
    $("#bring_change_amount_btn").text($(this).data("more"));
});

$(document).ready(function () {
    $("input").on("change", function () {
        bringChangeAmountSectionRender();
    });

    function bringChangeAmountSectionRender() {
        if ($("#cash_on_delivery").prop("checked")) {
            $(".bring_change_amount_section").slideDown();
        } else {
            $(".bring_change_amount_section").slideUp();
        }
    }

    $("#bring_change_amount_input").on("keyup keypress change", function () {
        $("#bring_change_amount_value").val($(this).val());
    });
});

$(document).off('click.checkoutPayment', '.action-checkout-function').on('click.checkoutPayment', '.action-checkout-function', function (e) {
    e.preventDefault();

    let currentRoute = $('#route-action-checkout-function').data('route');

    if (currentRoute === 'checkout-payment' && !$(this).hasClass('disabled')) {
        checkoutFromPayment();
    }
});
