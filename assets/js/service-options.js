// assets/js/service-options.js

jQuery(document).ready(function ($) {
  const optionsContainer = $(".vb-service-options-list");
  let currentOptionIndex = $(".vb-service-option").length;

  // Make sure all option content is collapsed by default
  $(".vb-option-content").hide();
  $(".vb-option-toggle")
    .removeClass("dashicons-arrow-up-alt2")
    .addClass("dashicons-arrow-down-alt2");

  // Make options sortable
  optionsContainer.sortable({
    handle: ".vb-option-sort",
    items: ".vb-service-option",
    axis: "y",
    update: function () {
      // Reindex options after sorting
      reindexOptions();
    },
  });

  // Initialize sortable for choice lists
  initChoicesSortable();

  // Add new option - ONLY bind once
  $(".vb-add-option")
    .off("click")
    .on("click", function () {
      $.ajax({
        url: vbServiceOptions.ajaxurl,
        type: "POST",
        data: {
          action: "vb_get_option_template",
          nonce: vbServiceOptions.nonce,
          index: currentOptionIndex,
        },
        success: function (response) {
          if (response.success) {
            optionsContainer.append(response.data.template);

            // Make sure the new option content is collapsed by default
            optionsContainer
              .find(
                '.vb-service-option[data-index="' +
                  currentOptionIndex +
                  '"] .vb-option-content'
              )
              .hide();

            // Initialize sortable for new option choices
            initChoicesSortable();

            // Initialize default display states
            updateOptionTypeDisplay(
              optionsContainer.find(
                '.vb-service-option[data-index="' + currentOptionIndex + '"]'
              )
            );

            currentOptionIndex++;
          }
        },
      });
    });

  // Toggle option content - Use delegated event handler
  $(document)
    .off("click", ".vb-option-toggle")
    .on("click", ".vb-option-toggle", function () {
      const content = $(this)
        .closest(".vb-service-option")
        .find(".vb-option-content");
      content.slideToggle(300);
      $(this).toggleClass("dashicons-arrow-down-alt2 dashicons-arrow-up-alt2");
    });

  // Remove option - Use delegated event handler
  $(document)
    .off("click", ".vb-option-remove")
    .on("click", ".vb-option-remove", function () {
      if (confirm("Are you sure you want to remove this option?")) {
        $(this).closest(".vb-service-option").remove();
        reindexOptions();
      }
    });

  // Add choice - Use delegated event handler
  $(document)
    .off("click", ".vb-add-choice")
    .on("click", ".vb-add-choice", function () {
      const optionEl = $(this).closest(".vb-service-option");
      const optionIndex = optionEl.data("index");
      const choicesContainer = optionEl.find(".vb-option-choices-list");
      const choiceIndex = choicesContainer.find(".vb-option-choice").length;

      $.ajax({
        url: vbServiceOptions.ajaxurl,
        type: "POST",
        data: {
          action: "vb_get_choice_template",
          nonce: vbServiceOptions.nonce,
          option_index: optionIndex,
          choice_index: choiceIndex,
        },
        success: function (response) {
          if (response.success) {
            choicesContainer.append(response.data.template);
            initChoicesSortable();
          }
        },
      });
    });

  // Remove choice - Use delegated event handler
  $(document)
    .off("click", ".vb-choice-remove")
    .on("click", ".vb-choice-remove", function () {
      const choiceEl = $(this).closest(".vb-option-choice");
      const optionIndex = choiceEl.closest(".vb-service-option").data("index");

      choiceEl.remove();
      reindexChoices(optionIndex);
    });

  // Update option title in header when title input changes - Use delegated event handler
  $(document).on("change keyup", ".vb-option-title-input", function () {
    const title = $(this).val() || "New Option";
    $(this)
      .closest(".vb-service-option")
      .find(".vb-option-title h4")
      .text(title);
  });

  // Change display based on option type - Use delegated event handler
  $(document).on("change", ".vb-option-type-select", function () {
    const optionEl = $(this).closest(".vb-service-option");
    updateOptionTypeDisplay(optionEl);
  });

  // Prevent multiple defaults for radio and dropdown - Use delegated event handler
  $(document).on(
    "change",
    '.vb-choice-default input[type="checkbox"]',
    function () {
      if ($(this).prop("checked")) {
        const optionEl = $(this).closest(".vb-service-option");
        const type = optionEl.find(".vb-option-type-select").val();

        // For radio and dropdown, only one default is allowed
        if (type === "radio" || type === "dropdown") {
          optionEl
            .find('.vb-choice-default input[type="checkbox"]')
            .not(this)
            .prop("checked", false);
        }
      }
    }
  );

  // Initialize option type display for existing options
  $(".vb-service-option").each(function () {
    updateOptionTypeDisplay($(this));
  });

  // Helper function to initialize sortable for choice lists
  function initChoicesSortable() {
    $(".vb-option-choices-list").each(function () {
      if (!$(this).hasClass("ui-sortable")) {
        $(this).sortable({
          handle: ".vb-choice-sort",
          items: ".vb-option-choice",
          axis: "y",
          update: function () {
            const optionIndex = $(this)
              .closest(".vb-service-option")
              .data("index");
            reindexChoices(optionIndex);
          },
        });
      }
    });
  }

  // Helper function to update option type display
  function updateOptionTypeDisplay(optionEl) {
    const type = optionEl.find(".vb-option-type-select").val();
    const choicesContainer = optionEl.find(".vb-option-choices-container");
    const priceType = optionEl.find(".vb-option-price-type-select").val();

    choicesContainer.attr("data-type", type);

    // Show/hide appropriate fields based on type
    if (type === "number" || type === "text" || type === "textarea") {
      // For these types, we only need one choice (for default value and price)
      const choicesList = choicesContainer.find(".vb-option-choices-list");
      // Keep only one choice
      if (choicesList.find(".vb-option-choice").length > 1) {
        choicesList.find(".vb-option-choice:not(:first)").remove();
      }

      // Change the label for the choice
      choicesContainer
        .find(".vb-choice-label input")
        .attr("placeholder", "Default value");
      choicesList.find(".vb-choice-default").hide();
    } else {
      // For dropdown, checkbox, radio
      choicesContainer
        .find(".vb-choice-label input")
        .attr("placeholder", "Choice label");
      choicesContainer.find(".vb-choice-default").show();
    }

    // If number input, we can enable multiply price type
    if (type === "number") {
      optionEl
        .find('.vb-option-price-type-select option[value="multiply"]')
        .prop("disabled", false);
    } else {
      const multiplyOption = optionEl.find(
        '.vb-option-price-type-select option[value="multiply"]'
      );
      multiplyOption.prop("disabled", true);

      // If currently selected, switch to fixed
      if (priceType === "multiply") {
        optionEl.find(".vb-option-price-type-select").val("fixed");
      }
    }
  }

  // Reindex options
  function reindexOptions() {
    $(".vb-service-option").each(function (newIndex) {
      const oldIndex = $(this).data("index");
      $(this).data("index", newIndex).attr("data-index", newIndex);

      // Update all input names
      $(this)
        .find("input, select, textarea")
        .each(function () {
          const name = $(this).attr("name");
          if (name) {
            const newName = name.replace(
              /vb_service_options\[\d+\]/,
              "vb_service_options[" + newIndex + "]"
            );
            $(this).attr("name", newName);
          }

          const id = $(this).attr("id");
          if (id && id.includes("vb_option_")) {
            const newId = id.replace(/_\d+$/, "_" + newIndex);
            $(this).attr("id", newId);

            // Update associated labels
            const label = $('label[for="' + id + '"]');
            if (label.length) {
              label.attr("for", newId);
            }
          }
        });

      // Reindex choices within this option
      reindexChoices(newIndex, oldIndex);
    });
  }

  // Reindex choices within an option
  function reindexChoices(optionIndex, oldOptionIndex) {
    const selector =
      typeof oldOptionIndex !== "undefined"
        ? '.vb-service-option[data-index="' +
          optionIndex +
          '"] .vb-option-choice'
        : '.vb-service-option[data-index="' +
          optionIndex +
          '"] .vb-option-choice';

    $(selector).each(function (choiceIndex) {
      $(this)
        .data("choice-index", choiceIndex)
        .attr("data-choice-index", choiceIndex);

      // Update all input names
      $(this)
        .find("input, select")
        .each(function () {
          const name = $(this).attr("name");
          if (name) {
            let newName;
            if (typeof oldOptionIndex !== "undefined") {
              newName = name.replace(
                new RegExp(
                  "vb_service_options\\[" +
                    oldOptionIndex +
                    "\\]\\[choices\\]\\[\\d+\\]"
                ),
                "vb_service_options[" +
                  optionIndex +
                  "][choices][" +
                  choiceIndex +
                  "]"
              );
            } else {
              newName = name.replace(
                new RegExp("\\[choices\\]\\[\\d+\\]"),
                "[choices][" + choiceIndex + "]"
              );
            }
            $(this).attr("name", newName);
          }
        });
    });
  }

  // Add first option if none exist
  if ($(".vb-service-option").length === 0) {
    $(".vb-add-option").trigger("click");
  }

  // Hide the redundant Service Settings section when Service Options is present
  if ($("#vb_service_options").length) {
    // Find the Service Settings metabox and hide it or its content
    $("#service_settings, .service-settings-metabox").hide();
  }
});
