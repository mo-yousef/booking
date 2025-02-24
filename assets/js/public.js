// assets/js/public.js

jQuery(document).ready(function ($) {
  // Booking state management
  let bookingState = {
    serviceId: null,
    subServiceId: null,
    serviceName: null,
    price: 0,
    taxRate: 0,
    discount: 0,
    depositAmount: 0,
    selectedDate: null,
    selectedTime: null,
    zipCode: null,
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

  // Step navigation
  function showStep(stepNumber) {
    // Hide all steps
    $(".vb-step").hide();
    // Show specific step
    $(`.vb-step[data-step="${stepNumber}"]`).show();
  }

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
        $(".vb-select-service").prop("disabled", true);
      },
      success: function (response) {
        if (response.success) {
          // Update booking state
          bookingState.serviceId = serviceId;
          bookingState.subServiceId = subServiceId;
          bookingState.serviceName = $serviceItem.find("h4").text();
          bookingState.price = response.data.price || 0;
          bookingState.taxRate = response.data.tax_rate || 0;

          // Clear previous selections and highlight current
          $(".vb-service-item").removeClass("selected");
          $serviceItem.addClass("selected");

          // Update service summary
          $("#vb-summary-service").text(
            subServiceId
              ? `${bookingState.serviceName} - ${$subServiceSelect
                  .find("option:selected")
                  .text()}`
              : bookingState.serviceName
          );

          // Proceed to next step
          showStep(2);
          notify.success("Service selected successfully");
        } else {
          notify.error(response.data.message || "Error selecting service");
        }
      },
      error: function () {
        notify.error("An error occurred while selecting the service");
      },
      complete: function () {
        $(".vb-select-service").prop("disabled", false);
      },
    });
  });

  // ZIP code handling
  $("#vb-zip-code").on("change", function () {
    const zipCode = $(this).val();

    // Validate ZIP code
    if (zipCode.length !== 5) {
      notify.warning("Please enter a valid 5-digit ZIP code");
      return;
    }

    // Update booking state
    bookingState.zipCode = zipCode;

    // If date is selected, load time slots
    if (bookingState.selectedDate) {
      loadTimeSlots();
    }
  });

  // Date selection handling
  $("#vb-booking-date").on("change", function () {
    const date = $(this).val();

    // Validate service and ZIP code selection first
    if (!bookingState.serviceId) {
      notify.warning("Please select a service first");
      $(this).val("");
      return;
    }

    if (!bookingState.zipCode) {
      notify.warning("Please enter a ZIP code first");
      $(this).val("");
      return;
    }

    // Update booking state
    bookingState.selectedDate = date;

    // Load time slots
    loadTimeSlots();
  });

  // Load time slots
  function loadTimeSlots() {
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_check_availability",
        nonce: vbBookingData.nonce,
        service_id: bookingState.serviceId,
        date: bookingState.selectedDate,
        zip_code: bookingState.zipCode,
      },
      beforeSend: function () {
        $("#vb-time-slots").html("<p>Loading available time slots...</p>");
        $(".vb-time-slot-container").addClass("loading");
      },
      success: function (response) {
        const $timeSlotsContainer = $("#vb-time-slots");
        $timeSlotsContainer.empty();

        if (
          response.success &&
          response.data.slots &&
          response.data.slots.length > 0
        ) {
          response.data.slots.forEach(function (slot) {
            const slotTime = new Date(slot.start);
            const formattedTime = slotTime.toLocaleTimeString([], {
              hour: "2-digit",
              minute: "2-digit",
            });

            const $slotButton = $("<button>")
              .addClass("vb-time-slot")
              .addClass(slot.available ? "" : "unavailable")
              .attr("data-time", slot.start)
              .text(formattedTime)
              .prop("disabled", !slot.available);

            $timeSlotsContainer.append($slotButton);
          });
        } else {
          $timeSlotsContainer.html(
            "<p>No available time slots for the selected date.</p>"
          );
        }
      },
      error: function () {
        $("#vb-time-slots").html(
          "<p>Error loading time slots. Please try again.</p>"
        );
        notify.error("Failed to load available time slots");
      },
      complete: function () {
        $(".vb-time-slot-container").removeClass("loading");
      },
    });
  }

  // Time slot selection
  $(document).on("click", ".vb-time-slot:not(.unavailable)", function () {
    $(".vb-time-slot").removeClass("selected");
    $(this).addClass("selected");

    // Update booking state with selected time
    bookingState.selectedTime = $(this).data("time");
  });

  // Step navigation buttons
  $(".vb-next-step").on("click", function () {
    const currentStep = $(this).closest(".vb-step");
    const nextStep = $(this).data("next");

    if (validateStep(currentStep, nextStep)) {
      showStep(nextStep);

      // Special handling for review step
      if (nextStep === 4) {
        updateSummary();
      }
    }
  });

  $(".vb-prev-step").on("click", function () {
    const currentStep = $(this).closest(".vb-step");
    const prevStep = $(this).data("prev");
    showStep(prevStep);
  });

  // Step validation
  function validateStep(currentStep, nextStep) {
    switch (nextStep) {
      case 2: // Date & Time step
        if (!bookingState.serviceId) {
          notify.warning("Please select a service");
          return false;
        }
        if (!bookingState.zipCode) {
          notify.warning("Please enter a ZIP code");
          return false;
        }
        break;

      case 3: // Customer Information step
        if (!bookingState.selectedDate || !bookingState.selectedTime) {
          notify.warning("Please select a date and time");
          return false;
        }
        break;

      case 4: // Review step
        const name = $("#vb-customer-name").val();
        const email = $("#vb-customer-email").val();
        const phone = $("#vb-customer-phone").val();

        if (!name || !email || !phone) {
          notify.warning("Please fill in all required fields");
          return false;
        }

        if (!isValidEmail(email)) {
          notify.warning("Please enter a valid email address");
          return false;
        }
        break;
    }

    return true;
  }

  // Update summary for review step
  function updateSummary() {
    // Service details
    $("#vb-summary-service").text(bookingState.serviceName);

    // Date and Time
    if (bookingState.selectedTime) {
      $("#vb-summary-datetime").text(
        new Date(bookingState.selectedTime).toLocaleString()
      );
    }

    // Location
    $("#vb-summary-location").text(bookingState.zipCode);

    // Price calculation
    const price = bookingState.price;
    const taxAmount = price * (bookingState.taxRate / 100);
    const discount = bookingState.discount || 0;

    $("#vb-summary-price").text(formatCurrency(price));

    // Tax handling
    if (taxAmount > 0) {
      $("#vb-summary-tax").text(formatCurrency(taxAmount));
      $(".tax-row").show();
    } else {
      $(".tax-row").hide();
    }

    // Discount handling
    if (discount > 0) {
      $("#vb-summary-discount").text("-" + formatCurrency(discount));
      $(".vb-coupon-row").show();
    } else {
      $(".vb-coupon-row").hide();
    }

    // Total calculation
    const total = price + taxAmount - discount;
    $("#vb-summary-total").text(formatCurrency(total));
  }

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

  // Booking submission
  $(".vb-submit-booking").on("click", function (e) {
    e.preventDefault();
    submitBooking();
  });

  // Submit booking function
  function submitBooking() {
    // Collect form data
    const bookingData = {
      service_id: bookingState.serviceId,
      sub_service_id: bookingState.subServiceId,
      booking_date: bookingState.selectedTime,
      zip_code: bookingState.zipCode,
      customer_name: $("#vb-customer-name").val(),
      customer_email: $("#vb-customer-email").val(),
      customer_phone: $("#vb-customer-phone").val(),
      customer_notes: $("#vb-customer-notes").val(),
      total_amount: parseFloat(
        $("#vb-summary-total")
          .text()
          .replace(/[^0-9.-]+/g, "")
      ),
      tax_amount:
        parseFloat(
          $("#vb-summary-tax")
            .text()
            .replace(/[^0-9.-]+/g, "")
        ) || 0,
      discount: bookingState.discount || 0,
    };

    // Optional: Deposit handling
    if ($('input[name="pay_deposit"]').is(":checked")) {
      bookingData.pay_deposit = true;
    }

    // Send booking request
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_create_booking",
        nonce: vbBookingData.nonce,
        ...bookingData,
      },
      beforeSend: function () {
        // Disable submit button and show loading
        $(".vb-submit-booking").prop("disabled", true).addClass("loading");
        showLoading();
      },
      success: function (response) {
        if (response.success) {
          // Move to confirmation step
          showStep(5);

          // Display booking reference
          $("#vb-booking-reference").text(response.data.booking_id);

          // Show success notification
          notify.success("Booking created successfully!");
        } else {
          // Show error message
          notify.error(response.data.message || "Failed to create booking");
        }
      },
      error: function () {
        notify.error("An error occurred while processing your booking");
      },
      complete: function () {
        // Re-enable submit button and hide loading
        $(".vb-submit-booking").prop("disabled", false).removeClass("loading");
        hideLoading();
      },
    });
  }

  // Loading and error handling functions (as before)
  function showLoading() {
    $('<div class="vb-loading-overlay">')
      .html('<div class="vb-loading-spinner">Loading...</div>')
      .appendTo("body");
  }

  function hideLoading() {
    $(".vb-loading-overlay").remove();
  }
});
