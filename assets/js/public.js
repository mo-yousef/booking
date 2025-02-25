// assets/js/public.js

jQuery(document).ready(function ($) {
  let stripe, cardElement;
  let bookingData = {
    basePrice: 0,
    selectedOptions: {},
    optionsPrices: {},
    totalOptionsPrice: 0,
    taxRate: 0,
    discount: 0,
    depositAmount: 0,
  };

  // Initialize booking data if a service is selected
  if (typeof vbBookingData !== "undefined" && vbBookingData.service) {
    initBookingData();
  }

  // Initialize Stripe elements if available
  function initStripe() {
    if (typeof vbBookingData !== "undefined" && vbBookingData.stripeKey) {
      stripe = Stripe(vbBookingData.stripeKey);
      const elements = stripe.elements();
      cardElement = elements.create("card");

      // Make sure the element exists before mounting
      if ($("#vb-stripe-card-element").length) {
        cardElement.mount("#vb-stripe-card-element");

        cardElement.on("change", function (event) {
          const displayError = document.getElementById("vb-card-errors");
          if (displayError) {
            if (event.error) {
              displayError.textContent = event.error.message;
            } else {
              displayError.textContent = "";
            }
          }
        });
      }
    }
  }

  // Initialize booking data
  function initBookingData() {
    // Get base price from service data
    const regularPrice = parseFloat(vbBookingData.service.regular_price || 0);
    const salePrice = parseFloat(vbBookingData.service.sale_price || 0);

    bookingData.basePrice =
      salePrice && salePrice < regularPrice ? salePrice : regularPrice;
    bookingData.taxRate = parseFloat(vbBookingData.service.tax_rate || 0);

    // Initialize Stripe if available
    initStripe();

    // Update initial price display
    updateRunningTotal();
  }

  // Step navigation
  $(".vb-next-step").on("click", function () {
    const currentStep = $(this).closest(".vb-step");
    const nextStep = $(this).data("next");

    if (validateStep(currentStep)) {
      currentStep.hide();
      $(`.vb-step[data-step="${nextStep}"]`).show();
      $(".vb-form-progress li").removeClass("active");
      $(`.vb-form-progress li[data-step="${nextStep}"]`).addClass("active");

      // If moving to review step, update summary
      if (nextStep === 4 || nextStep === 5) {
        // Depends on if options are enabled
        updateSummary();
      }
    }
  });

  $(".vb-prev-step").on("click", function () {
    const currentStep = $(this).closest(".vb-step");
    const prevStep = $(this).data("prev");

    currentStep.hide();
    $(`.vb-step[data-step="${prevStep}"]`).show();
    $(".vb-form-progress li").removeClass("active");
    $(`.vb-form-progress li[data-step="${prevStep}"]`).addClass("active");
  });

  // Option selection change handling
  $(document).on("change", ".vb-option-input", function () {
    calculateOptionsPrices();
    updateRunningTotal();
  });

  // Number input special handling for multiply price type
  $(document).on("change keyup", ".vb-option-number", function () {
    calculateOptionsPrices();
    updateRunningTotal();
  });

  // ZIP code validation and availability check
  $("#vb-zip-code").on("change", function () {
    const zipCode = $(this).val();
    if (zipCode.length === 5) {
      checkServiceAvailability(zipCode);
    }
  });

  // Date selection and time slot loading
  $("#vb-booking-date").on("change", function () {
    const date = $(this).val();
    const zipCode = $("#vb-zip-code").val();

    if (date && zipCode) {
      loadTimeSlots(date, zipCode);
    }
  });

  // Time slot selection
  $(document).on("click", ".vb-time-slot:not(.unavailable)", function () {
    $(".vb-time-slot").removeClass("selected");
    $(this).addClass("selected");
    updateSummary();
  });

  // Coupon application
  $(".vb-apply-coupon").on("click", function () {
    const couponCode = $("#vb-coupon-code").val();
    if (couponCode) {
      applyCoupon(couponCode);
    }
  });

  // Deposit toggle
  $('input[name="pay_deposit"]').on("change", function () {
    updateSummary();
  });

  // Form submission
  $(".vb-submit-booking").on("click", function (e) {
    e.preventDefault();
    submitBooking();
  });

  // Calculate prices for all selected options
  function calculateOptionsPrices() {
    bookingData.optionsPrices = {};
    bookingData.selectedOptions = {};
    bookingData.totalOptionsPrice = 0;

    $(".vb-option-group").each(function () {
      const optionIndex = $(this).data("option-index");
      const optionType = $(this).data("option-type");
      const priceType = $(this).data("price-type");
      let optionPrice = 0;
      let optionValue = null;

      // Get selected value and calculate price based on option type
      switch (optionType) {
        case "text":
        case "textarea":
          // For these types, we just use the fixed price if entered
          const input = $(this).find(".vb-option-input");
          optionValue = input.val();

          if (optionValue && input.data("price")) {
            optionPrice = parseFloat(input.data("price"));
          }
          break;

        case "number":
          const numberInput = $(this).find(".vb-option-input");
          const value = parseInt(numberInput.val()) || 0;
          optionValue = value;

          if (value > 0) {
            const baseOptionPrice = parseFloat(numberInput.data("price")) || 0;

            if (priceType === "multiply") {
              // Multiply price by quantity
              optionPrice = baseOptionPrice * value;
            } else if (priceType === "fixed") {
              // Fixed price regardless of value
              optionPrice = baseOptionPrice;
            } else if (priceType === "percentage") {
              // Percentage of base price
              optionPrice =
                ((bookingData.basePrice * baseOptionPrice) / 100) * value;
            }
          }
          break;

        case "dropdown":
          const select = $(this).find(".vb-option-select");
          const selectedOption = select.find("option:selected");
          optionValue = select.val();

          if (optionValue) {
            const choicePrice = parseFloat(selectedOption.data("price")) || 0;

            if (priceType === "fixed") {
              optionPrice = choicePrice;
            } else if (priceType === "percentage") {
              optionPrice = (bookingData.basePrice * choicePrice) / 100;
            }
          }
          break;

        case "radio":
          const checkedRadio = $(this).find(".vb-option-radio:checked");

          if (checkedRadio.length) {
            optionValue = checkedRadio.val();
            const choicePrice = parseFloat(checkedRadio.data("price")) || 0;

            if (priceType === "fixed") {
              optionPrice = choicePrice;
            } else if (priceType === "percentage") {
              optionPrice = (bookingData.basePrice * choicePrice) / 100;
            }
          }
          break;

        case "checkbox":
          const checkedBoxes = $(this).find(".vb-option-checkbox:checked");

          if (checkedBoxes.length) {
            optionValue = [];

            checkedBoxes.each(function () {
              optionValue.push($(this).val());
              const choicePrice = parseFloat($(this).data("price")) || 0;

              if (priceType === "fixed") {
                optionPrice += choicePrice;
              } else if (priceType === "percentage") {
                optionPrice += (bookingData.basePrice * choicePrice) / 100;
              }
            });
          }
          break;
      }

      if (optionValue !== null) {
        bookingData.selectedOptions[optionIndex] = {
          type: optionType,
          value: optionValue,
          price: optionPrice,
          title: $(this).find("h4").text(),
        };

        bookingData.optionsPrices[optionIndex] = optionPrice;
        bookingData.totalOptionsPrice += optionPrice;
      }
    });
  }

  // Update the running total displayed during option selection
  function updateRunningTotal() {
    const totalPrice = calculateTotalPrice();
    $(".vb-total-amount").text(formatCurrency(totalPrice));
  }

  // Calculate total price based on base price and options
  function calculateTotalPrice() {
    let total = parseFloat(bookingData.basePrice);

    // Add all option prices
    total += bookingData.totalOptionsPrice;

    // Apply tax if any
    if (bookingData.taxRate > 0) {
      total += total * (bookingData.taxRate / 100);
    }

    // Apply discount if any
    if (bookingData.discount > 0) {
      total -= bookingData.discount;
    }

    return Math.max(0, total);
  }

  // Validate each step
  function validateStep(step) {
    const stepNumber = step.data("step");
    let isValid = true;
    let errorMessage = "";

    switch (stepNumber) {
      case 1:
        // Service selection validation (nothing to validate, service is pre-selected)
        break;

      case 2:
        // This is either Options step or Date & Time step depending on options
        if ($(".vb-option-group").length > 0) {
          // Options validation
          const requiredOptions = $(".vb-option-group").filter(function () {
            return $(this).find(".vb-option-input[required]").length > 0;
          });

          requiredOptions.each(function () {
            const optionIndex = $(this).data("option-index");
            if (
              !bookingData.selectedOptions[optionIndex] ||
              bookingData.selectedOptions[optionIndex].value === null ||
              bookingData.selectedOptions[optionIndex].value === ""
            ) {
              errorMessage = "Please fill in all required options.";
              isValid = false;
              return false; // break each loop
            }
          });
        } else {
          // Date and time validation
          const zipCode = $("#vb-zip-code").val();
          const date = $("#vb-booking-date").val();
          const timeSlot = $(".vb-time-slot.selected").length;

          if (!zipCode || zipCode.length !== 5) {
            errorMessage = "Please enter a valid ZIP code.";
            isValid = false;
          } else if (!date) {
            errorMessage = "Please select a date.";
            isValid = false;
          } else if (!timeSlot) {
            errorMessage = "Please select a time slot.";
            isValid = false;
          }
        }
        break;

      case 3:
        // This is either Date & Time step or Customer Info step
        if ($(".vb-option-group").length > 0) {
          // Date and time validation
          const zipCode = $("#vb-zip-code").val();
          const date = $("#vb-booking-date").val();
          const timeSlot = $(".vb-time-slot.selected").length;

          if (!zipCode || zipCode.length !== 5) {
            errorMessage = "Please enter a valid ZIP code.";
            isValid = false;
          } else if (!date) {
            errorMessage = "Please select a date.";
            isValid = false;
          } else if (!timeSlot) {
            errorMessage = "Please select a time slot.";
            isValid = false;
          }
        } else {
          // Customer information validation
          const name = $("#vb-customer-name").val();
          const email = $("#vb-customer-email").val();
          const phone = $("#vb-customer-phone").val();

          if (!name || !email || !phone) {
            errorMessage = "Please fill in all required fields.";
            isValid = false;
          } else if (!isValidEmail(email)) {
            errorMessage = "Please enter a valid email address.";
            isValid = false;
          }
        }
        break;

      case 4:
        // This is either Customer Info step or Review & Payment step
        if ($(".vb-option-group").length > 0) {
          // Customer information validation
          const name = $("#vb-customer-name").val();
          const email = $("#vb-customer-email").val();
          const phone = $("#vb-customer-phone").val();

          if (!name || !email || !phone) {
            errorMessage = "Please fill in all required fields.";
            isValid = false;
          } else if (!isValidEmail(email)) {
            errorMessage = "Please enter a valid email address.";
            isValid = false;
          }
        }
        break;
    }

    if (!isValid && errorMessage) {
      showError(errorMessage);
    }
    return isValid;
  }

  // Check service availability
  function checkServiceAvailability(zipCode) {
    if (!vbBookingData.service || !vbBookingData.service.id) {
      showError("No service selected.");
      return;
    }

    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_check_availability",
        nonce: vbBookingData.nonce,
        service_id: vbBookingData.service.id,
        zip_code: zipCode,
      },
      beforeSend: function () {
        showLoading();
      },
      success: function (response) {
        if (response.success) {
          // If a location-specific price is available, update the base price
          if (response.data.price) {
            bookingData.basePrice = parseFloat(response.data.price);
            calculateOptionsPrices(); // Recalculate options prices based on new base price
            updateRunningTotal();
          }
        } else {
          showError(response.data.message);
        }
      },
      error: function () {
        showError("An error occurred while checking availability.");
      },
      complete: function () {
        hideLoading();
      },
    });
  }

  // Load time slots
  function loadTimeSlots(date, zipCode) {
    // Console debugging to see what's being sent
    console.log("Loading time slots with:", {
      service_id: vbBookingData.service
        ? vbBookingData.service.id
        : "undefined",
      date: date,
      zip_code: zipCode,
      nonce: vbBookingData.nonce,
    });

    // Check if service data is available
    if (!vbBookingData.service || !vbBookingData.service.id) {
      $("#vb-time-slots").html(
        '<div class="vb-time-slots-message">Error: No service selected. Please go back and select a service.</div>'
      );
      return;
    }

    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_check_availability",
        nonce: vbBookingData.nonce,
        service_id: vbBookingData.service.id,
        date: date,
        zip_code: zipCode,
      },
      beforeSend: function () {
        showLoading();
        $("#vb-time-slots")
          .empty()
          .html(
            '<div class="vb-time-slots-message">Loading available time slots...</div>'
          );
      },
      success: function (response) {
        console.log("Time slots response:", response);
        if (response.success && response.data && response.data.slots) {
          displayTimeSlots(response.data.slots);

          // If a location-specific price is available, update base price
          if (response.data.price) {
            bookingData.basePrice = parseFloat(response.data.price);
            updateRunningTotal();
          }
        } else {
          let errorMsg = "No available time slots found for the selected date.";
          if (response.data && response.data.message) {
            errorMsg = response.data.message;
          }
          $("#vb-time-slots").html(
            '<div class="vb-time-slots-message">' + errorMsg + "</div>"
          );
        }
      },
      error: function (xhr, status, error) {
        console.error("Ajax error:", error);
        console.log("Response:", xhr.responseText);
        $("#vb-time-slots").html(
          '<div class="vb-time-slots-message">Error loading time slots. Please try again.</div>'
        );
      },
      complete: function () {
        hideLoading();
      },
    });
  }

  // Display time slots
  function displayTimeSlots(slots) {
    const container = $("#vb-time-slots");
    container.empty();

    console.log("Displaying time slots:", slots);

    if (slots && slots.length > 0) {
      slots.forEach(function (slot) {
        try {
          const slotTime = new Date(slot.start);
          // Format time as 12-hour with AM/PM
          const time = slotTime.toLocaleTimeString([], {
            hour: "2-digit",
            minute: "2-digit",
          });
          const buttonClass = slot.available ? "" : "unavailable";

          container.append(`
                    <div class="vb-time-slot ${buttonClass}" data-time="${slot.start}">
                        ${time}
                    </div>
                `);
        } catch (e) {
          console.error("Error processing time slot:", e, slot);
        }
      });
    } else {
      container.html(
        '<div class="vb-time-slots-message">No available time slots for the selected date.</div>'
      );
    }
  }

  // Apply coupon
  function applyCoupon(code) {
    if (!vbBookingData.service || !vbBookingData.service.id) {
      showError("No service selected.");
      return;
    }

    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_apply_coupon",
        nonce: vbBookingData.nonce,
        coupon: code,
        service_id: vbBookingData.service.id,
        total: calculateTotalPrice(),
      },
      beforeSend: function () {
        showLoading();
      },
      success: function (response) {
        if (response.success) {
          bookingData.discount = parseFloat(response.data.discount);
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
    // Update base price
    $("#vb-summary-base-price").text(formatCurrency(bookingData.basePrice));

    // Update selected options
    const optionsSummary = $(".vb-selected-options-summary");
    if (optionsSummary.length) {
      optionsSummary.empty();

      // Options price breakdown
      const optionsPriceContainer = $(".vb-options-price-summary");
      if (optionsPriceContainer.length) {
        optionsPriceContainer.empty();
      }

      for (const optionIndex in bookingData.selectedOptions) {
        const option = bookingData.selectedOptions[optionIndex];
        let valueDisplay = "";

        if (option.type === "checkbox" && Array.isArray(option.value)) {
          // For checkboxes, get label for each selected value
          const labels = [];
          option.value.forEach(function (val) {
            const checkbox = $(
              `.vb-option-group[data-option-index="${optionIndex}"] .vb-option-checkbox[value="${val}"]`
            );
            labels.push(checkbox.closest("label").text().trim());
          });
          valueDisplay = labels.join(", ");
        } else if (option.type === "radio" || option.type === "dropdown") {
          // For radio and dropdown, get label for selected value
          if (option.type === "radio") {
            const radio = $(
              `.vb-option-group[data-option-index="${optionIndex}"] .vb-option-radio[value="${option.value}"]`
            );
            valueDisplay = radio.closest("label").text().trim();
          } else {
            const select = $(
              `.vb-option-group[data-option-index="${optionIndex}"] .vb-option-select`
            );
            valueDisplay = select
              .find(`option[value="${option.value}"]`)
              .text()
              .trim();
          }
        } else {
          // For text, textarea, number
          valueDisplay = option.value;
        }

        // Add to options summary
        optionsSummary.append(`
                    <div class="vb-summary-row">
                        <span class="vb-summary-label">${option.title}</span>
                        <span class="vb-summary-value">${valueDisplay}</span>
                    </div>
                `);

        // Add to price breakdown if option has a price
        if (option.price > 0 && optionsPriceContainer.length) {
          optionsPriceContainer.append(`
                        <div class="vb-summary-row">
                            <span class="vb-summary-label">${
                              option.title
                            }</span>
                            <span class="vb-summary-value">+ ${formatCurrency(
                              option.price
                            )}</span>
                        </div>
                    `);
        }
      }
    }

    // Update date/time and location
    const selectedTime = $(".vb-time-slot.selected").data("time");
    if (selectedTime) {
      $("#vb-summary-datetime").text(new Date(selectedTime).toLocaleString());
    }

    const zipCode = $("#vb-zip-code").val();
    if (zipCode) {
      $("#vb-summary-location").text(zipCode);
    }

    // Update tax amount
    const subtotal = bookingData.basePrice + bookingData.totalOptionsPrice;
    const taxAmount = subtotal * (bookingData.taxRate / 100);
    $("#vb-summary-tax").text(formatCurrency(taxAmount));

    // Update discount
    if (bookingData.discount > 0) {
      $("#vb-summary-discount").text(
        "-" + formatCurrency(bookingData.discount)
      );
    }

    // Update total
    const total = calculateTotalPrice();
    $("#vb-summary-total").text(formatCurrency(total));

    // Update deposit amount
    if ($('input[name="pay_deposit"]').is(":checked")) {
      const depositAmount = calculateDeposit(total);
      $(".vb-deposit-amount").text("(" + formatCurrency(depositAmount) + ")");
    }
  }

  // Add this to your public.js file

  // Service selection handling
  $(".vb-select-service").on("click", function () {
    const serviceItem = $(this).closest(".vb-service-item");
    const serviceId = serviceItem.data("service-id");
    const subServiceSelect = serviceItem.find(".vb-sub-service-select");
    const subServiceId = subServiceSelect.length
      ? subServiceSelect.val()
      : null;

    // Check if sub-service is required but not selected
    if (
      subServiceSelect.length &&
      !subServiceId &&
      subServiceSelect.find("option").length > 1
    ) {
      alert("Please select a sub-service");
      return;
    }

    // AJAX call to get service details
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
        showLoading();
      },
      success: function (response) {
        if (response.success) {
          // Update booking data with service information
          vbBookingData.service = response.data;

          // Initialize booking data with selected service
          bookingData.basePrice = parseFloat(response.data.price || 0);
          bookingData.taxRate = parseFloat(response.data.tax_rate || 0);

          // Update service name in booking summary
          $("#vb-summary-service").text(response.data.title);

          // Move to next step
          $('.vb-step[data-step="1"]').hide();
          $('.vb-step[data-step="2"]').show();
          $(".vb-form-progress li").removeClass("active");
          $('.vb-form-progress li[data-step="2"]').addClass("active");

          // Update price display
          updateRunningTotal();
        } else {
          showError(response.data.message || "Failed to select service");
        }
      },
      error: function () {
        showError("An error occurred while selecting the service.");
      },
      complete: function () {
        hideLoading();
      },
    });
  });

  // Submit booking
  function submitBooking() {
    if (!vbBookingData.service || !vbBookingData.service.id) {
      showError("No service selected.");
      return;
    }

    // Determine which step we're on based on whether options are present
    const hasOptions = $(".vb-option-group").length > 0;
    const currentStepNumber = hasOptions ? 5 : 4;
    const currentStep = $(`.vb-step[data-step="${currentStepNumber}"]`);

    if (!validateStep(currentStep)) {
      return;
    }

    const formData = {
      service_id: vbBookingData.service.id,
      sub_service_id: vbBookingData.service.sub_service_id || null,
      booking_date: $(".vb-time-slot.selected").data("time"),
      zip_code: $("#vb-zip-code").val(),
      customer_name: $("#vb-customer-name").val(),
      customer_email: $("#vb-customer-email").val(),
      customer_phone: $("#vb-customer-phone").val(),
      customer_notes: $("#vb-customer-notes").val(),
      coupon_code: $("#vb-coupon-code").val(),
      pay_deposit: $('input[name="pay_deposit"]').is(":checked"),
      options: JSON.stringify(bookingData.selectedOptions),
      base_price: bookingData.basePrice,
      options_price: bookingData.totalOptionsPrice,
      total_amount: calculateTotalPrice(),
      tax_amount: bookingData.basePrice * (bookingData.taxRate / 100),
      discount: bookingData.discount,
    };

    // Process payment with Stripe if available
    if (stripe && cardElement) {
      stripe.createToken(cardElement).then(function (result) {
        if (result.error) {
          showError(result.error.message);
          return;
        }

        formData.stripe_token = result.token.id;
        processBooking(formData);
      });
    } else {
      // Continue without payment (admin can handle payment manually)
      processBooking(formData);
    }
  }

  // Process booking submission
  function processBooking(formData) {
    $.ajax({
      url: vbBookingData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_create_booking",
        nonce: vbBookingData.nonce,
        ...formData,
      },
      beforeSend: function () {
        showLoading();
        $(".vb-submit-booking").prop("disabled", true);
      },
      success: function (response) {
        if (response.success) {
          $("#vb-booking-reference").text(response.data.booking_id);

          // Determine next step based on whether options are present
          const hasOptions = $(".vb-option-group").length > 0;
          const currentStepNumber = hasOptions ? 5 : 4;
          const nextStepNumber = hasOptions ? 6 : 5;

          $(`.vb-step[data-step="${currentStepNumber}"]`).hide();
          $(`.vb-step[data-step="${nextStepNumber}"]`).show();
          $(".vb-form-progress li").removeClass("active");
          $(`.vb-form-progress li[data-step="${nextStepNumber}"]`).addClass(
            "active"
          );
        } else {
          showError(
            response.data.message ||
              "An error occurred while processing your booking."
          );
        }
      },
      error: function (xhr) {
        console.error("Booking error:", xhr);
        showError(
          "An error occurred while processing your booking. Please try again later."
        );
      },
      complete: function () {
        hideLoading();
        $(".vb-submit-booking").prop("disabled", false);
      },
    });
  }

  // Calculate deposit amount
  function calculateDeposit(total) {
    if (vbBookingData.service && vbBookingData.service.enable_deposit) {
      if (vbBookingData.service.deposit_type === "percentage") {
        return total * (vbBookingData.service.deposit_amount / 100);
      } else {
        return parseFloat(vbBookingData.service.deposit_amount);
      }
    }
    return 0;
  }

  // Helper functions
  function formatCurrency(amount) {
    if (isNaN(amount)) amount = 0;

    const currency = vbBookingData.currency || "USD";
    return new Intl.NumberFormat("en-US", {
      style: "currency",
      currency: currency,
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
    $(".vb-loading-overlay").show();
  }

  function hideLoading() {
    $(".vb-loading-overlay").hide();
  }
});
