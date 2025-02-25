// assets/js/service-options.js

jQuery(document).ready(function ($) {
  const optionsContainer = $(".vb-service-options-list");
  let currentOptionIndex = $(".vb-service-option").length;

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

  // Make choices sortable within each option
  $(".vb-option-choices-list").sortable({
    handle: ".vb-choice-sort",
    items: ".vb-option-choice",
    axis: "y",
    update: function () {
      // Reindex choices after sorting
      const optionIndex = $(this).closest(".vb-service-option").data("index");
      reindexChoices(optionIndex);
    },
  });

  // Add new option
  $(".vb-add-option").on("click", function () {
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

          // Initialize sortable for new option choices
          optionsContainer
            .find(
              '.vb-service-option[data-index="' +
                currentOptionIndex +
                '"] .vb-option-choices-list'
            )
            .sortable({
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

          currentOptionIndex++;
        }
      },
    });
  });

  // Toggle option content
  $(document).on("click", ".vb-option-toggle", function () {
    const content = $(this)
      .closest(".vb-service-option")
      .find(".vb-option-content");
    content.slideToggle(300);
    $(this).toggleClass("dashicons-arrow-down-alt2 dashicons-arrow-up-alt2");
  });

  // Remove option
  $(document).on("click", ".vb-option-remove", function () {
    if (confirm("Are you sure you want to remove this option?")) {
      $(this).closest(".vb-service-option").remove();
      reindexOptions();
    }
  });

  // Add choice
  $(document).on("click", ".vb-add-choice", function () {
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
        }
      },
    });
  });

  // Remove choice
  $(document).on("click", ".vb-choice-remove", function () {
    const choiceEl = $(this).closest(".vb-option-choice");
    const optionIndex = choiceEl.closest(".vb-service-option").data("index");

    choiceEl.remove();
    reindexChoices(optionIndex);
  });

  // Update option title in header when title input changes
  $(document).on("change keyup", ".vb-option-title-input", function () {
    const title = $(this).val() || "New Option";
    $(this)
      .closest(".vb-service-option")
      .find(".vb-option-title h4")
      .text(title);
  });

  // Change display based on option type
  $(document).on("change", ".vb-option-type-select", function () {
    const type = $(this).val();
    const optionEl = $(this).closest(".vb-service-option");
    const choicesContainer = optionEl.find(".vb-option-choices-container");

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
  });

  // Change price type options based on option type
  $(document).on(
    "change",
    ".vb-option-type-select, .vb-option-price-type-select",
    function () {
      const optionEl = $(this).closest(".vb-service-option");
      const type = optionEl.find(".vb-option-type-select").val();
      const priceType = optionEl.find(".vb-option-price-type-select").val();

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
  );

  // Prevent multiple defaults for radio and dropdown
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

  // Initialize option type display
  $(".vb-option-type-select").each(function () {
    $(this).trigger("change");
  });

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
});
