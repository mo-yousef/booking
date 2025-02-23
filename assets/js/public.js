// assets/js/public.js

jQuery(document).ready(function ($) {
  let stripe, cardElement;
  let bookingData = {
    serviceId: null,
    serviceName: null,
    price: 0,
    taxRate: 0,
    discount: 0,
    depositAmount: 0,
  };

  // Notification utility
  const notify = {
    success: function (message) {
      if (typeof toastr !== "undefined" && toastr.success) {
        toastr.success(message);
      } else {
        alert(message);
      }
    },
    warning: function (message) {
      if (typeof toastr !== "undefined" && toastr.warning) {
        toastr.warning(message);
      } else {
        alert("Warning: " + message);
      }
    },
    error: function (message) {
      if (typeof toastr !== "undefined" && toastr.error) {
        toastr.error(message);
      } else {
        alert("Error: " + message);
      }
    },
  };

  let selectedService = null;

  // Service selection handling
  $(".vb-select-service").on("click", function () {
    const $serviceItem = $(this).closest(".vb-service-item");
    const serviceId = $serviceItem.data("service-id");
    const $subServiceSelect = $serviceItem.find(".vb-sub-service-select");
    let subServiceId = null;

    // Check if there are sub-services
    if ($subServiceSelect.length > 0) {
      subServiceId = $subServiceSelect.val();

      // Validate sub-service selection if present
      if ($subServiceSelect.val() === "") {
        notify.warning("Please select a sub-service");
        return;
      }
    }

    // Send AJAX request to validate service
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_select_service",
        nonce: vbBookingData.nonce,
        service_id: serviceId,
        sub_service_id: subServiceId,
      },
      beforeSend: function () {
        // Disable service selection while processing
        $(".vb-select-service").prop("disabled", true);
      },
      success: function (response) {
        if (response.success) {
          // Clear previous selections
          $(".vb-service-item").removeClass("selected");
          $serviceItem.addClass("selected");

          // Store selected service
          selectedService = {
            id: serviceId,
            subServiceId: subServiceId,
            name: $serviceItem.find("h4").text(),
            subServiceName: subServiceId
              ? $subServiceSelect.find("option:selected").text()
              : null,
          };

          // Show next button
          $('.vb-next-step[data-next="2"]').show();

          // Optionally update summary or display service details
          $("#vb-summary-service").text(
            selectedService.subServiceName
              ? `${selectedService.name} - ${selectedService.subServiceName}`
              : selectedService.name
          );

          notify.success("Service selected successfully");
        } else {
          notify.error(response.data.message || "Error selecting service");
        }
      },
      error: function () {
        notify.error("An error occurred while selecting the service");
      },
      complete: function () {
        // Re-enable service selection
        $(".vb-select-service").prop("disabled", false);
      },
    });
  });

  // Sub-service selection handling
  $(".vb-sub-service-select").on("change", function () {
    const subServiceId = $(this).val();
    if (subServiceId) {
      bookingData.serviceId = subServiceId;
      bookingData.serviceName = $(this).find("option:selected").text();
      $(".vb-next-step").show();
      fetchServiceDetails(subServiceId);
    } else {
      $(".vb-next-step").hide();
    }
  });

  // Fetch service details
  function fetchServiceDetails(serviceId) {
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_get_service_details",
        nonce: vbBookingData.nonce,
        service_id: serviceId,
      },
      success: function (response) {
        if (response.success) {
          bookingData.price = response.data.price;
          bookingData.taxRate = response.data.tax_rate;
          updateSummary();
        }
      },
    });
  }

  // Step navigation
  $(".vb-next-step").on("click", function () {
    const currentStep = $(this).closest(".vb-step");
    const nextStep = $(this).data("next");

    if (validateStep(currentStep)) {
      currentStep.hide();
      $(`.vb-step[data-step="${nextStep}"]`).show();

      if (nextStep === 4) {
        updateSummary();
      }
    }
  });

  $(".vb-prev-step").on("click", function () {
    const currentStep = $(this).closest(".vb-step");
    const prevStep = $(this).data("prev");

    currentStep.hide();
    $(`.vb-step[data-step="${prevStep}"]`).show();
  });

  // Validate each step
  function validateStep(step) {
    const stepNumber = step.data("step");
    let isValid = true;

    switch (stepNumber) {
      case 1:
        if (!bookingData.serviceId) {
          showError("Please select a service.");
          isValid = false;
        }
        break;

      case 2:
        const date = $("#vb-booking-date").val();
        const timeSlot = $(".vb-time-slot.selected").length;
        if (!date) {
          showError("Please select a date.");
          isValid = false;
        } else if (!timeSlot) {
          showError("Please select a time slot.");
          isValid = false;
        }
        break;

      case 3:
        const name = $("#vb-customer-name").val();
        const email = $("#vb-customer-email").val();
        const phone = $("#vb-customer-phone").val();

        if (!name || !email || !phone) {
          showError("Please fill in all required fields.");
          isValid = false;
        } else if (!isValidEmail(email)) {
          showError("Please enter a valid email address.");
          isValid = false;
        }
        break;
    }

    return isValid;
  }

  // Date selection and time slot loading
  $("#vb-booking-date").on("change", function () {
    const date = $(this).val();
    if (date && bookingData.serviceId) {
      loadTimeSlots(date);
    }
  });

  // Time slot selection
  $(document).on("click", ".vb-time-slot:not(.unavailable)", function () {
    $(".vb-time-slot").removeClass("selected");
    $(this).addClass("selected");
    updateSummary();
  });

  // Load time slots
  function loadTimeSlots(date) {
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_check_availability",
        nonce: vbBookingData.nonce,
        service_id: bookingData.serviceId,
        date: date,
      },
      beforeSend: function () {
        showLoading();
        $("#vb-time-slots").empty();
      },
      success: function (response) {
        if (response.success) {
          displayTimeSlots(response.data.slots);
        } else {
          showError(response.data.message);
        }
      },
      error: function () {
        showError("An error occurred while loading time slots.");
      },
      complete: function () {
        hideLoading();
      },
    });
  }

  // Display time slots
  function displayTimeSlots(slots) {
    const container = $("#vb-time-slots");
    slots.forEach(function (slot) {
      const time = new Date(slot.start).toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });
      const buttonClass = slot.available ? "" : "unavailable";
      container.append(`
                <div class="vb-time-slot ${buttonClass}" data-time="${slot.start}">
                    ${time}
                </div>
            `);
    });
  }

  // Coupon application
  $(".vb-apply-coupon").on("click", function () {
    const couponCode = $("#vb-coupon-code").val();
    if (couponCode) {
      applyCoupon(couponCode);
    }
  });

  // Apply coupon
  function applyCoupon(code) {
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_apply_coupon",
        nonce: vbBookingData.nonce,
        coupon: code,
        service_id: bookingData.serviceId,
        total: bookingData.price,
      },
      beforeSend: function () {
        showLoading();
      },
      success: function (response) {
        if (response.success) {
          bookingData.discount = response.data.discount;
          $(".vb-coupon-row").show();
          updateSummary();
          showSuccess(response.data.message);
        } else {
          showError(response.data.message);
        }
      },
      error: function () {
        showError("An error occurred while applying the coupon.");
      },
      complete: function () {
        hideLoading();
      },
    });
  }

  // Update summary
  function updateSummary() {
    // Update service name
    $("#vb-summary-service").text(bookingData.serviceName);

    // Update time if selected
    const selectedTime = $(".vb-time-slot.selected").data("time");
    if (selectedTime) {
      $("#vb-summary-datetime").text(new Date(selectedTime).toLocaleString());
    }

    $("#vb-summary-price").text(formatCurrency(bookingData.price));

    // Update tax if applicable
    const taxAmount = bookingData.price * (bookingData.taxRate / 100);
    if (taxAmount > 0) {
      $("#vb-summary-tax").text(formatCurrency(taxAmount));
      $(".tax-row").show();
    }

    // Update discount if applicable
    if (bookingData.discount > 0) {
      $("#vb-summary-discount").text(
        "-" + formatCurrency(bookingData.discount)
      );
      $(".vb-coupon-row").show();
    }

    // Calculate total
    const total = bookingData.price + taxAmount - bookingData.discount;
    $("#vb-summary-total").text(formatCurrency(total));

    // Update deposit if applicable
    if (bookingData.depositEnabled) {
      $(".vb-deposit-option").show();
      const depositAmount = calculateDeposit(total);
      $(".vb-deposit-amount").text("(" + formatCurrency(depositAmount) + ")");
    }
  }

  // Calculate deposit amount
  function calculateDeposit(total) {
    if (bookingData.depositType === "percentage") {
      return total * (bookingData.depositAmount / 100);
    }
    return bookingData.depositAmount;
  }

  // Submit booking
  $(".vb-submit-booking").on("click", function (e) {
    e.preventDefault();
    submitBooking();
  });

  // Helper functions
  function formatCurrency(amount) {
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: "USD",
    }).format(amount);
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function showError(message) {
    if (typeof toastr !== "undefined") {
      toastr.error(message);
    } else {
      alert(message);
    }
  }

  function showSuccess(message) {
    if (typeof toastr !== "undefined") {
      toastr.success(message);
    } else {
      alert(message);
    }
  }

  function showLoading() {
    // Add loading indicator implementation
    $('<div class="vb-loading">Loading...</div>').appendTo("body");
  }

  function hideLoading() {
    // Remove loading indicator
    $(".vb-loading").remove();
  }
});
