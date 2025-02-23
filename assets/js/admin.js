// assets/js/admin.js

jQuery(document).ready(function ($) {
  // Status update handling
  $(".vb-status-select").on("change", function () {
    const bookingId = $(this).data("booking-id");
    const newStatus = $(this).val();

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_admin_update_booking",
        nonce: vbAdminData.nonce,
        booking_id: bookingId,
        status: newStatus,
      },
      success: function (response) {
        if (response.success) {
          toastr.success(response.data.message);
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while updating the booking status.");
      },
    });
  });

  // Coupon modal handling
  $(".vb-add-coupon").on("click", function (e) {
    e.preventDefault();
    $("#vb-coupon-form").show();
  });

  $(".vb-modal-close").on("click", function () {
    $(this).closest(".vb-modal").hide();
  });

  $(window).on("click", function (e) {
    if ($(e.target).hasClass("vb-modal")) {
      $(".vb-modal").hide();
    }
  });

  // New coupon form handling
  $("#vb-new-coupon-form").on("submit", function (e) {
    e.preventDefault();

    const formData = $(this).serializeArray();
    formData.push(
      {
        name: "action",
        value: "vb_admin_create_coupon",
      },
      {
        name: "nonce",
        value: vbAdminData.nonce,
      }
    );

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: formData,
      success: function (response) {
        if (response.success) {
          location.reload();
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while creating the coupon.");
      },
    });
  });

  // Coupon toggle handling
  $(".vb-toggle-coupon").on("click", function () {
    const couponId = $(this).data("coupon-id");
    const currentStatus = $(this).data("status");
    const newStatus = currentStatus === "active" ? "inactive" : "active";
    const $button = $(this);

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_admin_toggle_coupon",
        nonce: vbAdminData.nonce,
        coupon_id: couponId,
        status: newStatus,
      },
      success: function (response) {
        if (response.success) {
          $button
            .data("status", newStatus)
            .text(newStatus === "active" ? "Deactivate" : "Activate")
            .closest("tr")
            .find(".vb-status")
            .removeClass("vb-status-" + currentStatus)
            .addClass("vb-status-" + newStatus)
            .text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));

          toastr.success(response.data.message);
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while updating the coupon status.");
      },
    });
  });

  // Coupon deletion handling
  $(".vb-delete-coupon").on("click", function () {
    if (!confirm("Are you sure you want to delete this coupon?")) {
      return;
    }

    const couponId = $(this).data("coupon-id");
    const $row = $(this).closest("tr");

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_admin_delete_coupon",
        nonce: vbAdminData.nonce,
        coupon_id: couponId,
      },
      success: function (response) {
        if (response.success) {
          $row.fadeOut(400, function () {
            $(this).remove();
          });
          toastr.success(response.data.message);
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while deleting the coupon.");
      },
    });
  });

  // Initialize toastr notifications
  toastr.options = {
    closeButton: true,
    progressBar: true,
    positionClass: "toast-top-right",
    timeOut: "3000",
  };

  // Status update handling
  $(".vb-status-select")
    .on("change", function () {
      const bookingId = $(this).data("booking-id");
      const newStatus = $(this).val();
      const $select = $(this);

      $.ajax({
        url: vbAdminData.ajaxurl,
        type: "POST",
        data: {
          action: "vb_admin_update_booking",
          nonce: vbAdminData.nonce,
          booking_id: bookingId,
          status: newStatus,
        },
        beforeSend: function () {
          $select.prop("disabled", true);
        },
        success: function (response) {
          if (response.success) {
            toastr.success(response.data.message);
          } else {
            toastr.error(response.data.message);
            // Revert to previous value on error
            $select.val($select.data("previous-value"));
          }
        },
        error: function () {
          toastr.error("An error occurred while updating the booking status.");
          $select.val($select.data("previous-value"));
        },
        complete: function () {
          $select.prop("disabled", false);
        },
      });
    })
    .each(function () {
      // Store initial values
      $(this).data("previous-value", $(this).val());
    })
    .on("focus", function () {
      // Store value before change
      $(this).data("previous-value", $(this).val());
    });

  // Service pricing handling
  $(".vb-add-price-row").on("click", function () {
    const template = $("#vb-price-row-template").html();
    $(".vb-price-rows").append(template);
  });

  $(document).on("click", ".vb-remove-price-row", function () {
    $(this).closest(".vb-price-row").remove();
  });

  $("#vb-service-pricing-form").on("submit", function (e) {
    e.preventDefault();

    const $form = $(this);
    const $submitButton = $form.find('button[type="submit"]');
    const formData = new FormData(this);
    formData.append("action", "vb_admin_update_pricing");
    formData.append("nonce", vbAdminData.nonce);

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      beforeSend: function () {
        $submitButton.prop("disabled", true);
      },
      success: function (response) {
        if (response.success) {
          toastr.success(response.data.message);
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while updating pricing.");
      },
      complete: function () {
        $submitButton.prop("disabled", false);
      },
    });
  });

  // Coupon modal handling
  $(".vb-add-coupon").on("click", function (e) {
    e.preventDefault();
    $("#vb-coupon-form").show();
  });

  $(".vb-modal-close").on("click", function () {
    $(this).closest(".vb-modal").hide();
  });

  $(window).on("click", function (e) {
    if ($(e.target).hasClass("vb-modal")) {
      $(".vb-modal").hide();
    }
  });

  // New coupon form handling
  $("#vb-new-coupon-form").on("submit", function (e) {
    e.preventDefault();

    const $form = $(this);
    const $submitButton = $form.find('button[type="submit"]');
    const formData = new FormData(this);
    formData.append("action", "vb_admin_create_coupon");
    formData.append("nonce", vbAdminData.nonce);

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      beforeSend: function () {
        $submitButton.prop("disabled", true);
      },
      success: function (response) {
        if (response.success) {
          location.reload();
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while creating the coupon.");
      },
      complete: function () {
        $submitButton.prop("disabled", false);
      },
    });
  });

  // Booking view modal
  $(".vb-view-booking").on("click", function (e) {
    e.preventDefault();

    const bookingId = $(this).data("booking-id");

    $.ajax({
      url: vbAdminData.ajaxurl,
      type: "POST",
      data: {
        action: "vb_admin_get_booking",
        nonce: vbAdminData.nonce,
        booking_id: bookingId,
      },
      success: function (response) {
        if (response.success) {
          $("#vb-booking-details").html(response.data.html).show();
        } else {
          toastr.error(response.data.message);
        }
      },
      error: function () {
        toastr.error("An error occurred while loading booking details.");
      },
    });
  });

  // Date range filter handling
  $("#date-from, #date-to").on("change", function () {
    const fromDate = $("#date-from").val();
    const toDate = $("#date-to").val();

    if (fromDate && toDate && fromDate > toDate) {
      toastr.warning("Start date cannot be later than end date.");
      $(this).val("");
    }
  });

  // Bulk actions confirmation
  $("#vb-bookings-form").on("submit", function (e) {
    const action = $(this).find('select[name="action"]').val();

    if (action === "delete") {
      if (!confirm("Are you sure you want to delete the selected bookings?")) {
        e.preventDefault();
      }
    }
  });

  // Export handling
  $(".vb-export-btn").on("click", function (e) {
    e.preventDefault();

    const exportType = $(this).data("type");
    const filters = $("#vb-filter-form").serialize();

    window.location.href =
      vbAdminData.ajaxurl + "?" + filters + "&action=vb_export_" + exportType;
  });

  // Initialize datepicker for date inputs
  if ($.fn.datepicker) {
    $(".vb-datepicker").datepicker({
      dateFormat: "yy-mm-dd",
      changeMonth: true,
      changeYear: true,
    });
  }

  // Initialize select2 for multiple select inputs
  if ($.fn.select2) {
    $(".vb-select2").select2({
      width: "100%",
      placeholder: "Select options",
    });
  }

  // Bind ctrl+s to save forms
  $(document).on("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && e.keyCode === 83) {
      // Ctrl/Cmd + S
      e.preventDefault();
      const $form = $(".vb-admin-form:visible");
      if ($form.length) {
        $form.submit();
      }
    }
  });

  // Handle service settings tabs
  $(".vb-settings-tab").on("click", function (e) {
    e.preventDefault();

    const targetId = $(this).attr("href");

    $(".vb-settings-tab").removeClass("nav-tab-active");
    $(this).addClass("nav-tab-active");

    $(".vb-settings-panel").hide();
    $(targetId).show();
  });

  // Show first tab by default
  $(".vb-settings-tab:first").click();
});
